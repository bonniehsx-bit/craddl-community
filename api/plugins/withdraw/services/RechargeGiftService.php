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

namespace app\plugins\withdraw\services;

use app\models\Coupon;
use app\models\UserCoupon;
use app\plugins\withdraw\models\RechargeOrder;
use app\plugins\withdraw\models\RechargeOrderGift;
use app\plugins\withdraw\models\RechargePlan;
use app\plugins\withdraw\models\RechargePlanGift;
use app\services\CouponService;
use Yii;

/**
 * 充值分期赠送 —— 展开计划、按期发放、退款收回。
 *
 * 三个阶段:
 *   1. 下单   expandForOrder()  把档位的分期配置**快照**成这一单的发放计划,时间算死
 *   2. 到期   grantDue()        定时任务扫到期未发的,逐条发;首期(immediate)在充值到账时直接发
 *   3. 退款   revokeForOrder()  未发的作废、已发未用的收回
 */
class RechargeGiftService
{
    /** 单条最多重试几次,超过就停手等人工 —— 券被删/被下架的话再试一万次也没用 */
    const MAX_ATTEMPTS = 5;

    /** 读某档位的分期配置(后台回显) */
    public static function configOf(int $planId): array
    {
        $rows = RechargePlanGift::find()
            ->where(['plan_id' => $planId, 'is_delete' => 0])
            ->orderBy(['period' => SORT_ASC, 'sort' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'          => (int) $r->id,
                'period'      => (int) $r->period,
                'gift_type'   => (string) $r->gift_type,
                'coupon_id'   => (int) $r->coupon_id,
                'num'         => (int) $r->num,
                'asset_type'  => (string) $r->asset_type,
                'amount'      => (float) $r->amount,
                'delay_mode'  => (string) $r->delay_mode,
                'delay_value' => (int) $r->delay_value,
            ];
        }
        return $out;
    }

