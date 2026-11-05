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

namespace app\plugins\distribution\services;

use app\models\CommissionLog;
use app\models\Order;
use app\plugins\distribution\models\DistributionSettlePlan;
use app\plugins\distribution\models\DistributionUser;
use app\services\CommissionService;
use Yii;

/**
 * 分销佣金的「发放」侧：T+N 到期结算 + 后台手动结算。
 *
 * 与 dealer 的 DealerSettleService 同构。分销没有欠款模型，
 * 所以这里不做扣款，只负责「到点/人工点了才发」以及发钱前的资格校验。
 */
class DistributionSettleService
{
    /**
     * T+N 到期结算：发放本商城已到期的待发放计划。
     * @return array [已发放, 已作废]
     */
    public function settleDue(int $mallId, int $limit = 500): array
    {
        $now   = time();
        $plans = DistributionSettlePlan::find()
            ->where([
                'mall_id'   => $mallId,
                'status'    => DistributionSettlePlan::STATUS_PENDING,
                'is_delete' => 0,
            ])
            ->andWhere(['<=', 'due_at', $now])
            ->orderBy(['due_at' => SORT_ASC])
            ->limit($limit)
            ->all();

        $settled = 0;
        $invalid = 0;
        $service = new CommissionService();

        foreach ($plans as $plan) {
            $log = CommissionLog::findOne($plan->commission_log_id);

            // 流水已不存在 / 已被回滚作废 → 计划一并作废，别再尝试发钱
            if (!$log || $log->is_delete || $log->status == CommissionLog::STATUS_INVALID) {
                $this->closePlan($plan, DistributionSettlePlan::STATUS_INVALID, '分润流水已作废或不存在');
                $invalid++;
                continue;
            }
            // 已经由别的链路发过：计划标记完成即可，不能重复发
            if ($log->status == CommissionLog::STATUS_SETTLED) {
                $this->closePlan($plan, DistributionSettlePlan::STATUS_SETTLED, '流水已由其它链路结算');
                continue;
            }

            $reason = '';
            $wait = false;
            if (!$this->canSettle($log, $reason, $wait)) {
                if ($wait) {
                    continue; // 这件还在售后处理中:计划保持待发放,下一轮再看(被拒 / 撤销后照常发,退款成功会被回滚作废)
                }
                try {
                    $service->invalid($log);
                } catch (\Throwable $e) {
                    Yii::error('[分销] T+N 作废分润失败 log:' . $log->id . ' ' . $e->getMessage());
                }
                $this->closePlan($plan, DistributionSettlePlan::STATUS_INVALID, $reason);
                $invalid++;
                continue;
            }

            $transaction = Yii::$app->db->beginTransaction();
            try {
                $service->settle($log);
                $this->closePlan($plan, DistributionSettlePlan::STATUS_SETTLED, '');
                $transaction->commit();
                $settled++;
            } catch (\Throwable $e) {
                $transaction->rollBack();
                // 计划保持待发放，下一轮 cron 重试
                Yii::error('[分销] T+N 到期结算失败 log:' . $log->id . ' ' . $e->getMessage());
            }
        }

        return [$settled, $invalid];
    }

    /**
     * 后台手动结算单条分润（结算方式=手动 时使用）。走与到期结算相同的资格校验。
     * @return array [成功?, 说明]
     */
    public function settleOne(int $logId): array
    {
        $log = CommissionLog::findOne($logId);
        if (!$log || $log->is_delete) {
            return [false, '分润记录不存在'];
        }
        if ($log->status == CommissionLog::STATUS_SETTLED) {
            return [false, '该记录已结算'];
        }
        if ($log->status == CommissionLog::STATUS_INVALID) {
            return [false, '该记录已失效'];
        }
        $reason = '';
        if (!$this->canSettle($log, $reason)) {
            return [false, $reason ?: '不满足结算条件'];
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            (new CommissionService())->settle($log);
            DistributionSettlePlan::updateAll(
                ['status' => DistributionSettlePlan::STATUS_SETTLED, 'settled_at' => time(), 'updated_at' => time()],
                ['commission_log_id' => $log->id, 'status' => DistributionSettlePlan::STATUS_PENDING]
            );
            $transaction->commit();
            return [true, '结算成功'];
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error('[分销] 手动结算失败 log:' . $logId . ' ' . $e->getMessage());
            return [false, '结算失败：' . $e->getMessage()];
        }
    }

    /**
     * 后台手动作废单条待结算分润
     * @return array [成功?, 说明]
     */
    public function invalidOne(int $logId): array
    {
        $log = CommissionLog::findOne($logId);
        if (!$log || $log->is_delete) {
            return [false, '分润记录不存在'];
        }
        if ($log->status == CommissionLog::STATUS_SETTLED) {
            // 已发出去的钱不能靠「作废」收回，必须走退款回滚链路，否则账实不符
            return [false, '已结算的记录不能作废，请通过订单退款回滚'];
        }
        if ($log->status == CommissionLog::STATUS_INVALID) {
            return [false, '该记录已失效'];
        }

        try {
            (new CommissionService())->invalid($log);
            DistributionSettlePlan::invalidByLogIds([(int) $log->id]);
            return [true, '已作废'];
        } catch (\Throwable $e) {
            Yii::error('[分销] 手动作废失败 log:' . $logId . ' ' . $e->getMessage());
            return [false, '作废失败：' . $e->getMessage()];
        }
    }

    /**
     * 发钱前的资格校验：订单关闭、这件已退款、分销资格被取消都不发(退款按件判断,口径见 CommissionService::payoutRefundState,
     * 与 DistributionListener 结算时一致:部分退款只作废退掉的那件)。
     *
     * @param bool $wait 输出:true = 这件还在售后处理中,先别发也别作废,售后有结论后再结算
     */
    public function canSettle(CommissionLog $log, &$reason = '', &$wait = false): bool
    {
        $reason = '';
        $wait = false;

        $order = $log->order_id > 0 ? Order::findOne($log->order_id) : null;
        $state = CommissionService::payoutRefundState($order, (int) $log->order_detail_id);
        if ($state === CommissionService::PAYOUT_PROCESSING) {
            $reason = '这件商品售后处理中,处理完再结算';
            $wait = true;
            return false;
        }
        if ($state === CommissionService::PAYOUT_REFUNDED) {
            $reason = '订单已关闭或这件商品已退款';
            return false;
        }

        $disabled = DistributionUser::find()
            ->where(['mall_id' => (int) $log->mall_id, 'user_id' => (int) $log->user_id, 'is_disabled' => 1])
            ->exists();
        if ($disabled) {
            $reason = '分销资格已取消';
            return false;
        }

        return true;
    }

    private function closePlan(DistributionSettlePlan $plan, int $status, string $remark): void
    {
        $plan->status = $status;
        if ($status === DistributionSettlePlan::STATUS_SETTLED) {
            $plan->settled_at = time();
        }
        if ($remark !== '') {
            $plan->remark = mb_substr($remark, 0, 200);
        }
        $plan->save(false);
    }
}
