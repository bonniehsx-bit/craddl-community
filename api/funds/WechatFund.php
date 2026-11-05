<?php
/**
 * Copyright (c) 2026 佛山今日鼎盛信息科技有限公司
 * 官网：https://www.craddl.cn
 * The contents of this file are subject to the Common Public Attribution License Version 1.0 (the
 * “License”); you may not use this file except in compliance with the License. You may obtain a
 * copy of the License from LICENSE in the source package or
 * https://opensource.org/license/cpal-1.0. The License is based on the Mozilla Public License
 * Version 1.1 but Sections 14 and 15 have been added to cover use of software over a computer
 * network and provide for limited attribution for the Original Developer. In addition, Exhibit A
 * has been modified to be consistent with Exhibit B.
 * Software distributed under the License is distributed on an “AS IS” basis, WITHOUT WARRANTY OF
 * ANY KIND, either express or implied. See the License for the specific language governing rights
 * and limitations under the License.
 * The Original Code is Craddl Community Edition.
 * The Original Developer is the Initial Developer.
 * The Initial Developer of the Original Code is 佛山今日鼎盛信息科技有限公司. All portions of the code written
 * by 佛山今日鼎盛信息科技有限公司 are Copyright (c) 2026. All Rights Reserved.
 */

namespace app\funds;

use app\helpers\OptionHelper;
use app\helpers\WechatPayHelper;
use app\models\PayoutLog;
use app\models\User;
use app\models\UserSettlementInfo;
use Exception;
use Yii;
use yii\helpers\Json;

/**
 * 微信支付打款渠道
 * [最终重构版] - 通过微信商家转账到零钱(V3)功能进行打款。
 *
 * 核心功能：
 * 1. 自动获取用户 OpenID (getUserAccountInfo)
 * 2. 允许用户补充真实姓名 (onboardPerson)
 * 3. 执行打款 (payout)
 */
class WechatFund extends BaseFund
{
    /** @var array 从数据库加载的配置 */
    private $config;

    public function __construct($mall_id)
    {
        $this->mall_id = $mall_id;
        $fund = OptionHelper::get('funds', $mall_id);

        // 允许配置不存在时不抛致命错误，保证后台列表正常显示
        $this->config = $fund[self::class] ?? [];

        $this->name = $this->config['name'] ?? '微信零钱';
        $this->fund_type = self::class;
    }

    /**
     * @inheritdoc
     * 定义前端进件字段
     * 微信提现通常不需要用户填账号，但为了大额提现安全，可以收集真实姓名
     */
    public function getOnboardingFields(): array
    {
        return [
            [
                'key' => 'real_name',
                'label' => '真实姓名',
                'type' => 'text',
                'required' => true, // 建议必填，用于微信实名校验
                'placeholder' => '请输入微信实名认证的姓名'
            ],
            // 微信 OpenID 通常自动获取，不需要用户填
        ];
    }

