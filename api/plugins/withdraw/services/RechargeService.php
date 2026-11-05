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

use app\models\Order;
use app\plugins\withdraw\models\Channel;
use app\plugins\withdraw\models\RechargeOrder;
use app\models\Goods;
use app\plugins\withdraw\listeners\RechargeGoodsListener;
use app\plugins\withdraw\models\RechargeGoods;
use app\plugins\withdraw\models\RechargePlan;
use app\plugins\withdraw\services\RechargeGiftService;
use Yii;

/**
 * 充值服务 —— 档位校验、建充值单、到账发放。
 *
 * 三条硬约定：
 *  ① **金额只认档位,不接受前端传值** —— 前端传金额等于让用户自己定价。
 *  ② 充值单落的是**下单那一刻的快照**;运营改档位不影响已售出的。
 *  ③ 到账走 `UPDATE ... WHERE status=0` 抢锁,抢到才发。
 *     支付回调和补结算 cron 会同时到,靠 if 判断挡不住并发。
 */
class RechargeService
{
    /** 积分侧业务类型(须与 RechargeIntegralTypeListener 登记的一致) */
    public const INTEGRAL_TYPE = 'withdraw_recharge';

    /**
     * 取一个可购买的档位并校验资格。
     *
     * @param int $goodsId 下单提交的充值商品ID。**必须校验档位属于这个商品** ——
     *                     否则用户可以拿 A 商品下单、传 B 商品的高额档位 ID,
     *                     价格按 B 档收、分润却按 A 商品的配置算,是个越权口子。
     * @throws \Exception 档位不存在 / 不属于该商品 / 已下架 / 商品已下架 /
     *                    到账账户不可用 / 该用户类型无权购买 / 超出每日限购
     */
    public static function resolvePlan(int $planId, int $mallId, int $userId, int $goodsId = 0): RechargePlan
    {
        if ($planId <= 0) {
            throw new \Exception('请选择充值档位');
        }
        $plan = RechargePlan::findOne(['id' => $planId, 'mall_id' => $mallId, 'is_delete' => 0]);
        if (!$plan) {
            throw new \Exception('充值档位不存在');
        }
        if ($goodsId > 0 && (int)$plan->goods_id !== $goodsId) {
            throw new \Exception('充值档位与商品不匹配');
        }
        if ((int)$plan->status !== RechargePlan::STATUS_ON) {
            throw new \Exception('该充值档位已下架');
        }
        if (bccomp((string)$plan->pay_amount, '0', 2) <= 0) {
            throw new \Exception('该档位金额配置有误，请联系管理员');
        }

        // 到账账户看**商品**的配置(一个商品一个钱包),不看档位那列已作废的 asset_type
        $cfg = RechargeGoods::ofGoods((int)$plan->goods_id, $mallId);
        if (!$cfg) {
            throw new \Exception('该充值商品尚未配置到账账户,请联系管理员');
        }
        if (!AssetService::isValid((string)$cfg->asset_type)) {
            throw new \Exception('该商品的到账账户已不可用，请联系管理员');
        }

        // 商品本身必须在架 —— 否则运营下架了商品,老链接还能继续充
        $goods = Goods::findOne(['id' => (int)$plan->goods_id, 'mall_id' => $mallId, 'is_delete' => 0]);
        if (!$goods || (int)$goods->status !== Goods::STATUS_UP) {
            throw new \Exception('该充值商品已下架');
        }
        if ($goods->type !== RechargeGoodsListener::GOODS_TYPE) {
            throw new \Exception('该商品不是充值商品');
        }

        // 用户类型：商户身份判定与提现渠道同口径
        if (!$plan->allowsUserType(self::userTypeOf($userId))) {
            throw new \Exception('当前身份不可购买该充值档位');
        }

        if ((int)$plan->day_limit > 0) {
            self::assertDayLimit($plan, $mallId, $userId);
        }

        // 累计限购:与 day_limit 是两个维度,配了就都要满足。
        // day_limit 控节奏(次日重置)、total_limit 控总量(充完就永远没有了)——
        // "新人首充 1 元得 10 元"这种亏本引流档只能靠后者挡住,
        // 光配 day_limit=1 的话用户每天都能再来一次。
        //
        // 与 day_limit 同口径:只数**已到账**的,待支付单不占额度,
        // 否则用户点开支付页又放弃就把自己的额度永久烧掉了 —— 累计额度不像
        // 每日额度会自己恢复,这里烧掉就是不可逆的。
        if ((int)$plan->total_limit > 0) {
            self::assertTotalLimit($plan, $mallId, $userId);
        }

        return $plan;
    }

