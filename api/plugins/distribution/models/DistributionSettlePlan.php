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

namespace app\plugins\distribution\models;

use app\models\BaseActiveRecord;
use app\models\CommissionLog;
use Yii;

/**
 * 分销佣金 T+N 延迟结算计划。
 *
 * 一条 CommissionLog 至多对应一条计划；到期由 DistributionSettleDueTask 发放。
 *
 * @property int    $id
 * @property int    $mall_id
 * @property int    $commission_log_id
 * @property int    $order_id
 * @property int    $user_id
 * @property int    $due_at
 * @property int    $status
 * @property int    $settled_at
 * @property string $remark
 */
class DistributionSettlePlan extends BaseActiveRecord
{
    const STATUS_PENDING = 0; // 待发放
    const STATUS_SETTLED = 1; // 已发放
    const STATUS_INVALID = 2; // 已作废（对应分润被退款回滚）

    public static function tableName()
    {
        return '{{%plugin_distribution_settle_plan}}';
    }

    public function rules()
    {
        return [
            [['mall_id', 'commission_log_id'], 'required'],
            [['mall_id', 'commission_log_id', 'order_id', 'user_id', 'due_at', 'status', 'settled_at', 'is_delete'], 'integer'],
            [['remark'], 'string', 'max' => 255],
        ];
    }

    /**
     * 为一条分润流水登记延迟发放计划（幂等：同一 log 重复登记直接返回已有计划）
     */
    public static function plan(CommissionLog $log, int $delayDays): ?self
    {
        if ($delayDays <= 0) {
            return null;
        }
        $exist = self::findOne(['commission_log_id' => $log->id]);
        if ($exist) {
            return $exist;
        }

        $plan = new self([
            'mall_id'           => (int) $log->mall_id,
            'commission_log_id' => (int) $log->id,
            'order_id'          => (int) $log->order_id,
            'user_id'           => (int) $log->user_id,
            'due_at'            => time() + $delayDays * 86400,
            'status'            => self::STATUS_PENDING,
        ]);
        if (!$plan->save()) {
            Yii::error('[分销] 延迟结算计划写入失败 log:' . $log->id . ' '
                . json_encode($plan->getErrors(), JSON_UNESCAPED_UNICODE));
            return null;
        }
        return $plan;
    }

    /**
     * 这批流水里「还挂着待发放计划」的集合(key=commission_log_id)。
     * 结算链路据此跳过它们 —— 交给到期 cron 发，避免两条路都发一次。
     * @return array<int,int>
     */
    public static function pendingLogIdSet(array $logIds): array
    {
        if (empty($logIds)) {
            return [];
        }
        $ids = self::find()
            ->where(['commission_log_id' => $logIds, 'status' => self::STATUS_PENDING, 'is_delete' => 0])
            ->select('commission_log_id')
            ->column();
        return array_flip(array_map('intval', $ids));
    }

    /**
     * 分润被回滚时同步作废计划，避免 cron 到期还把这笔已回滚的佣金发出去
     */
    public static function invalidByLogIds(array $logIds): void
    {
        if (empty($logIds)) {
            return;
        }
        self::updateAll(
            ['status' => self::STATUS_INVALID, 'updated_at' => time()],
            ['commission_log_id' => $logIds, 'status' => self::STATUS_PENDING]
        );
    }
}
