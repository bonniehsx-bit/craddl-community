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

namespace app\plugins\withdraw\models;

use app\models\BaseActiveRecord;
use app\plugins\withdraw\services\WithdrawService;
use app\services\payout\HandlesPayoutResult;
use Yii;

/**
 * 提现单据（记录）
 *
 * 自管状态机，与老 Cash 表无任何关系。
 * 申请时把渠道配置 + 账户资料快照进来（snapshot/payout_info），后续配置变更不影响历史单。
 *
 * 状态流转：
 *   待审核(0) ──审核通过─▶ 待打款(1) ──发起打款─▶ 打款中(2) ──回调成功─▶ 已到账(3)
 *      └──驳回─▶ 已驳回(4)[退资金]              └──回调失败─▶ 打款失败(5) ──退资金─▶ 已退回(6)
 *   免审核渠道：创建即 待打款(1)
 *
 * @property int    $id
 * @property int    $mall_id
 * @property int    $user_id
 * @property string $order_no
 * @property int    $channel_id
 * @property string $fund_class
 * @property string $asset_type
 * @property string $amount
 * @property string $actual_amount
 * @property int    $status
 */
class WithdrawOrder extends BaseActiveRecord implements HandlesPayoutResult
{
    const STATUS_PENDING_AUDIT  = 0; // 待审核
    const STATUS_PENDING_PAYOUT = 1; // 待打款（审核通过 / 免审核）
    const STATUS_PAYING         = 2; // 打款中（已提交网关）
    const STATUS_SUCCESS        = 3; // 已到账
    const STATUS_REJECTED       = 4; // 已驳回（审核不通过，资金已退回）
    const STATUS_FAILED         = 5; // 打款失败
    const STATUS_RETURNED       = 6; // 已退回（打款失败后资金退回）

    public static $statusMap = [
        self::STATUS_PENDING_AUDIT  => '待审核',
        self::STATUS_PENDING_PAYOUT => '待打款',
        self::STATUS_PAYING         => '打款中',
        self::STATUS_SUCCESS        => '已到账',
        self::STATUS_REJECTED       => '已驳回',
        self::STATUS_FAILED         => '打款失败',
        self::STATUS_RETURNED       => '已退回',
    ];

    public static function tableName()
    {
        return '{{%plugin_withdraw_order}}';
    }

    public function getStatusText(): string
    {
        return self::$statusMap[$this->status] ?? '未知';
    }

    /** 是否处于终态（不可再变更） */
    public function isFinal(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUCCESS,
            self::STATUS_REJECTED,
            self::STATUS_RETURNED,
        ], true);
    }

    public function getPayoutInfoList(): array
    {
        if (!is_string($this->payout_info) || $this->payout_info === '') {
            return [];
        }
        $decoded = json_decode($this->payout_info, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * 打款结果统一回流入口（实现 HandlesPayoutResult）。
     * 网关/回调/轮询只需 PayoutResultDispatcher::dispatch(self::class, id, 结果)，
     * 这里把规范化结果落到本插件的状态机（复用原子 markSuccess/markFailed）。
     *
     * @param array $result ['status'=>'success|failed|doing','payout_no'=>?,'message'=>?]
     */
    public static function onPayoutResult(int $sourceId, array $result): void
    {
        $order = self::findOne(['id' => $sourceId, 'is_delete' => 0]);
        if (!$order) {
            return;
        }
        $status = $result['status'] ?? '';
        $service = new WithdrawService();
        try {
            if ($status === 'success') {
                $service->markSuccess($order, (string)($result['payout_no'] ?? ''));
            } elseif ($status === 'failed') {
                // 异步回调/对账失败 → 置「打款失败」态,资金冻结不自动退,由管理员决定重试/退回(与同步一致)
                $service->markPayoutFailed($order, (string)($result['message'] ?? '网关打款失败'));
            }
            // 'doing' / 其它：仍在处理，保持打款中，不动
        } catch (\Throwable $e) {
            // 与后台/其它入口竞争时 transition 抢占失败属预期
            Yii::warning("[withdraw] onPayoutResult {$order->order_no}：" . $e->getMessage(), 'payout');
        }
    }
}