    /**
     * @inheritdoc
     * 保存进件数据 (真实姓名) 到本地
     */
    public function onboardPerson(array $userData): array
    {
        $userId = $userData['user_id'] ?? null;
        if (!$userId) return ['status' => 'failed', 'message' => '用户ID缺失'];

        if (empty($userData['real_name'])) {
            return ['status' => 'failed', 'message' => '真实姓名不能为空'];
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $model = UserSettlementInfo::findOne(['user_id' => $userId, 'mall_id' => $this->mall_id]);
            if (!$model) {
                $model = new UserSettlementInfo();
                $model->mall_id = $this->mall_id;
                $model->user_id = $userId;
            }

            // 更新物理字段
            $model->real_name = $userData['real_name'];
            $model->mobile = $userData['mobile'] ?? $model->mobile;

            // 更新渠道数据
            $allChannelData = $model->getChannelData();
            $allChannelData[self::class] = [
                'real_name' => $userData['real_name'],
                // 微信 OpenID 不存这里，而是直接读 User 表，保证实时性
            ];
            $model->setChannelData($allChannelData);

            // 更新状态
            $model->updateOnboardingStatus(self::class, [
                'status' => UserSettlementInfo::FUND_STATUS_SUCCESS,
                'message' => '信息保存成功'
            ]);

            if (!$model->save()) {
                throw new \Exception($this->getErrorMsg($model));
            }

            $transaction->commit();
            return ['status' => 'success', 'message' => '保存成功'];

        } catch (\Exception $e) {
            $transaction->rollBack();
            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * @inheritdoc
     * [核心新增] 获取账户信息
     * 供 WithdrawalApplyForm 使用，自动提取 OpenID 和 姓名
     */
    public function getUserAccountInfo($settlementInfo): array
    {
        if (!$settlementInfo) return [];

        $userId = $settlementInfo->user_id;
        $user = User::findOne($userId);
        if (!$user) return [];

        // 1. 获取真实姓名 (优先用本渠道存的，没有则用物理字段)
        $allChannelData = $settlementInfo->getChannelData();
        $myData = $allChannelData[self::class] ?? [];
        $realName = $myData['real_name'] ?? $settlementInfo->real_name;

        // 2. 智能获取 OpenID
        // 逻辑：尝试获取当前平台(Yii::$app->platform)对应的OpenID
        // 如果无法确定平台（如API调用），优先取小程序，其次取公众号
        $platform = Yii::$app->platform ?? '';
        $openid = '';

        if ($platform === 'wxapp' || $platform === 'miniapp') {
            $openid = $user->wechat_openid; // 小程序
        } elseif ($platform === 'wechat' || $platform === 'official') {
            $openid = $user->mpwx_openid; // 公众号
        } else {
            // 兜底策略
            $openid = $user->wechat_openid ?: $user->mpwx_openid;
        }

        return [
            'type'      => 'wechat',
            'bank_name' => '微信零钱',
            'account'   => '微信零钱', // 用于前端显示
            'real_name' => $realName,
            'mobile'    => $settlementInfo->mobile,
            'openid'    => $openid, // [关键] 返回OpenID
            'platform'  => $platform, // 返回当前平台标识
        ];
    }

    /**
     * @inheritdoc
     */
    public function getSupportedChannels(): array
    {
        return ['wechat'];
    }

    /**
     * @inheritdoc
     * 执行打款 (商家转账到零钱 V3)
     */
    public function payout(array $payoutData): array
    {
        $channel = $payoutData['channel'];

        if ($channel !== 'wechat') {
            throw new Exception("微信支付渠道不支持 '{$channel}' 提现方式。");
        }

        // 1. 获取关键参数
        // CashPayoutForm 传递的标准化参数中包含 openid, real_name
        $openid = $payoutData['openid'] ?? null;
        $realName = $payoutData['real_name'] ?? null;

        // 平台标识(决定 V3 支付 APPID)：【解耦】优先从调用方传入的 payoutData 取，网关不该回查业务模型。
        $platform = !empty($payoutData['platform']) ? $payoutData['platform'] : 'wxapp'; // 默认小程序
        // (原有一段老 cash 链的 platform 兼容回查,老链已下线,随之移除)

        if (empty($openid)) {
            throw new \InvalidArgumentException('打款失败：缺少用户OpenID');
        }

        // 2. 加载对应平台的配置 (AppID)
        // config 配置结构通常是 ['wxapp' => [...], 'wechat' => [...]]
        // 需根据 platform 转换一下 key，例如 mpwx -> wxapp
        $configKey = ($platform === 'mpwx' || $platform === 'miniapp') ? 'wxapp' : $platform;
        $payConfig = $this->config[$configKey] ?? ($this->config['wxapp'] ?? $this->config);

        if (empty($payConfig['app_id']) || empty($payConfig['mchid']) || empty($payConfig['cert_pem'])) {
            // 尝试读取最外层配置作为兜底
            if (!empty($this->config['app_id'])) {
                $payConfig = $this->config;
            } else {
                throw new Exception("未找到平台 '{$platform}' 的有效微信支付配置");
            }
        }

        $appid = $payConfig['app_id'];

        try {
            Yii::warning("[WechatFund::payout] 开始打款 Order:{$payoutData['order_no']} OpenID:{$openid}");

            // 3. 准备 V3 转账参数
            $amountFen = (int)bcmul($payoutData['amount'], 100);

            $transferDetail = [
                'out_detail_no'   => $payoutData['order_no'],
                'transfer_amount' => $amountFen,
                'transfer_remark' => $payoutData['remark'] ?? '提现到账',
                'openid'          => $openid,
            ];

            // 真实姓名校验逻辑
            // 金额 >= 2000元 (200000分) 或 后台配置强制校验时
            // 注意：V3 接口如果传了 user_name，微信就会强制校验
            if (!empty($realName)) {
                $transferDetail['user_name'] = $realName;
            }

            if ($amountFen >= 200000 && empty($transferDetail['user_name'])) {
                throw new \Exception('转账金额 >= 2000元，必须提供用户真实姓名以供校验');
            }

            $params = [
                'appid' => $appid,
                'out_batch_no' => $payoutData['order_no'],
                'batch_name'   => '余额提现',
                'batch_remark' => '用户申请提现',
                'total_amount' => $amountFen,
                'total_num'    => 1,
                'transfer_detail_list' => [$transferDetail],
            ];

            // 4. 初始化 SDK 并请求
            $instance = WechatPayHelper::getSDKInstance($payConfig);
            $resp = $instance->chain('v3/transfer/batches')->post(['json' => $params]);

            $resBody = $resp->getBody()->getContents();
            $resData = Json::decode($resBody);

            Yii::warning("[WechatFund::payout] 微信响应: " . $resBody);

            // 5. 状态映射
            // batch_status: ACCEPTED(已受理), PROCESSING(处理中), SUCCESS(成功), FAIL(失败)
            // 注意：商家转账到零钱通常是异步的，很少直接返回 SUCCESS
            $statusMap = [
                'SUCCESS'    => 'success',
                'ACCEPTED'   => 'doing',
                'PROCESSING' => 'doing',
                'FAIL'       => 'failed',
                'CLOSED'     => 'failed'
            ];
            $internalStatus = $statusMap[$resData['batch_status'] ?? ''] ?? 'doing';

            $this->createPayoutLog($payoutData, $params, $resData, $internalStatus);

            return [
                'status'    => $internalStatus,
                'message'   => '微信已受理: ' . ($resData['batch_status'] ?? '处理中'),
                'payout_no' => $resData['batch_id'] ?? ''
            ];

        } catch (\Exception $e) {
            Yii::error("[WechatFund::payout] 异常: " . $e->getMessage());

            // 尝试解析更详细的 API 错误
            $msg = $e->getMessage();
            if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
                $rBody = $e->getResponse()->getBody()->getContents();
                $rData = json_decode($rBody, true);
                if (isset($rData['message'])) {
                    $msg = $rData['message'];
                }
            }

            $this->createPayoutLog($payoutData, $params ?? [], ['error' => $msg], 'failed');
            return ['status' => 'failed', 'message' => $msg];
        }
    }

    /**
     * @inheritdoc
     * 检查资格
     */
    public function checkUserEligibility(array $userData): array
    {
        $user = User::findOne($userData['user_id']);
        if (!$user) {
            return ['is_eligible' => false, 'message' => '用户不存在'];
        }

        // 检查用户是否绑定了微信
        if (empty($user->wechat_openid) && empty($user->mpwx_openid)) {
            return [
                'is_eligible' => false,
                'message' => '请先在微信中登录或绑定微信',
                'require_onboarding' => false
            ];
        }

        // 检查是否填写了真实姓名 (如果后台要求)
        $statusInfo = $this->checkOnboardingStatus($userData['user_id']);
        // 这里策略比较灵活：微信通常不需要进件，所以即便没有 success 也算通过
        // 除非我们强制要求实名
        return ['is_eligible' => true, 'message' => '资格校验通过', 'result' => $statusInfo];
    }

    /**
     * @inheritdoc
     */
    public function checkOnboardingStatus(int $userId): array
    {
        $model = UserSettlementInfo::findOne(['user_id' => $userId, 'mall_id' => $this->mall_id]);
        if (!$model) {
            return ['status' => UserSettlementInfo::FUND_STATUS_NONE, 'message' => '未填写信息'];
        }
        $statusInfo = $model->getOnboardingStatus(self::class);
        $statusInfo['settlement_data'] = $model->getChannelData()[self::class] ?? [];
        return $statusInfo;
    }

    /**
     * 创建打款日志
     */
    private function createPayoutLog(array $payoutData, array $request, array $response, string $status)
    {
        $statusMap = [
            'success' => PayoutLog::STATUS_SUCCESS,
            'doing'   => PayoutLog::STATUS_PENDING,
            'failed'  => PayoutLog::STATUS_FAIL,
        ];

        $payoutLog = new PayoutLog();
        $payoutLog->mall_id = $this->mall_id;
        $payoutLog->user_id = $payoutData['user_id'];
        $payoutLog->source_id = $payoutData['source_id'];
        $payoutLog->source_type = $payoutData['source_type'];
        $payoutLog->fund_class = self::class;
        $payoutLog->channel = $payoutData['channel'];
        $payoutLog->amount = $payoutData['amount'];
        $payoutLog->status = $statusMap[$status] ?? PayoutLog::STATUS_PENDING;
        $payoutLog->request_data = Json::encode($request);
        $payoutLog->response_data = Json::encode($response);
        $payoutLog->payout_no = $response['batch_id'] ?? ($response['payout_no'] ?? '');

        if ($status === 'success') {
            $payoutLog->completed_at = time();
        }
        $payoutLog->save();
    }

    /**
     * @inheritdoc
     */
    public function getDisplay()
    {
        return ['name' => $this->name, 'fund' => self::class];
    }
}