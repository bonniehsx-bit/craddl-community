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
 * 手动打款渠道 / 线下转账
 * [最终完整版]
 *
 * 适用场景：
 * 1. 管理员线下转账，仅在系统记录流水。
 * 2. 作为“默认/通用”的银行卡信息收集渠道。
 */
class ManualFund extends BaseFund
{
    /** @var array 从数据库加载的配置 */
    private $config;

    public function __construct($mall_id)
    {
        $this->mall_id = $mall_id;
        $fund = OptionHelper::get('funds', $mall_id);

        // 允许配置不存在时不抛致命错误，保证代码健壮性
        $this->config = $fund[self::class] ?? [];

        $this->name = $this->config['name'] ?? '手动打款';
        $this->fund_type = self::class;
    }

    /**
     * @inheritdoc
     * 定义前端通用进件字段
     * 如果手动打款被设为默认进件渠道，前端将显示这些字段
     */
    public function getOnboardingFields(): array
    {
        return [
            [
                'key' => 'real_name',
                'label' => '真实姓名',
                'type' => 'text',
                'required' => true,
                'placeholder' => '请输入收款人姓名'
            ],
            [
                'key' => 'bank_name',
                'label' => '收款方式/银行',
                'type' => 'text',
                'required' => true,
                'placeholder' => '例如: 招商银行 / 支付宝 / 微信'
            ],
            [
                'key' => 'account',
                'label' => '收款账号',
                'type' => 'text',
                'required' => true,
                'placeholder' => '请输入银行卡号或账号'
            ],
            [
                // 银行卡跨行/对公必填；支付宝/微信免填，故设为可选
                'key' => 'bank_branch_name',
                'label' => '开户支行',
                'type' => 'text',
                'required' => false,
                'placeholder' => '银行卡跨行/对公需填，如：招商银行深圳科技园支行',
                'note' => '支付宝/微信收款可不填'
            ],
            [
                'key' => 'mobile',
                'label' => '联系手机',
                'type' => 'text',
                'required' => true,
                'placeholder' => '请输入预留手机号'
            ],
            [
                // 大额/实名核验用；UserSettlementInfo 有物理字段，一并冗余
                'key' => 'identity_no',
                'label' => '身份证号',
                'type' => 'text',
                'required' => false,
                'placeholder' => '大额提现/实名核验时填写'
            ],
        ];
    }

