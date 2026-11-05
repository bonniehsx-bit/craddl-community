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
use app\models\PayoutLog;
use app\models\UserSettlementInfo;
use Yii;
use yii\helpers\Json;

/**
 * 支付宝打款渠道实现类
 * [功能]
 * 1. 定义前端进件字段 (支付宝账号、真实姓名)
 * 2. 校验进件数据
 * 3. 提供账户信息回显 (getUserAccountInfo)
 * 4. 执行打款逻辑 (默认为线下/手动打款模式，记录流水)
 */
class AlipayFund extends BaseFund
{
    /** @var array 从数据库加载的配置 */
    private $config;

    public function __construct($mall_id)
    {
        $this->mall_id = $mall_id;
        $fundSettings = OptionHelper::get('funds', $mall_id);

        // 读取配置，如果没配则使用默认值，status=0 默认关闭
        $this->config = $fundSettings[self::class] ?? ['name' => '支付宝', 'status' => 0];

        // 可以在此处检查 status，但建议在 checkUserEligibility 中检查，以免影响后台配置读取
        $this->name = $this->config['name'];
        $this->fund_type = self::class;
    }

    /**
     * @inheritdoc
     * 定义前端渲染所需的字段 (核心)
     * 这些字段会自动显示在 settlement.vue 页面中
     */
    public function getOnboardingFields(): array
    {
        return [
            [
                'key' => 'real_name',
                'label' => '真实姓名',
                'type' => 'text',
                'required' => true,
                'placeholder' => '请输入支付宝实名认证的姓名'
            ],
            [
                'key' => 'alipay_account',
                'label' => '支付宝账号',
                'type' => 'text', // 支付宝账号可能是手机号或邮箱
                'required' => true,
                'placeholder' => '请输入支付宝账号(手机号/邮箱)'
            ],
        ];
    }

    /**
     * @inheritdoc
     * 校验用户提交的进件数据
     * 对应 UserSettlementForm -> OnboardingService -> onboardPerson
     */
    public function onboardPerson(array $userData): array
    {
        // 1. 基础字段校验
        if (empty($userData['real_name'])) {
            return ['status' => 'failed', 'message' => '请输入真实姓名'];
        }
        if (empty($userData['alipay_account'])) {
            return ['status' => 'failed', 'message' => '请输入支付宝账号'];
        }

        // 2. 返回成功状态
        // 对于手动支付宝转账模式，只要数据格式有，就视为校验通过
        return [
            'status' => 'success', // 对应 UserSettlementInfo::FUND_STATUS_SUCCESS
            'message' => '支付宝信息保存成功',
            'data' => $userData
        ];
    }

    /**
     * @inheritdoc
     * 检查用户是否有资格提现
     */
    public function checkUserEligibility(array $userData): array
    {
        $userId = $userData['user_id'] ?? null;
        if (!$userId) return ['is_eligible' => false, 'message' => '用户ID缺失'];

        // 检查配置是否开启
        if (empty($this->config['status'])) {
            return ['is_eligible' => false, 'message' => '支付宝提现通道暂时关闭'];
        }

        // 检查本地是否有成功的进件记录
        $statusInfo = $this->checkOnboardingStatus($userId);

        if ($statusInfo['status'] === UserSettlementInfo::FUND_STATUS_SUCCESS) {
            return ['is_eligible' => true, 'message' => '资格校验通过', 'result' => $statusInfo];
        }

        return [
            'is_eligible' => false,
            'message' => '请先填写支付宝收款信息',
            'require_onboarding' => true
        ];
    }

