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
namespace app\plugins\mch\forms\mall;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\CommonPayOrder;
use app\models\CommonPayRefund;
use app\models\Goods;
use app\models\Order;
use app\models\OrderDetail;
use app\models\OrderRefund;
use app\plugins\mch\models\Mch;
use app\services\order\OrderBehaviorRegistry;
use yii\db\Expression;

/**
 * 平台后台 · 多商户数据概览
 *
 * 【口径说明】(前端也会把这套说明展示给运营,两边必须一致)
 *  · 成交额 GMV = 商家订单(mch_id>0)中 pay_status ∈ {已支付, 部分退款, 已退款} 的实付金额之和,
 *    按【支付时间 pay_at】落期。已退款的单仍计入 GMV(它确实成交过),再由"退款额"扣减得净额。
 *  · 退款额 = common_pay_refund 中 status=成功 的金额,按【退款发生时间】落期 —— 这是全库唯一权威口径。
 *    ⛔ 不用 order.refund_price:核心注释明确记载该字段历史上从未被写过(实测线上全是 0.00),
 *       用它做减数等于没减,旧看板"总销售额/实际销售额"两个数完全相同就是这么来的。
 *    ⛔ 也不用 order_refund.refund_price:那是【申请额】,可能被驳回或改额。
 *  · 待结算 = 尚未打款给商家的货款(order_detail.is_mch_settled=0),这是【实时存量】不随时间 tab 变化,
 *    前端已单独分区标注,避免被误读成"本期待结算"。
 *  · 平台佣金 = order.platform_income,退款时核心会 recalculateAfterRefund 重算,故直接求和即为真实值。
 */
class DashboardForm extends BaseModel
{
    /** @var string 统计周期 today|yesterday|week|month|total */
    public $period = 'today';

    public function rules()
    {
        return [
            [['period'], 'in', 'range' => ['today', 'yesterday', 'week', 'month', 'total']],
            [['period'], 'default', 'value' => 'today'],
        ];
    }

    /**
     * 周期 → [起, 止] 秒级时间戳;total 返回 [0, now] 表示不限起点。
     */
    private function timeRange(): array
    {
        $now = time();
        switch ($this->period) {
            case 'yesterday':
                $start = strtotime('yesterday');
                return [$start, strtotime('today') - 1];
            case 'week':
                return [strtotime('-6 days', strtotime('today')), $now];
            case 'month':
                return [strtotime(date('Y-m-01')), $now];
            case 'total':
                return [0, $now];
            case 'today':
            default:
                return [strtotime('today'), $now];
        }
    }

    /** 商家订单基础查询(本商城 + 已成交 + 商家单) */
    private function paidOrderQuery(array $range, string $alias = 'o')
    {
        $q = Order::find()->alias($alias)
            ->where([
                "{$alias}.mall_id" => $this->mall_id,
                "{$alias}.is_delete" => 0,
            ])
            ->andWhere(['>', "{$alias}.mch_id", 0])
            ->andWhere(['in', "{$alias}.pay_status", [
                Order::PAY_STATUS_PAID,
                Order::PAY_STATUS_PARTIAL_REFUND,
                Order::PAY_STATUS_REFUNDED,
            ]]);
        if ($range[0] > 0) {
            $q->andWhere(['between', "{$alias}.pay_at", $range[0], $range[1]]);
        }
        return $q;
    }