    /**
     * @inheritdoc
     * 保存进件数据
     */
    public function onboardPerson(array $userData): array
    {
        $userId = $userData['user_id'] ?? null;
        if (!$userId) return ['status' => 'failed', 'message' => '用户ID缺失'];

        // 基础校验
        if (empty($userData['account']) || empty($userData['real_name'])) {
            return ['status' => 'failed', 'message' => '姓名和账号不能为空'];
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
            if (!empty($userData['identity_no'])) {
                $model->identity_no = $userData['identity_no'];
            }

            // 更新渠道数据
            $allChannelData = $model->getChannelData();
            $allChannelData[self::class] = [
                'real_name'        => $userData['real_name'],
                'bank_name'        => $userData['bank_name'],
                'account'          => $userData['account'],
                'bank_branch_name' => $userData['bank_branch_name'] ?? '',
                'mobile'           => $userData['mobile'] ?? '',
                'identity_no'      => $userData['identity_no'] ?? '',
            ];
            $model->setChannelData($allChannelData);

            // 手动渠道默认直接成功
            $model->updateOnboardingStatus(self::class, [
                'status' => UserSettlementInfo::FUND_STATUS_SUCCESS,
                'message' => '保存成功'
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
     * 解决 WithdrawalApplyForm 报错的关键
     */
    public function getUserAccountInfo($settlementInfo): array
    {
        if (!$settlementInfo) return [];

        $allChannelData = $settlementInfo->getChannelData();
        $myData = $allChannelData[self::class] ?? [];

        return [
            'type'             => 'manual',
            'bank_name'        => $myData['bank_name'] ?? '手动转账',
            'account'          => $myData['account'] ?? '',
            'real_name'        => $myData['real_name'] ?? $settlementInfo->real_name,
            'bank_branch_name' => $myData['bank_branch_name'] ?? '',
            'mobile'           => $myData['mobile'] ?? $settlementInfo->mobile,
            'identity_no'      => $myData['identity_no'] ?? $settlementInfo->identity_no,
        ];
    }

    /**
     * @inheritdoc
     * 后台「打款账户信息」展示。覆盖 BaseFund 默认（默认只显示 4 项，看不到支行/身份证）。
     * 按 label/value 顺序展示，空值跳过。
     */
    public function getPayoutDisplay(array $accountData): array
    {
        $rows = [
            ['收款方式/银行', $accountData['bank_name'] ?? ''],
            ['户名',         $accountData['real_name'] ?? ''],
            ['收款账号',     $accountData['account'] ?? ''],
            ['开户支行',     $accountData['bank_branch_name'] ?? ''],
            ['预留手机',     $accountData['mobile'] ?? ''],
            ['身份证号',     $accountData['identity_no'] ?? ''],
        ];
        $list = [];
        foreach ($rows as $row) {
            if ($row[1] !== '' && $row[1] !== null) {
                $list[] = ['label' => $row[0], 'value' => $row[1]];
            }
        }
        return $list;
    }

    /**
     * @inheritdoc
     */
    public function getSupportedChannels(): array
    {
        // 手动打款理论上支持所有线下可以操作的方式
        return ['bank', 'wechat', 'alipay'];
    }

    /**
     * @inheritdoc
     * 执行打款逻辑
     */
    public function payout(array $payoutData): array
    {
        $channel = $payoutData['channel'];

        if (!in_array($channel, $this->getSupportedChannels())) {
            throw new \Exception("手动打款渠道不支持 '{$channel}' 提现方式。");
        }

        try {
            Yii::warning("[ManualFund::payout] 创建手动打款记录, 源单ID: {$payoutData['source_id']}");

            // 1. 创建 PayoutLog
            $payoutLog = new PayoutLog();
            $payoutLog->mall_id = $this->mall_id;
            $payoutLog->user_id = $payoutData['user_id'];
            $payoutLog->source_id = $payoutData['source_id'];
            $payoutLog->source_type = $payoutData['source_type'];
            $payoutLog->fund_class = self::class;
            $payoutLog->channel = $channel;
            $payoutLog->amount = $payoutData['amount'];

            // 状态设置为 PENDING，表示等待线下操作
            $payoutLog->status = PayoutLog::STATUS_PENDING;

            $payoutLog->request_data = Json::encode($payoutData);
            $payoutLog->response_data = Json::encode(['status' => 'PENDING', 'message' => '等待管理员线下处理']);
            $payoutLog->payout_no = 'MANUAL-' . $payoutData['order_no'];

            if (!$payoutLog->save()) {
                throw new \Exception("保存手动打款流水失败: " . json_encode($payoutLog->errors));
            }

            // 2. 返回结果
            // [关键] 返回 'doing' 而不是 'success'
            // 这样 CashPayoutForm 会将订单状态改为 STATUS_TRANSFERRING (打款中)
            // 而不是直接改为 STATUS_COMPLETED (已打款)
            return [
                'status' => 'doing',
                'message' => '已生成打款单，请管理员线下转账',
                'payout_no' => $payoutLog->payout_no
            ];

        } catch (\Exception $e) {
            Yii::error("[ManualFund::payout] 异常: " . $e->getMessage());
            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * @inheritdoc
     */
    public function checkUserEligibility(array $userData): array
    {
        // 检查本地是否有进件数据
        $userId = $userData['user_id'] ?? null;
        if ($userId) {
            $statusInfo = $this->checkOnboardingStatus($userId);
            if ($statusInfo['status'] === UserSettlementInfo::FUND_STATUS_SUCCESS) {
                return ['is_eligible' => true, 'message' => '校验通过', 'result' => $statusInfo];
            }
        }

        // 如果没有进件，但由于是手动打款，也可以配置为允许
        // 但为了统一流程，建议要求填写入库
        return [
            'is_eligible' => false,
            'message' => '请先填写收款信息',
            'require_onboarding' => true
        ];
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
        if ($statusInfo['status'] === UserSettlementInfo::FUND_STATUS_SUCCESS) {
            // 补充回显数据
            $statusInfo['settlement_data'] = $model->getChannelData()[self::class] ?? [];
        }

        return $statusInfo;
    }

    /**
     * @inheritdoc
     */
    public function getDisplay()
    {
        return ['name' => $this->name, 'fund' => self::class];
    }
}