    /**
     * 给 C 端的分期预览 —— 只是"会送什么、什么时候到",不涉及具体日期
     * (具体日期要等充值成功才算得出来,锚点是充值日)。
     */
    public static function previewOf(int $planId, string $assetLabelKey = ''): array
    {
        $rows = RechargePlanGift::find()
            ->where(['plan_id' => $planId, 'is_delete' => 0])
            ->orderBy(['period' => SORT_ASC, 'sort' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
        if (!$rows) {
            return [];
        }

        // 券名一次查完,别在循环里逐个查
        $couponIds = array_values(array_filter(array_map(fn($r) => (int) $r->coupon_id, $rows)));
        $names = $couponIds
            ? Coupon::find()->select(['name', 'id'])->where(['id' => $couponIds])->indexBy('id')->column()
            : [];

        $out = [];
        foreach ($rows as $r) {
            $isCoupon = $r->gift_type === RechargePlanGift::TYPE_COUPON;
            $out[] = [
                'period'  => (int) $r->period,
                'type'    => (string) $r->gift_type,
                // 「10元券×2」或「20」—— C 端直接显示,不用自己拼
                'content' => $isCoupon
                    ? (($names[(int) $r->coupon_id] ?? '优惠券') . '×' . max(1, (int) $r->num))
                    : rtrim(rtrim((string) $r->amount, '0'), '.'),
                'unit'    => $isCoupon ? '' : AssetService::label($assetLabelKey),
                'timing'  => $r->timingText(),
                'is_now'  => $r->delay_mode === RechargePlanGift::MODE_IMMEDIATE,
            ];
        }
        return $out;
    }

    /**
     * 保存某档位的分期配置。整表替换(本次没提交的软删)。
     *
     * ⛔ 校验不通过直接抛 —— 调用方在商品保存的事务里,会整体回滚。
     *    半个配置(比如第 2 期指向一张已删的券)比没配置危险得多:
     *    用户充了钱,到期那天发不出来,而当时没有任何人会发现。
     */
    public static function saveConfig(int $mallId, int $planId, array $gifts, string $planTitle = ''): void
    {
        $keep = [];
        $period = 0;
        foreach ($gifts as $item) {
            if (!is_array($item)) {
                continue;
            }
            $type = ($item['gift_type'] ?? 'coupon') === RechargePlanGift::TYPE_ASSET
                ? RechargePlanGift::TYPE_ASSET : RechargePlanGift::TYPE_COUPON;

            // 空行丢弃:券没选、或赠金填 0,都当没配
            if ($type === RechargePlanGift::TYPE_COUPON && (int) ($item['coupon_id'] ?? 0) <= 0) {
                continue;
            }
            if ($type === RechargePlanGift::TYPE_ASSET && round((float) ($item['amount'] ?? 0), 2) <= 0) {
                continue;
            }
            $period++;

            if ($type === RechargePlanGift::TYPE_COUPON) {
                self::assertCouponUsable($mallId, (int) $item['coupon_id'], $planTitle, $period,
                    (string) ($item['delay_mode'] ?? 'immediate'));
            }

            $id = (int) ($item['id'] ?? 0);
            $row = $id > 0
                ? RechargePlanGift::findOne(['id' => $id, 'plan_id' => $planId, 'is_delete' => 0])
                : null;
            if (!$row) {
                $row = new RechargePlanGift();
                $row->mall_id = $mallId;
                $row->plan_id = $planId;
            }
            $row->period      = $period;                       // 期次按提交顺序重排,运营拖动排序即生效
            $row->gift_type   = $type;
            $row->coupon_id   = (int) ($item['coupon_id'] ?? 0);
            $row->num         = max(1, (int) ($item['num'] ?? 1));
            $row->asset_type  = (string) ($item['asset_type'] ?? '');
            $row->amount      = round((float) ($item['amount'] ?? 0), 2);
            $row->delay_mode  = (string) ($item['delay_mode'] ?? RechargePlanGift::MODE_IMMEDIATE);
            $row->delay_value = max(0, (int) ($item['delay_value'] ?? 0));
            $row->sort        = $period;
            if (!$row->save()) {
                throw new \Exception("档位「{$planTitle}」第{$period}期赠品保存失败:"
                    . json_encode($row->errors, JSON_UNESCAPED_UNICODE));
            }
            $keep[] = (int) $row->id;
        }

        // 本次没提交的软删。不物理删:已经展开到订单里的期次仍按快照发放,
        // 但配置侧要看得出"这一期被运营去掉了"
        $q = ['plan_id' => $planId, 'is_delete' => 0];
        $cond = $keep ? ['and', $q, ['not in', 'id', $keep]] : $q;
        RechargePlanGift::updateAll(['is_delete' => 1, 'deleted_at' => time()], $cond);
    }

    /**
     * 券可用性校验 —— 分期送最容易踩的坑就在这。
     *
     * 「固定日期范围」的券配到延后的期次上,到期发出去时券早过期了,
     * 用户点开是灰的,而系统**不会报任何错**(sendToUser 只是照抄 start_at/end_at)。
     * 所以延后期次必须用「领取后 N 天有效」的券。
     */
    private static function assertCouponUsable(int $mallId, int $couponId, string $planTitle, int $period, string $delayMode): void
    {
        $c = Coupon::findOne(['id' => $couponId, 'mall_id' => $mallId, 'is_delete' => 0]);
        if (!$c) {
            throw new \Exception("档位「{$planTitle}」第{$period}期选的优惠券不存在(可能已被删除),请重新选择");
        }
        if (!$c->is_enabled) {
            throw new \Exception("档位「{$planTitle}」第{$period}期选的优惠券「{$c->name}」已停用,启用后再保存");
        }
        // 立返那期当场就发,固定日期的券没问题;延后的期次才有过期风险
        if ($delayMode !== RechargePlanGift::MODE_IMMEDIATE && (int) $c->time_type === 1) {
            $end = $c->end_at ? date('Y-m-d', (int) $c->end_at) : '固定截止日';
            throw new \Exception(
                "档位「{$planTitle}」第{$period}期选的优惠券「{$c->name}」是**固定有效期**的(到 {$end} 截止)。"
                . "延后发放的期次必须用「领取后 N 天有效」的券 —— 否则等到该发的那天券早过期了,"
                . "用户收到的是一张点开就是灰的废券,而且系统不会报错。"
            );
        }
    }

    /**
     * 计算某一期的发放时间。**锚点一律是充值日**。
     *
     * ⚠ 按月推算有个坑:PHP 的 strtotime('+1 month') 对 1月31日 得到的是 **3月3日**
     *   (2月没有31号,自动溢出到下个月)。用户 1月31日 充值,第二期跳到 3月3日,
     *   而界面上写的是"1个月后到账" —— 会被投诉。
     *   这里检测到"日"变了就回退到目标月的最后一天(1月31 +1月 → 2月28/29)。
     */
    public static function calcGrantAt(int $baseTs, string $mode, int $value): int
    {
        switch ($mode) {
            case 'immediate':
                return $baseTs;

            case 'day':
                return $baseTs + $value * 86400;

            case 'week':
                return $baseTs + $value * 7 * 86400;

            case 'month':
                $day = (int) date('j', $baseTs);
                $ts  = strtotime("+{$value} month", $baseTs);
                // 溢出了(比如 1/31 +1月 变成 3/3):回退到目标月最后一天
                if ((int) date('j', $ts) !== $day) {
                    $ts = strtotime('last day of ' . $value . ' month', $baseTs);
                    // 保留原来的时分秒,只把日期挪到月末
                    $ts = strtotime(date('Y-m-d', $ts) . ' ' . date('H:i:s', $baseTs));
                }
                return $ts;

            default:
                // 认不出的模式当立即发 —— 宁可早给,不能因为配置写错就永远不发
                Yii::warning("[RechargeGift] 未知的到账模式 {$mode},按立即发放处理");
                return $baseTs;
        }
    }

    /**
     * 下单时把档位的分期配置展开成这一单的发放计划(快照)。
     *
     * @param int $baseTs 计时起点。传充值**支付成功**的时间 —— 用下单时间的话,
     *                    用户放着不付、三天后才付,第二期会比第一期还早到
     */
    public static function expandForOrder(RechargeOrder $ro, int $planId, int $baseTs): int
    {
        $rows = RechargePlanGift::find()
            ->where(['plan_id' => $planId, 'is_delete' => 0])
            ->orderBy(['period' => SORT_ASC, 'sort' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
        if (!$rows) {
            return 0;
        }

        $n = 0;
        foreach ($rows as $r) {
            // 已经展开过就跳过 —— 补结算/重试可能把这里跑第二遍
            $exists = RechargeOrderGift::find()
                ->where(['recharge_order_id' => $ro->id, 'period' => (int) $r->period, 'is_delete' => 0])
                ->exists();
            if ($exists) {
                continue;
            }

            $g = new RechargeOrderGift();
            $g->mall_id           = (int) $ro->mall_id;
            $g->recharge_order_id = (int) $ro->id;
            $g->user_id           = (int) $ro->user_id;
            $g->period            = (int) $r->period;
            $g->gift_type         = (string) $r->gift_type;
            $g->coupon_id         = (int) $r->coupon_id;
            $g->num               = max(1, (int) $r->num);
            // 券名也快照:券模板被删之后,C 端「我的充值」还要显示得出送了什么
            $g->coupon_name       = $r->gift_type === RechargePlanGift::TYPE_COUPON
                ? (string) (Coupon::find()->select('name')->where(['id' => (int) $r->coupon_id])->scalar() ?: '')
                : '';
            // 赠金没单独配钱包时,跟充值单到账的是同一个钱包
            $g->asset_type        = (string) ($r->asset_type ?: $ro->asset_type);
            $g->amount            = (string) $r->amount;
            $g->plan_grant_at     = self::calcGrantAt($baseTs, (string) $r->delay_mode, (int) $r->delay_value);
            $g->status            = RechargeOrderGift::STATUS_PENDING;
            if (!$g->save()) {
                throw new \Exception('展开分期赠品失败: ' . json_encode($g->errors, JSON_UNESCAPED_UNICODE));
            }
            $n++;
        }
        return $n;
    }

    /**
     * 发放到期的赠品。返回本次成功发放的条数。
     *
     * @param int $limit 单次最多处理多少条(定时任务是 fpm 模式,受 30s 硬超时)
     * @param int|null $orderId 只处理某个充值单(充值到账时立即发首期用)
     */
    public static function grantDue(int $mallId, int $limit = 100, ?int $orderId = null): int
    {
        // ⛔ 待发放**和失败的**都要捞。只捞 PENDING 的话,一次瞬时失败
        //   (券被临时停用、库存瞬时耗尽、DB 抖动)就把这一期永久钉死在 FAILED,
        //   MAX_ATTEMPTS 和 attempts 计数就成了纯装饰 —— 用户的赠品无声丢失。
        //   attempts 到顶才真的停手,那时 fail_reason 已经留痕,等人工处理。
        $q = RechargeOrderGift::find()
            ->where([
                'mall_id'   => $mallId,
                'status'    => [RechargeOrderGift::STATUS_PENDING, RechargeOrderGift::STATUS_FAILED],
                'is_delete' => 0,
            ])
            ->andWhere(['<=', 'plan_grant_at', time()])
            ->andWhere(['<', 'attempts', self::MAX_ATTEMPTS]);
        if ($orderId !== null) {
            $q->andWhere(['recharge_order_id' => $orderId]);
        }
        $list = $q->orderBy(['plan_grant_at' => SORT_ASC, 'id' => SORT_ASC])->limit($limit)->all();

        $ok = 0;
        foreach ($list as $g) {
            if (self::grantOne($g)) {
                $ok++;
            }
        }
        return $ok;
    }

    /**
     * 发一条。幂等:靠 UPDATE ... WHERE status=0 抢占。
     *
     * ⚠ 抢锁和发放必须在同一个事务里 —— 分开做的话中间有个致命窗口:
     *   锁已提交、发券时进程被杀 → 记录显示"已发放"但用户没收到,
     *   而重试又因为状态不是 0 被跳过,这份赠品就永远丢了。
     *   (与 RechargeService::credit 同一套路,那套已在线上验证过)
     */
    public static function grantOne(RechargeOrderGift $g): bool
    {
        $tx = Yii::$app->db->beginTransaction();
        try {
            // 抢占也要允许从 FAILED 抢 —— 否则上面把失败的捞出来了,这里又抢不到,等于没修。
            // 幂等性不受影响:GRANTED 和 VOID 都不在允许集合里,已发/已作废的抢不动
            $affected = RechargeOrderGift::updateAll(
                ['status' => RechargeOrderGift::STATUS_GRANTED, 'granted_at' => time()],
                [
                    'id'     => $g->id,
                    'status' => [RechargeOrderGift::STATUS_PENDING, RechargeOrderGift::STATUS_FAILED],
                ]
            );
            if ($affected <= 0) {
                $tx->rollBack();
                return false;   // 已被别的进程发过
            }

            if ($g->gift_type === RechargePlanGift::TYPE_ASSET) {
                self::grantAsset($g);
            } else {
                self::grantCoupon($g);
            }

            $tx->commit();
            return true;
        } catch (\Throwable $e) {
            $tx->rollBack();
            // 失败要留痕:券被删/下架/领完这些靠重试解决不了,得让运营看见
            RechargeOrderGift::updateAll(
                [
                    'status'      => RechargeOrderGift::STATUS_FAILED,
                    'fail_reason' => mb_substr($e->getMessage(), 0, 250),
                ],
                ['id' => $g->id]
            );
            RechargeOrderGift::updateAllCounters(['attempts' => 1], ['id' => $g->id]);
            Yii::error("[RechargeGift] 第{$g->period}期发放失败 gift={$g->id} user={$g->user_id}: " . $e->getMessage());
            return false;
        }
    }

    /** 发券。多张就发多张,记下 user_coupon id 供退款收回 */
    private static function grantCoupon(RechargeOrderGift $g): void
    {
        $coupon = Coupon::findOne(['id' => (int) $g->coupon_id, 'mall_id' => (int) $g->mall_id, 'is_delete' => 0]);
        if (!$coupon) {
            throw new \Exception('赠送的优惠券不存在或已删除');
        }
        if (!$coupon->is_enabled) {
            throw new \Exception('赠送的优惠券已停用');
        }

        $svc = new CouponService();
        $ids = [];
        for ($i = 0; $i < max(1, (int) $g->num); $i++) {
            // 保留总库存校验(不能超发),但跳过"个人限领" ——
            // 这是充值赠品不是主动领取,被 limit_per_user 卡住就成了"充了钱不给券"
            $uc = $svc->sendToUser($coupon, (int) $g->user_id, true, false, CouponService::SOURCE_UNKNOWN);
            $ids[] = (int) $uc->id;
        }
        RechargeOrderGift::updateAll(['user_coupon_ids' => implode(',', $ids)], ['id' => $g->id]);
    }

    /** 发赠金 */
    private static function grantAsset(RechargeOrderGift $g): void
    {
        if (bccomp((string) $g->amount, '0', 2) <= 0) {
            return;
        }
        AssetService::credit(
            (int) $g->user_id,
            (string) $g->asset_type,
            (string) $g->amount,
            "充值赠送第{$g->period}期到账 {$g->amount}",
            '充值赠送',
            // 幂等键带上这一期的主键,重复调用不会重复发
            'withdraw_recharge_gift_' . $g->id,
            RechargeService::INTEGRAL_TYPE
        );
    }

    /**
     * 充值退款:未发的作废,已发未用的收回。
     *
     * 口径(2026-08-23 定案):
     *   · 未发放 → 直接作废,一分钱不出
     *   · 已发放但用户还没用的券 → 作废收回
     *   · 已经用掉的券 / 已入账的赠金 → 不追。追回要做"券折现"的金额换算,
     *     客诉成本远高于那点损失
     *
     * @return array{voided:int, revoked:int} 作废期数 / 收回券数
     */
    public static function revokeForOrder(int $rechargeOrderId): array
    {
        $voided = RechargeOrderGift::updateAll(
            ['status' => RechargeOrderGift::STATUS_VOID, 'revoked_at' => time()],
            [
                'recharge_order_id' => $rechargeOrderId,
                'status'            => [RechargeOrderGift::STATUS_PENDING, RechargeOrderGift::STATUS_FAILED],
                'is_delete'         => 0,
            ]
        );

        $revoked = 0;
        $granted = RechargeOrderGift::find()
            ->where([
                'recharge_order_id' => $rechargeOrderId,
                'status'            => RechargeOrderGift::STATUS_GRANTED,
                'is_delete'         => 0,
            ])
            ->andWhere(['>', 'revoked_at', -1])
            ->all();

        foreach ($granted as $g) {
            if ((int) $g->revoked_at > 0) {
                continue;   // 已经收过了
            }
            $ids = array_filter(array_map('intval', explode(',', (string) $g->user_coupon_ids)));
            if ($ids) {
                // 只收「未使用」的。已使用/已过期的不动 —— 用都用了再去作废没有意义
                $revoked += UserCoupon::updateAll(
                    ['is_delete' => 1, 'deleted_at' => time()],
                    ['id' => $ids, 'status' => UserCoupon::STATUS_UNUSE, 'is_delete' => 0]
                );
            }
            RechargeOrderGift::updateAll(['revoked_at' => time()], ['id' => $g->id]);
        }

        if ($voided || $revoked) {
            Yii::warning("[RechargeGift] 充值单 {$rechargeOrderId} 退款:作废 {$voided} 期,收回券 {$revoked} 张");
        }
        return ['voided' => (int) $voided, 'revoked' => (int) $revoked];
    }
}
