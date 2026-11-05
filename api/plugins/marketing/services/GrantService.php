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
use app\models\Coupon;
use app\models\User;
use app\plugins\marketing\models\MarketingCampaign;
use app\plugins\marketing\models\MarketingGrantLog;
use app\plugins\marketing\models\MarketingReward;
use app\services\CouponService;
use app\services\IntegralService;
use Yii;
use yii\db\IntegrityException;

/**
 * 营销发放核心:券 / 积分 / 余额
 *
 * 资产一律走平台唯一写入口,插件只决定「发不发、发多少」并记发放锚点:
 *   - 券    CouponService::sendToUser(来源=营销)
 *   - 积分  IntegralService::grant(类型 marketing_reward,带去重键)
 *   - 余额  BalanceLogHelper::add(平台入口本身不去重,靠本插件发放锚点)
 *
 * 去重:每一份奖励先插一行发放流水占位,唯一索引 (campaign_id,user_id,dedup_key,reward_id,seq)
 * 保证同一份奖励只能占位一次;占位成功才发资产,二者同一事务,失败整体回滚。
 * 已占位的奖励项跳过、其余照发(回收后重新达标时,只补发被收回的那几份)。
 * 预算:整份奖励要么全发要么不发,不再出现「礼包发一半」。
 */
class GrantService
{
    const INTEGRAL_TYPE = 'marketing_reward';

    /**
     * 给单个用户按活动发放一份奖励(幂等)。
     *
     * @param int    $campaignId  活动ID
     * @param int    $userId      用户ID
     * @param string $sourceEvent 来源事件(register/order_paid/order_cumulative/claim/batch/...)
     * @param string $dedupKey    去重键(同活动+同用户+同键只发一次)
     * @param int    $tierNo      档位(非阶梯活动为 0)
     * @param int    $refTime     判断活动时段用的时间点(默认当前;累计奖传订单支付时间)
     * @param callable|null $guard 锁内校验(持有活动行锁时调用,返回 false 则不发),用于「每人最多 N 次」这类防并发超发的上限
     * @return int 实际发放的奖励份数(0=跳过)
     */
    public static function grant(int $campaignId, int $userId, string $sourceEvent, string $dedupKey, int $tierNo = 0, int $refTime = 0, ?callable $guard = null): int
    {
        $assetGranted = false;
        $transaction = Yii::$app->db->beginTransaction();
        try {
            /** @var MarketingCampaign $campaign 锁活动行,串行化预算扣减 */
            $campaign = MarketingCampaign::find()
                ->where(['id' => $campaignId, 'is_delete' => 0])
                ->forUpdate()
                ->one();

            if (!$campaign || !$campaign->isGrantable($refTime)) {
                $transaction->commit();
                return 0;
            }

            if ($campaign->isBudgetExhausted()) {
                if ($campaign->status != MarketingCampaign::STATUS_ENDED) {
                    $campaign->status = MarketingCampaign::STATUS_ENDED;
                    $campaign->save(false);
                }
                $transaction->commit();
                return 0;
            }

            // 已占位的奖励项(同活动+用户+去重键)。回收过的行已改名释放占位,不在其中;
            // 已使用、不追回的券仍占位 → 重新达标时只补发被收回的那几份,不重复发券。
            $occupied = MarketingGrantLog::find()
                ->select(['reward_id', 'seq'])
                ->where(['campaign_id' => $campaignId, 'user_id' => $userId, 'dedup_key' => $dedupKey, 'is_delete' => 0])
                ->asArray()->all();
            $occupiedSlots = [];
            foreach ($occupied as $o) {
                if ((int)$o['reward_id'] === 0) {
                    // 7 月旧版流水(未记奖励项):视为整份已发,防止定时补扫给老用户重发
                    $transaction->commit();
                    return 0;
                }
                $occupiedSlots[(int)$o['reward_id'] . ':' . (int)$o['seq']] = true;
            }

            if ($guard !== null && !$guard($campaign)) {
                $transaction->commit();
                return 0;
            }

            // 资金写入口不校验租户,调用方负责:只给本商城有效用户发
            $userOk = User::find()
                ->where(['id' => $userId, 'mall_id' => $campaign->mall_id, 'is_delete' => 0])
                ->exists();
            if (!$userOk) {
                $transaction->commit();
                return 0;
            }

            $items = array_values(array_filter(self::buildItems($campaign, $tierNo), function ($it) use ($occupiedSlots) {
                return !isset($occupiedSlots[$it['reward_id'] . ':' . $it['seq']]);
            }));
            if (!$items) {
                $transaction->commit();
                return 0;
            }

            $couponCount = 0;
            $couponFace = 0.0;
            $integral = 0.0;
            $balance = 0.0;
            foreach ($items as $it) {
                if ($it['type'] === MarketingReward::TYPE_COUPON) {
                    $couponCount++;
                    $couponFace += $it['face'];
                } elseif ($it['type'] === MarketingReward::TYPE_INTEGRAL) {
                    $integral += $it['amount'];
                } else {
                    $balance += $it['amount'];
                }
            }
            if ($campaign->wouldExceedBudget($couponCount, $couponFace, $integral, $balance)) {
                Yii::warning("[营销奖励] 预算不足,跳过 campaign={$campaignId} user={$userId} key={$dedupKey}");
                $transaction->commit();
                return 0;
            }

            $now = time();
            $couponService = new CouponService();
            $integralService = new IntegralService();

            foreach ($items as $it) {
                // 1) 先占位:唯一索引冲突即说明并发已发过,整体回滚
                $log = new MarketingGrantLog();
                $log->mall_id = $campaign->mall_id;
                $log->campaign_id = $campaignId;
                $log->user_id = $userId;
                $log->source_event = $sourceEvent;
                $log->dedup_key = $dedupKey;
                $log->tier_no = $tierNo;
                $log->reward_type = $it['type'];
                $log->reward_id = $it['reward_id'];
                $log->seq = $it['seq'];
                $log->coupon_id = $it['coupon'] ? (int)$it['coupon']->id : 0;
                $log->face_value = $it['face'];
                $log->amount = $it['amount'];
                $log->status = MarketingGrantLog::STATUS_GRANTED;
                $log->granted_at = $now;
                if (!$log->save()) {
                    throw new \Exception('发放流水记录失败: ' . current($log->getFirstErrors()));
                }

                // 2) 再发资产
                if ($it['type'] === MarketingReward::TYPE_COUPON) {
                    $userCoupon = $couponService->sendToUser(
                        $it['coupon'], $userId, false, false, CouponService::SOURCE_MARKETING
                    );
                    $log->user_coupon_id = (int)$userCoupon->id;
                } elseif ($it['type'] === MarketingReward::TYPE_INTEGRAL) {
                    $log->ref_id = $integralService->grant($userId, $it['amount'], self::INTEGRAL_TYPE, [
                        'idempotencyKey' => 'marketing_grant_' . $log->id,
                        'title' => '营销奖励',
                        'content' => '营销活动「' . $campaign->name . '」奖励',
                    ]);
                    $assetGranted = true;
                } else {
                    BalanceLogHelper::add($userId, $it['amount'], '营销活动「' . $campaign->name . '」奖励', 0, 0, '营销奖励');
                    $assetGranted = true;
                }
                $log->save(false);
            }

            $campaign->grant_count += $couponCount;
            $campaign->grant_amount += $couponFace;
            $campaign->grant_integral += $integral;
            $campaign->grant_balance += $balance;
            if ($campaign->isBudgetExhausted()) {
                $campaign->status = MarketingCampaign::STATUS_ENDED;
            }
            $campaign->save(false);

            $transaction->commit();
        } catch (IntegrityException $e) {
            $transaction->rollBack();
            // 正常情况是唯一索引拦下的并发重复发放;带上原始报错,万一是别的完整性问题也能查
            Yii::warning("[营销奖励] 完整性冲突,本次不发 campaign={$campaignId} user={$userId} key={$dedupKey} - " . $e->getMessage());
            return 0;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error("[营销奖励] 发放失败 campaign={$campaignId} user={$userId} key={$dedupKey} - " . $e->getMessage());
            return 0;
        }

        // 积分/余额入口在嵌套事务里清缓存早于外层提交,提交后再清一次,避免读到旧值
        if ($assetGranted) {
            try {
                CacheHelper::remove('user_data:' . $userId);
            } catch (\Throwable $e) {
                Yii::warning('[营销奖励] 清理用户缓存失败: ' . $e->getMessage());
            }
        }
        return count($items);
    }

