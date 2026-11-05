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

use app\helpers\BalanceLogHelper;
use app\helpers\OptionHelper;
use app\models\PayoutLog;
use Yii;
use yii\helpers\Json;

/**
 * 余额打款渠道
 * [重构版] - 将提现金额转入用户余额。
 */
class BalanceFund extends BaseFund
{
    /** @var array 从数据库加载的配置 */
    private $config;

    public function __construct($mall_id)
    {
        $this->mall_id = $mall_id;
        $fundSettings = OptionHelper::get('funds', $mall_id);
        // 余额渠道总是可用，即使没有显式配置
        $this->config = $fundSettings[self::class] ?? ['name' => '余额', 'status' => 1];
        if (empty($this->config['status'])) {
            throw new \InvalidArgumentException('余额打款通道未开启');
        }
        $this->name = $this->config['name'];
        $this->fund_type = self::class;
    }

    // ======================= [核心重构] 开始 =======================

    /**
     * @inheritdoc
     */
    public function getSupportedChannels(): array
    {
        // 这个渠道只支持 "打款" 到余额
        return ['balance', 'bank', 'wechat', 'alipay'];
    }

    /**
     * @inheritdoc
     */
    public function payout(array $payoutData): array
    {
        $channel = $payoutData['channel'];

        // 1. 渠道自我决策
        if (!in_array($channel, $this->getSupportedChannels())) {
            throw new \Exception("余额渠道不支持 '{$channel}' 提现方式。");
        }

        try {
            // 2. 执行内部转账核心逻辑
            // 资产名走词库(price→佣金/integral→积分/balance→余额),继承商户品牌词
            $typeKeyMap = ['price' => 'commission', 'integral' => 'integral', 'balance' => 'balance'];
            $cash = $payoutData['cash_model'] ?? null; // 从上下文获取提现模型
            $typeKey = ($cash && isset($typeKeyMap[$cash->type])) ? $typeKeyMap[$cash->type] : null;
            $typeName = $typeKey ? \app\helpers\NounHelper::parse($typeKey, $this->mall_id) : '资产';

            // 流水标题/明细内容走可配文案模板({type}/{order_no}/{amount} 数据变量 + {withdraw}/{balance} 词库联动)
            $vars = [
                'type'     => $typeName,
                'order_no' => $payoutData['order_no'],
                'amount'   => $payoutData['amount'],
            ];
            $title = \app\helpers\NounHelper::render(\app\helpers\NounHelper::parse('balance_payout_title', $this->mall_id), $vars, $this->mall_id);
            $content = \app\helpers\NounHelper::render(\app\helpers\NounHelper::parse('balance_payout_content', $this->mall_id), $vars, $this->mall_id);

            // 增加用户余额并记录余额日志
            BalanceLogHelper::add($payoutData['user_id'], $payoutData['amount'], $content, 0, 0, $title);

            // 3. 记录 PayoutLog 以保持所有渠道流水一致
            $payoutLog = new PayoutLog();
            $payoutLog->mall_id = $this->mall_id;
            $payoutLog->user_id = $payoutData['user_id'];
            $payoutLog->source_id = $payoutData['source_id'];
            $payoutLog->source_type = $payoutData['source_type'];
            $payoutLog->fund_class = self::class;
            $payoutLog->channel = 'balance';
            $payoutLog->amount = $payoutData['amount'];
            $payoutLog->status = PayoutLog::STATUS_SUCCESS; // 余额到账总是即时成功
            $payoutLog->request_data = Json::encode($payoutData);
            $payoutLog->response_data = Json::encode(['status' => 'SUCCESS', 'message' => '内部转账成功']);
            $payoutLog->payout_no = 'BALANCE-' . $payoutData['order_no'];
            $payoutLog->completed_at = time();

            if (!$payoutLog->save()) {
                // 如果日志保存失败，需要抛出异常，以便上层业务进行事务回滚
                throw new \Exception("保存余额打款流水失败: " . json_encode($payoutLog->errors));
            }

            // 4. 返回成功结果
            return [
                'status' => 'success',
                'message' => '提现金额已成功转入账户余额',
                'payout_no' => $payoutLog->payout_no
            ];

        } catch (\Exception $e) {
            Yii::error("[BalanceFund::payout] 余额打款异常: " . $e->getMessage());
            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    // ======================= [核心重构] 结束 =======================


    /**
     * @inheritdoc
     */
    public function checkUserEligibility(array $userData): array
    {
        // 余额渠道对所有用户都可用，无需特殊资格
        return [
            'is_eligible' => true,
            'message' => '余额通道无需资格校验',
            'require_onboarding' => false
        ];
    }

    /**
     * @inheritdoc
     */
    public function getDisplay()
    {
        return ['name' => $this->name, 'fund' => self::class];
    }
}