    /**
     * 累计限购校验。
     *
     * 占用口径 = 已到账 + **仍活着的待支付单**。
     *
     * 为什么待支付单也算(与 day_limit 的口径不同,是刻意的):
     *   累计额度不会自己恢复。只数已到账的话,用户开 N 个标签页同时下单、
     *   再逐个支付,就能整体突破限制 —— 而 total_limit 的用途正是反薅羊毛,
     *   会薅的人恰恰会这么干。
     * 那为什么不怕"放弃支付把额度烧掉":
     *   未支付订单由核心 AutoCancelOrderTask 每分钟按 expire_at 自动取消,
     *   这里把已取消/已关闭的排除掉,额度会自己回来。
     */
    public static function assertTotalLimit(RechargePlan $plan, int $mallId, int $userId, ?\yii\db\Connection $db = null): void
    {
        $limit = (int)$plan->total_limit;
        if ($limit <= 0) {
            return;
        }

        $orderTable = Order::tableName();
        $used = RechargeOrder::find()->alias('r')
            ->leftJoin(["o" => $orderTable], 'o.id = r.order_id')
            ->where([
                'r.mall_id' => $mallId,
                'r.user_id' => $userId,
                'r.plan_id' => (int)$plan->id,
                'r.is_delete' => 0,
            ])
            ->andWhere(['or',
                // 已到账的,永久占用
                ['r.status' => RechargeOrder::STATUS_CREDITED],
                // 待支付但订单还活着的,临时占用(取消/关闭后自动释放)
                ['and',
                    ['r.status' => RechargeOrder::STATUS_PENDING],
                    ['not', ['o.id' => null]],
                    ['o.is_delete' => 0],
                    ['not in', 'o.status', [Order::STATUS_CLOSED]],
                ],
            ])
            ->count('*', $db);

        if ($used >= $limit) {
            throw new \Exception("该档位每人限购 {$limit} 次,您已充完");
        }
    }

    /**
     * 每日限购校验。
     *
     * 占用口径 = 今日已到账 + **今日仍活着的待支付单**,与 assertTotalLimit 完全一致。
     *
     * ⚠ 原来这里只数「已到账」的,理由是"怕用户点开支付页又放弃、把自己额度耗光"。
     *   但那样下单阶段根本不占额度 —— 用户开两个标签页分别下单、再分别支付,
     *   两笔都能过校验、都能到账,每日限购形同虚设(已实测:限购 1 次实际充了 2 次,
     *   「充100送10」这种档位就是白送)。
     *   而"怕烧额度"这个顾虑在 total_limit 那里已经论证过不成立:
     *   未支付订单由核心 AutoCancelOrderTask 按 expire_at 自动取消,
     *   这里把已取消/已关闭的排除掉,额度会自己回来 —— 何况每日额度次日本来就重置。
     */
    public static function assertDayLimit(RechargePlan $plan, int $mallId, int $userId, ?\yii\db\Connection $db = null): void
    {
        $limit = (int)$plan->day_limit;
        if ($limit <= 0) {
            return;
        }
        $todayStart = strtotime(date('Y-m-d 00:00:00'));
        $orderTable = Order::tableName();

        $used = RechargeOrder::find()->alias('r')
            ->leftJoin(["o" => $orderTable], 'o.id = r.order_id')
            ->where([
                'r.mall_id' => $mallId,
                'r.user_id' => $userId,
                'r.plan_id' => (int)$plan->id,
                'r.is_delete' => 0,
            ])
            ->andWhere(['>=', 'r.created_at', $todayStart])
            ->andWhere(['or',
                ['r.status' => RechargeOrder::STATUS_CREDITED],
                ['and',
                    ['r.status' => RechargeOrder::STATUS_PENDING],
                    ['not', ['o.id' => null]],
                    ['o.is_delete' => 0],
                    ['not in', 'o.status', [Order::STATUS_CLOSED]],
                ],
            ])
            ->count('*', $db);

        if ($used >= $limit) {
            throw new \Exception("该档位每天最多充值 {$limit} 次");
        }
    }