    /**
     * 展开某档位的奖励项为逐份发放清单。券按张数展开,积分/余额一项一份。
     * 券模板缺失或停用的跳过(不影响其他奖励)。
     */
    private static function buildItems(MarketingCampaign $campaign, int $tierNo): array
    {
        $rewards = MarketingReward::find()
            ->where(['campaign_id' => $campaign->id, 'tier_no' => $tierNo, 'is_delete' => 0])
            ->orderBy('id ASC')
            ->all();

        $items = [];
        foreach ($rewards as $r) {
            /** @var MarketingReward $r */
            if ($r->reward_type === MarketingReward::TYPE_COUPON) {
                $coupon = Coupon::findOne(['id' => $r->coupon_id, 'mall_id' => $campaign->mall_id, 'is_delete' => 0]);
                if (!$coupon || !$coupon->is_enabled) {
                    continue;
                }
                $face = ((int)$coupon->type === 0) ? (float)$coupon->sub_price : 0.0;
                $num = max(1, (int)$r->num);
                for ($i = 0; $i < $num; $i++) {
                    $items[] = ['type' => $r->reward_type, 'reward_id' => (int)$r->id, 'seq' => $i, 'coupon' => $coupon, 'face' => $face, 'amount' => 0.0];
                }
            } elseif (in_array($r->reward_type, [MarketingReward::TYPE_INTEGRAL, MarketingReward::TYPE_BALANCE], true)) {
                $amount = round((float)$r->amount, 2);
                if ($amount <= 0) {
                    continue;
                }
                $items[] = ['type' => $r->reward_type, 'reward_id' => (int)$r->id, 'seq' => 0, 'coupon' => null, 'face' => 0.0, 'amount' => $amount];
            }
        }
        return $items;
    }
}
