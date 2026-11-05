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

namespace app\plugins\marketing\services;

use app\helpers\BalanceLogHelper;
use app\helpers\CacheHelper;
use app\models\UserCoupon;
use app\plugins\marketing\models\MarketingCampaign;
use app\plugins\marketing\models\MarketingGrantLog;
use app\plugins\marketing\models\MarketingReward;
use app\services\IntegralService;
use Yii;
use yii\db\Expression;

/**
 * 营销奖励回收(订单售后导致不再达标时)
 *
 * 口径(已定):
 *   - 积分:全额扣回,不足扣成负数;商城开了积分有效期时,按平台撤销口径只收回该批仍存活的部分(防过期+售后双扣)
 *   - 余额:全额扣回,不足扣成负数
 *   - 券:未使用的作废;已使用/已过期的不追,流水保持「已发放」
 *
 * 并发/重入:以发放流水 status 的条件更新(已发放→已回收)作锚点,与扣回资产同一事务;
 * 同时给 dedup_key 追加 #r{id} 释放占位,用户之后重新达标可再次获得该份奖励。
 */
class RewardRevertService
{
    /**
     * @return bool 是否实际回收
     */
    public static function revert(MarketingGrantLog $row, string $reason): bool
    {
        if ((int)$row->status !== MarketingGrantLog::STATUS_GRANTED) {
            return false;
        }

        $tx = Yii::$app->db->beginTransaction();
        try {
            if ($row->reward_type === MarketingReward::TYPE_COUPON) {
                $voided = UserCoupon::updateAll(
                    ['is_delete' => 1],
                    ['id' => (int)$row->user_coupon_id, 'status' => UserCoupon::STATUS_UNUSE, 'is_delete' => 0]
                );
                if ($voided <= 0) {
                    $tx->rollBack(); // 已用/已过期/已作废:不追
                    return false;
                }
            }

            $affected = MarketingGrantLog::updateAll(
                [
                    'status' => MarketingGrantLog::STATUS_REVERTED,
                    'reverted_at' => time(),
                    'dedup_key' => new Expression("CONCAT(dedup_key, '#r', id)"),
                ],
                ['id' => (int)$row->id, 'status' => MarketingGrantLog::STATUS_GRANTED]
            );
            if ($affected <= 0) {
                $tx->rollBack();
                return false;
            }

            $amount = (float)$row->amount;
            $counters = [];
            if ($row->reward_type === MarketingReward::TYPE_INTEGRAL) {
                if ((int)$row->ref_id > 0) {
                    // 走平台撤销口径:未开有效期 → 全额扣回、允许负数;开了有效期 → 只收回该批仍存活的部分,
                    // 避免「过期已回收 + 售后再全额扣」对同一批积分扣两次(平台 2026-07-25 定案)
                    (new IntegralService())->revert((int)$row->ref_id);
                } else {
                    (new IntegralService())->consume((int)$row->user_id, $amount, GrantService::INTEGRAL_TYPE, [
                        'idempotencyKey' => 'marketing_revert_' . $row->id,
                        'title' => '营销奖励回收',
                        'content' => $reason,
                    ], true);
                }
                $counters['grant_integral'] = -$amount;
            } elseif ($row->reward_type === MarketingReward::TYPE_BALANCE) {
                BalanceLogHelper::sub((int)$row->user_id, $amount, $reason, 0, 0, '营销奖励回收', true);
                $counters['grant_balance'] = -$amount;
            } else {
                $counters['grant_count'] = -1;
                $counters['grant_amount'] = -(float)$row->face_value;
            }
            MarketingCampaign::updateAllCounters($counters, ['id' => (int)$row->campaign_id]);

            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            Yii::error("[营销奖励] 回收失败 log#{$row->id} - " . $e->getMessage());
            return false;
        }

        if ($row->reward_type !== MarketingReward::TYPE_COUPON) {
            try {
                CacheHelper::remove('user_data:' . $row->user_id);
            } catch (\Throwable $e) {
                Yii::warning('[营销奖励] 清理用户缓存失败: ' . $e->getMessage());
            }
        }
        Yii::warning("[营销奖励] 已回收 log#{$row->id} type={$row->reward_type} user={$row->user_id}: {$reason}");
        return true;
    }
}