    /**
     * 为一个核心订单落充值单（快照）。已存在则直接返回，保证可重入。
     */
    public static function createForOrder(Order $order, RechargePlan $plan): RechargeOrder
    {
        $exists = RechargeOrder::findOne(['order_id' => (int)$order->id, 'is_delete' => 0]);
        if ($exists) {
            return $exists;
        }

        // 真正写入前再校验一次,并用锁把「查+写」串起来。
        // 前面 resolvePlan 的校验发生在下单流程的更早阶段,两次调用之间有窗口 ——
        // 不在这里兜住的话,并发下单仍能整体突破限购。
        //
        // ⚠ 两种限购**都要**进锁。原来只在 total_limit>0 时加锁,
        //   于是只配了每日限购的档位完全裸奔:开两个标签页分别下单、分别支付,
        //   两笔都能到账(已实测:限购 1 次实际充了 2 次)。
        //
        // ⛔ 锁用的是用户行(FOR UPDATE),不是 Redis 锁。原来 Redis 锁在这个函数的 finally 里就放了,
        //   可充值单要等外层建单事务(OrderCreator)跑完后面几个阶段才提交 —— 后一个请求在这个空档拿到锁,
        //   数不到前一单(还没提交),两单都过。行锁要等外层事务真正提交才放。
        // ⛔ 锁里的复查要另开一条连接数:建单事务开头就读过很多表,可重复读下普通查询看的是那一刻的快照,
        //   前一单哪怕已经提交了也数不到。当前连接也数一遍(同一事务里先写的只有它看得见),任一边超限就拒。
        $needLimit = (int)$plan->total_limit > 0 || (int)$plan->day_limit > 0;
        if ($needLimit) {
            \app\models\User::find()->where(['id' => (int)$order->user_id])->forUpdate()->one();
            $fresh = clone \Yii::$app->db;   // Connection::__clone 会丢掉原来的 PDO,第一次查询时新建连接
            try {
                foreach ([\Yii::$app->db, $fresh] as $db) {
                    self::assertDayLimit($plan, (int)$order->mall_id, (int)$order->user_id, $db);
                    self::assertTotalLimit($plan, (int)$order->mall_id, (int)$order->user_id, $db);
                }
            } finally {
                $fresh->close();
            }
        }

        $ro = new RechargeOrder();
        $ro->mall_id     = (int)$order->mall_id;
        $ro->user_id     = (int)$order->user_id;
        $ro->plan_id     = (int)$plan->id;
        $ro->order_id    = (int)$order->id;
        $ro->order_no    = (string)$order->order_no;
        // 到账资产以**商品配置**为准(档位那列已作废)
        $cfg = RechargeGoods::ofGoods((int)$plan->goods_id, (int)$order->mall_id);
        $ro->asset_type  = $cfg ? (string)$cfg->asset_type : 'balance';
        $ro->pay_amount  = $plan->pay_amount;
        $ro->gain_amount = $plan->gain_amount;
        $ro->gift_amount = $plan->gift_amount;
        $ro->plan_title  = (string)$plan->title;
        $ro->status      = RechargeOrder::STATUS_PENDING;
        if (!$ro->save()) {
            throw new \Exception('充值单创建失败：' . implode('; ', $ro->getFirstErrors()));
        }
        return $ro;
    }