    /**
     * @inheritdoc
     * 检查本地进件状态
     */
    public function checkOnboardingStatus(int $userId): array
    {
        $model = UserSettlementInfo::findOne(['user_id' => $userId, 'mall_id' => $this->mall_id]);

        if (!$model) {
            return ['status' => UserSettlementInfo::FUND_STATUS_NONE, 'message' => '未填写信息'];
        }

        $statusInfo = $model->getOnboardingStatus(self::class);

        if (empty($statusInfo)) {
            return ['status' => UserSettlementInfo::FUND_STATUS_NONE, 'message' => '未填写信息'];
        }

        // 回显数据
        $allChannelData = $model->getChannelData();
        $statusInfo['settlement_data'] = $allChannelData[self::class] ?? [];

        return $statusInfo;
    }

    /**
     * @inheritdoc
     * [核心实现] 获取用户账户信息
     * 专门用于解决 CashApplyForm 中字段不存在的问题。
     * 将 JSON 数据映射为标准提现字段。
     */
    public function getUserAccountInfo($settlementInfo): array
    {
        if (!$settlementInfo) {
            return [];
        }

        // 1. 获取本渠道(AlipayFund)存的数据
        $allChannelData = $settlementInfo->getChannelData();
        $myData = $allChannelData[self::class] ?? [];

        // 2. 提取数据
        return [
            'type'      => 'alipay',
            'bank_name' => '支付宝', // 固定显示名称
            // 映射 account 字段
            'account'   => $myData['alipay_account'] ?? '',
            'real_name' => $myData['real_name'] ?? $settlementInfo->real_name,
            'mobile'    => $myData['mobile'] ?? $settlementInfo->mobile,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getSupportedChannels(): array
    {
        return ['alipay'];
    }

    /**
     * @inheritdoc
     * 执行打款逻辑
     * 这里默认为：记录一条“待打款”的日志，由管理员线下转账。
     * 如果您后续对接了企业支付宝API，请在此处修改逻辑调用API。
     */
    public function payout(array $payoutData): array
    {
        $channel = $payoutData['channel'];
        $userId = $payoutData['user_id'];

        if ($channel !== 'alipay') {
            throw new \Exception("支付宝渠道不支持 '{$channel}' 提现方式。");
        }

        // 1. 验证参数
        if (empty($payoutData['account']) || empty($payoutData['real_name'])) {
            throw new \Exception("用户的支付宝信息不完整(账号或姓名缺失)");
        }

        try {
            // 2. 记录 PayoutLog
            // 状态设为 PENDING (待处理)，因为是手动打款模式
            $payoutLog = new PayoutLog();
            $payoutLog->mall_id = $this->mall_id;
            $payoutLog->user_id = $userId;
            $payoutLog->source_id = $payoutData['source_id'];
            $payoutLog->source_type = $payoutData['source_type'];
            $payoutLog->fund_class = self::class;
            $payoutLog->channel = 'alipay';
            $payoutLog->amount = $payoutData['amount'];

            // 状态为待处理
            $payoutLog->status = PayoutLog::STATUS_PENDING;

            $requestData = [
                'account'   => $payoutData['account'],
                'real_name' => $payoutData['real_name'],
                'remark'    => $payoutData['remark'] ?? '申请提现到支付宝'
            ];

            $payoutLog->request_data = Json::encode($requestData);
            $payoutLog->payout_no = 'ALI-' . $payoutData['order_no'];

            if (!$payoutLog->save()) {
                throw new \Exception("保存流水失败: " . json_encode($payoutLog->errors));
            }

            // 支付宝(手动模式)是线下转账:只落待办流水,钱尚未打出。
            // 返回 'doing'(打款中)而非 'success',避免上层误置"已到账"造成对账错乱/资损。
            // 等管理员线下转账后再手动「确认到账」。
            return [
                'status' => 'doing',
                'message' => '提现申请已记录，等待管理员线下转账后确认到账',
                'payout_no' => $payoutLog->payout_no
            ];

        } catch (\Exception $e) {
            Yii::error("[AlipayFund::payout] 异常: " . $e->getMessage());
            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * @inheritdoc
     */
    public function getDisplay()
    {
        return ['name' => $this->name, 'fund' => self::class];
    }
}