    public function get()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $range = $this->timeRange();

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'period'     => $this->period,
            'trade'      => $this->tradeStats($range),
            'settlement' => $this->settlementStats($range),
            'merchant'   => $this->merchantStats(),
            'goods'      => $this->goodsStats(),
            'todo'       => $this->todoStats(),
            'mix'        => $this->mixStats($range),
            'trend'      => $this->trend(),
            'top_mch'    => $this->topMerchants($range),
        ]);
    }

    /** 成交:GMV / 单量 / 退款 / 净额 / 平台佣金 / 商家应得 —— 一次条件聚合拿完 */
    private function tradeStats(array $range): array
    {
        $row = $this->paidOrderQuery($range)
            ->select([
                'gmv'             => new Expression('IFNULL(SUM(o.pay_price), 0)'),
                'order_count'     => new Expression('COUNT(o.id)'),
                'platform_income' => new Expression('IFNULL(SUM(o.platform_income), 0)'),
                'mch_income'      => new Expression('IFNULL(SUM(o.mch_income), 0)'),
                'express_amount'  => new Expression('IFNULL(SUM(o.express_price), 0)'),
            ])
            ->asArray()->one() ?: [];

        $gmv = (float)($row['gmv'] ?? 0);
        $refund = $this->refundAmount($range);
        $orderCount = (int)($row['order_count'] ?? 0);

        return [
            'gmv'             => $this->money($gmv),
            'order_count'     => $orderCount,
            'refund_amount'   => $this->money($refund),
            'net_amount'      => $this->money($gmv - $refund),
            'platform_income' => $this->money($row['platform_income'] ?? 0),
            'mch_income'      => $this->money($row['mch_income'] ?? 0),
            'express_amount'  => $this->money($row['express_amount'] ?? 0),
            // 客单价:净额 / 单量,单量为 0 时给 0 而不是除零
            'avg_order_price' => $this->money($orderCount > 0 ? ($gmv / $orderCount) : 0),
            // 退款率:退款额 / GMV
            'refund_rate'     => $gmv > 0 ? round($refund / $gmv * 100, 2) : 0,
        ];
    }

    /**
     * 权威退款额:common_pay_refund(status=成功)按退款发生时间落期,并限定商家订单。
     */
    private function refundAmount(array $range): float
    {
        $q = CommonPayRefund::find()->alias('r')
            ->innerJoin(['po' => CommonPayOrder::tableName()], 'po.id = r.common_pay_order_id')
            ->innerJoin(['o' => Order::tableName()], 'o.id = po.order_id')
            ->where([
                'r.mall_id'   => $this->mall_id,
                'r.is_delete' => 0,
                'r.status'    => CommonPayRefund::STATUS_SUCCESS,
                'po.is_delete' => 0,
                'o.is_delete' => 0,
            ])
            ->andWhere(['>', 'o.mch_id', 0]);
        if ($range[0] > 0) {
            $q->andWhere(['between', 'r.updated_at', $range[0], $range[1]]);
        }
        return (float)($q->sum('r.refund_price') ?: 0);
    }

    /**
     * 结算:待结算是【实时存量】(不随时间 tab 变),已结算按期内订单统计。
     * 待结算必须排除软删与已失效明细 —— 核心同口径函数专门写过警告:
     * 一条软删的 VALID 明细会被算进去,结算时多打钱出去。
     */
    private function settlementStats(array $range): array
    {
        $base = fn() => OrderDetail::find()->alias('od')
            ->innerJoin(['o' => Order::tableName()], 'o.id = od.order_id')
            ->where([
                'o.mall_id'   => $this->mall_id,
                'o.is_delete' => 0,
                'od.is_delete' => 0,
            ])
            ->andWhere(['>', 'o.mch_id', 0])
            ->andWhere(['!=', 'od.status', OrderDetail::STATUS_INVALID])
            ->andWhere(['in', 'o.pay_status', [
                Order::PAY_STATUS_PAID,
                Order::PAY_STATUS_PARTIAL_REFUND,
                Order::PAY_STATUS_REFUNDED,
            ]]);

        $pending = (float)($base()->andWhere(['od.is_mch_settled' => 0])->sum('od.mch_income_price') ?: 0);

        $settledQ = $base()->andWhere(['od.is_mch_settled' => 1]);
        if ($range[0] > 0) {
            $settledQ->andWhere(['between', 'o.pay_at', $range[0], $range[1]]);
        }
        $settled = (float)($settledQ->sum('od.mch_income_price') ?: 0);

        // 待结算订单数(实时):已完成待结算的商家单
        $pendingOrders = (int)Order::find()
            ->where([
                'mall_id' => $this->mall_id,
                'is_delete' => 0,
                'settlement_status' => 0,
                'status' => Order::STATUS_COMPLETED,
            ])
            ->andWhere(['>', 'mch_id', 0])
            ->count();

        return [
            'pending_amount' => $this->money($pending),
            'settled_amount' => $this->money($settled),
            'pending_orders' => $pendingOrders,
        ];
    }

    /** 商家:总数/今日新增/分状态/分类型 */
    private function merchantStats(): array
    {
        $base = fn() => Mch::find()->where(['mall_id' => $this->mall_id, 'is_delete' => 0]);

        $byStatus = $base()->select(['status', 'cnt' => new Expression('COUNT(*)')])
            ->groupBy('status')->asArray()->all();
        $statusMap = [];
        foreach ($byStatus as $r) {
            $statusMap[(int)$r['status']] = (int)$r['cnt'];
        }

        $byType = $base()->select(['type', 'cnt' => new Expression('COUNT(*)')])
            ->groupBy('type')->asArray()->all();
        $typeLabels = [1 => '线上商家', 2 => '线下门店', 3 => '酒店民宿', 4 => '个人租户'];
        $typeList = [];
        foreach ($byType as $r) {
            $typeList[] = [
                'type'  => (int)$r['type'],
                'label' => $typeLabels[(int)$r['type']] ?? ('类型' . (int)$r['type']),
                'count' => (int)$r['cnt'],
            ];
        }

        return [
            'total'     => (int)$base()->count(),
            'today_new' => (int)$base()->andWhere(['>=', 'created_at', strtotime('today')])->count(),
            'pending'   => $statusMap[0] ?? 0,   // 待审核
            'normal'    => $statusMap[1] ?? 0,   // 正常营业
            'rejected'  => $statusMap[2] ?? 0,   // 已驳回
            'disabled'  => $statusMap[3] ?? 0,   // 已禁用
            'by_type'   => $typeList,
        ];
    }

    /** 商品:在售/待审核。必须带 is_check,并剔除插件"幽灵商品" */
    private function goodsStats(): array
    {
        $base = fn() => Goods::find()->alias('g')
            ->innerJoin(['m' => Mch::tableName()], 'g.mch_id = m.id AND m.is_delete = 0 AND m.mall_id = :mid', [':mid' => $this->mall_id])
            ->where(['g.is_delete' => 0]);

        // 幽灵商品(如收银台为记账而建的占位商品)不该出现在商品口径里,
        // 名单由各插件通过 goods_protected 声明,底座不认识具体插件名。
        $excludeTypes = OrderBehaviorRegistry::keysWith('goods_protected');

        $onSale = $base()->andWhere(['g.status' => 1, 'g.is_check' => 1]);
        $pending = $base()->andWhere(['g.is_check' => 0]);
        if ($excludeTypes) {
            $onSale->andWhere(['not in', 'g.type', $excludeTypes]);
            $pending->andWhere(['not in', 'g.type', $excludeTypes]);
        }

        return [
            'on_sale'       => (int)$onSale->count(),
            'pending_check' => (int)$pending->count(),
        ];
    }

    /** 待办:三个需要平台介入的数字 */
    private function todoStats(): array
    {
        $refunding = (int)OrderRefund::find()->alias('r')
            ->innerJoin(['o' => Order::tableName()], 'o.id = r.order_id')
            ->where(['r.is_delete' => 0, 'o.mall_id' => $this->mall_id, 'o.is_delete' => 0])
            ->andWhere(['>', 'o.mch_id', 0])
            // 「进行中」= 还需要人介入的状态,排除已拒绝/已完成/已关闭这三个终态
            ->andWhere(['in', 'r.status', [
                OrderRefund::STATUS_PENDING,      // 待审核
                OrderRefund::STATUS_AGREED,       // 已同意(待用户发货)
                OrderRefund::STATUS_USER_SHIPPED, // 用户已发货,待商家收货
                OrderRefund::STATUS_PROCESSING,   // 商家已打款,退款处理中
            ]])
            ->count();

        $goods = $this->goodsStats();
        $mch = $this->merchantStats();

        return [
            'mch_pending'   => $mch['pending'],
            'goods_pending' => $goods['pending_check'],
            'refunding'     => $refunding,
        ];
    }

    /** 结构:履约方式 + 订单类型(普通 vs 收银台) */
    private function mixStats(array $range): array
    {
        $rows = $this->paidOrderQuery($range)
            ->select([
                'deliver_type' => 'o.deliver_type',
                'cnt'          => new Expression('COUNT(o.id)'),
                'amount'       => new Expression('IFNULL(SUM(o.pay_price), 0)'),
            ])
            ->groupBy('o.deliver_type')->asArray()->all();

        $deliverLabels = [
            Order::DELIVER_TYPE_EXPRESS => '快递',
            Order::DELIVER_TYPE_CITY    => '同城配送',
            Order::DELIVER_TYPE_PICKUP  => '到店自提',
            Order::DELIVER_TYPE_CASHIER => '收银台',
            Order::DELIVER_TYPE_NONE    => '无需物流',
        ];
        $deliver = [];
        foreach ($rows as $r) {
            $key = (string)$r['deliver_type'];
            $deliver[] = [
                'key'    => $key,
                'label'  => $deliverLabels[$key] ?? ($key ?: '未知'),
                'count'  => (int)$r['cnt'],
                'amount' => $this->money($r['amount']),
            ];
        }

        $typeRows = $this->paidOrderQuery($range)
            ->select([
                'type'   => 'o.type',
                'cnt'    => new Expression('COUNT(o.id)'),
                'amount' => new Expression('IFNULL(SUM(o.pay_price), 0)'),
            ])
            ->groupBy('o.type')->asArray()->all();
        $typeLabels = [Order::TYPE_NORMAL => '普通订单', Order::TYPE_CASHIER => '收银台订单'];
        $types = [];
        foreach ($typeRows as $r) {
            $key = (string)$r['type'];
            $types[] = [
                'key'    => $key,
                'label'  => $typeLabels[$key] ?? ($key ?: '普通订单'),
                'count'  => (int)$r['cnt'],
                'amount' => $this->money($r['amount']),
            ];
        }

        return ['deliver' => $deliver, 'order_type' => $types];
    }

    /**
     * 近 30 天 GMV 趋势 —— 用 SQL 分组,不把订单全量拉进 PHP 再循环。
     * 与卡片同口径(pay_status 1/2/3),两边数字对得上。
     */
    private function trend(): array
    {
        $start = strtotime('-29 days', strtotime('today'));
        $rows = $this->paidOrderQuery([$start, time()])
            ->select([
                'd'      => new Expression("FROM_UNIXTIME(o.pay_at, '%Y-%m-%d')"),
                'amount' => new Expression('IFNULL(SUM(o.pay_price), 0)'),
                'cnt'    => new Expression('COUNT(o.id)'),
            ])
            ->groupBy(new Expression("FROM_UNIXTIME(o.pay_at, '%Y-%m-%d')"))
            ->asArray()->all();

        $map = [];
        foreach ($rows as $r) {
            $map[$r['d']] = ['amount' => (float)$r['amount'], 'cnt' => (int)$r['cnt']];
        }

        // 补齐空白日期,否则折线会在没有订单的日子断开
        $out = [];
        for ($i = 0; $i < 30; $i++) {
            $day = date('Y-m-d', strtotime("+{$i} days", $start));
            $out[] = [
                'date'   => $day,
                'amount' => $this->money($map[$day]['amount'] ?? 0),
                'count'  => $map[$day]['cnt'] ?? 0,
            ];
        }
        return $out;
    }

    /** Top 10 商家(按期内 GMV) */
    private function topMerchants(array $range): array
    {
        $rows = $this->paidOrderQuery($range)
            ->select([
                'mch_id' => 'o.mch_id',
                'amount' => new Expression('IFNULL(SUM(o.pay_price), 0)'),
                'cnt'    => new Expression('COUNT(o.id)'),
            ])
            ->groupBy('o.mch_id')
            ->orderBy(['amount' => SORT_DESC])
            ->limit(10)->asArray()->all();

        if (!$rows) {
            return [];
        }

        $names = Mch::find()
            ->select(['id', 'mch_name'])
            ->where(['id' => array_column($rows, 'mch_id')])
            ->indexBy('id')->asArray()->all();

        $out = [];
        foreach ($rows as $r) {
            $mid = (int)$r['mch_id'];
            $out[] = [
                'mch_id' => $mid,
                'name'   => $names[$mid]['mch_name'] ?? ('商户#' . $mid),
                'amount' => $this->money($r['amount']),
                'count'  => (int)$r['cnt'],
            ];
        }
        return $out;
    }

    /** 金额统一两位小数字符串,避免前端各处 toFixed 口径不一 */
    private function money($v): string
    {
        return number_format((float)$v, 2, '.', '');
    }
}