    /**
     * 到账发放。**幂等**：同一个订单发多少次都只到账一次。
     *
     * @return bool 本次是否真的发放了（false = 无需发 / 已发过 / 抢锁失败）
     */
    public static function credit(int $orderId): bool
    {
        $ro = RechargeOrder::findOne(['order_id' => $orderId, 'is_delete' => 0]);
        if (!$ro) {
            return false;
        }
        if ((int)$ro->status === RechargeOrder::STATUS_CREDITED) {
            return false;
        }

        $total = $ro->totalGain();
        if (bccomp($total, '0', 2) <= 0) {
            return false;
        }

        $label = AssetService::label((string)$ro->asset_type);
        $gift  = bccomp((string)$ro->gift_amount, '0', 2) > 0
            ? "（含赠送 {$ro->gift_amount}）" : '';

        // ⚠ 抢锁和发钱必须在**同一个事务**里。
        //   分开做的话中间存在一个致命窗口：锁已提交、发钱时进程被杀 →
        //   充值单显示"已到账"但钱没进钱包，而后台「重新发放」又因为状态已是已到账而不可用，
        //   这笔钱就永远卡死了。放进一个事务，崩溃时两者一起回滚，下次补结算能重来。
        //   并发方面：另一个进程执行同一条 UPDATE 会被行锁挡住，等本事务结束再判 affected，
        //   所以幂等性不受影响。
        $tx = Yii::$app->db->beginTransaction();
        try {
            $affected = RechargeOrder::updateAll(
                ['status' => RechargeOrder::STATUS_CREDITED, 'credited_at' => time()],
                ['id' => $ro->id, 'status' => RechargeOrder::STATUS_PENDING]
            );
            if ($affected <= 0) {
                // 已被别的进程发过了
                $tx->rollBack();
                return false;
            }

            AssetService::credit(
                (int)$ro->user_id,
                (string)$ro->asset_type,
                $total,
                "充值到账 {$total} {$label}{$gift}",
                '账户充值',
                'withdraw_recharge_' . $ro->id,
                self::INTEGRAL_TYPE
            );

            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            Yii::error("[充值] 到账失败 recharge_order={$ro->id} order={$orderId}: " . $e->getMessage());
            throw $e;
        }

        Yii::warning("[充值] 到账成功 recharge_order={$ro->id} user={$ro->user_id} {$total} {$ro->asset_type}");

        // ── 分期赠品:在这里展开并发首期 ──
        // ⚠ 放在主到账事务**之外**,而且失败只记日志不抛:
        //   钱已经进钱包了,不能因为赠品这一步出问题就把整笔充值回滚掉。
        //   漏掉的期次由 WithdrawRechargeGiftTask 每分钟兜底重扫。
        //
        // ⚠ 计时起点用**到账时刻**而不是下单时刻:用户下单放着不付、三天后才付的话,
        //   按下单时间算会出现"第二期比第一期还早到"。
        try {
            $n = RechargeGiftService::expandForOrder($ro, (int)$ro->plan_id, time());
            if ($n > 0) {
                Yii::warning("[充值] 充值单 {$ro->id} 展开 {$n} 期赠品");
            }
            // 立即发「充值立返」那一期(plan_grant_at <= now 的都发)
            RechargeGiftService::grantDue((int)$ro->mall_id, 50, (int)$ro->id);
        } catch (\Throwable $e) {
            Yii::error("[充值] 分期赠品展开/发放失败 recharge_order={$ro->id}(不影响充值到账,由定时任务兜底): " . $e->getMessage());
        }

        return true;
    }

    /**
     * 判定用户类型 —— 直接复用提现渠道那套 UserTypeResolver，
     * 保证「谁能提现」和「谁能充值」是同一个口径，不另起一套判定。
     */
    public static function userTypeOf(int $userId, int $mallId = 0): string
    {
        $mallId = $mallId ?: (int)(Yii::$app->mall->id ?? 0);
        try {
            return UserTypeResolver::resolve($userId, $mallId);
        } catch (\Throwable $e) {
            Yii::warning('[充值] 用户类型判定失败，按普通用户处理: ' . $e->getMessage());
            return Channel::USER_TYPE_USER;
        }
    }
}
