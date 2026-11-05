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

namespace app\plugins\mch\forms\admin\statistics;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Goods;
use app\models\Order;
use app\models\OrderDetail;
use app\models\OrderRefund;
use app\plugins\mch\models\Mch;
use app\services\order\OrderBehaviorRegistry;
use Yii;

class DashboardForm extends BaseModel
{
    public $period = 'today';

    public function rules()
    {
        return [
            [['period'], 'in', 'range' => ['today', 'yesterday', 'week', 'month', 'total']],
        ];
    }

    /**
     * [核心] 获取聚合后的统计数据
     * @return array
     */
    public function getStats()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $mch = Yii::$app->mch; // 获取当前登录的商家
        $mchId = $mch->id;
        $mchType = $mch->type;

        $revenueStats = $this->getRevenueStatistics($mchId);
        $orderStats = $this->getOrderStatistics($mchId);
        $goodsStats = $this->getGoodsStatistics($mchId);

        // --- 根据商家类型，组装前端所需的数据结构 ---
        $formattedData = [];

        if ($mchType == Mch::TYPE_UP_STORE) {
            // 组装【供应商】的数据看板
            $formattedData['dashboard_title'] = '供应商数据看板';
            $formattedData['revenue_stats'] = [
                'sales_amount' => $revenueStats['total_pay_price'],   // 销售额(成交)
                'net_amount' => $revenueStats['net_amount'],           // 净额(扣已完成退款)
                'refund_amount' => $revenueStats['total_refund_amount'], // 退款金额
                'refund_count' => $revenueStats['total_refund_count'],   // 退款笔数
                'sales_count' => $revenueStats['total_order_count'],   // 销售单数
                'cancel_amount' => $revenueStats['closed_goods_price'],// 取消金额
                'cancel_count' => $revenueStats['closed_order_count'],    // 取消单数
            ];
            $formattedData['order_stats'] = [
                'to_be_shipped' => $orderStats['toBeShipped'], // 待发货
            ];

        } elseif ($mchType == Mch::TYPE_DOWN_STORE) {
            // 组装【门店】的数据看板
            $formattedData['dashboard_title'] = '门店数据看板';
            $formattedData['revenue_stats'] = [
                'collection_amount' => $revenueStats['total_pay_price'], // 总收款(成交)
                'net_amount' => $revenueStats['net_amount'],             // 净额(扣已完成退款)
                'refund_amount' => $revenueStats['total_refund_amount'], // 退款金额
                'refund_count' => $revenueStats['total_refund_count'],   // 退款笔数
                'online_amount' => $revenueStats['online'],              // 线上商品收款
                'qrcode_amount' => $revenueStats['qrcode'],              // 收款码收款
                'cancel_amount' => $revenueStats['closed_goods_price'], // 取消金额
                'cancel_count' => $revenueStats['closed_order_count'],   // 取消单数
            ];
            $formattedData['order_stats'] = [
                'to_be_shipped' => $orderStats['toBeShipped'],   // 待发货
                'to_be_verified' => $orderStats['toBePickedUp'], // 待核销

            ];
        } else {
            // 个人租户 (type=4) 或其他未识别类型：兜底空结构避免 line 下方索引访问报错
            $formattedData['dashboard_title'] = '账户数据';
            $formattedData['revenue_stats'] = [
                'collection_amount' => $revenueStats['total_pay_price'] ?? 0,
                'net_amount'        => $revenueStats['net_amount'] ?? 0,
                'refund_amount'     => $revenueStats['total_refund_amount'] ?? 0,
                'refund_count'      => $revenueStats['total_refund_count'] ?? 0,
                'cancel_amount'     => $revenueStats['closed_goods_price'] ?? 0,
                'cancel_count'      => $revenueStats['closed_order_count'] ?? 0,
            ];
            $formattedData['order_stats'] = [];
        }
        $formattedData['order_stats']['processingRefund']=$orderStats['processingRefund'];

        $formattedData['mch_type'] = $mchType;
        // 通用统计数据
        $formattedData['common_stats'] = [
            'on_sale_goods' => $goodsStats['onSale'],     // 在售商品
            'off_shelf_goods' => $goodsStats['offShelf'], // 下架商品
            'on_check_goods' => $goodsStats['onCheck'],   // 审核中商品
        ];

        // 【新增】图表趋势数据（近15条）
        $formattedData['chart'] = $this->get15DaysChart($mchId);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', $formattedData);
    }

    /**
     * 【新增】获取近 15 天的销售额和订单量趋势
     * @param int $mchId
     * @return array
     */
    public function get15DaysChart(int $mchId): array
    {
        $dates = [];
        $sales = [];
        $orders = [];

        // 最近 15 天日期生成 (14, 13, ..., 0)
        for ($i = 14; $i >= 0; $i--) {
            $dates[] = date('Y-m-d', strtotime("-{$i} days"));
        }

        // 以当天0点为结束，14天前0点为开始
        $startTime = strtotime(date('Y-m-d', strtotime("-14 days")));
        $endTime = time();

        $orderList = Order::find()
            ->where([
                'mch_id' => $mchId,
                'pay_status' => Order::PAY_STATUS_PAID,
                'is_delete' => 0,
            ])
            ->andWhere(['!=', 'status', Order::STATUS_CLOSED])
            ->andWhere(['between', 'pay_at', $startTime, $endTime])
            ->select(['pay_price', 'pay_at'])
            ->asArray()
            ->all();

        $groupData = array_fill_keys($dates, ['sales' => 0, 'orders' => 0]);

        foreach ($orderList as $order) {
            $payAt = $order['pay_at'];
            $timestamp = is_numeric($payAt) ? $payAt : strtotime($payAt);
            $dateKey = date('Y-m-d', $timestamp);
            
            if (isset($groupData[$dateKey])) {
                $groupData[$dateKey]['sales'] += floatval($order['pay_price']);
                $groupData[$dateKey]['orders'] += 1;
            }
        }

        foreach ($dates as $d) {
            // 提供简化的 M/D 日期格式给前端图表X轴更美观
            $sales[] = sprintf('%.2f', $groupData[$d]['sales']);
            $orders[] = $groupData[$d]['orders'];
        }

        return [
            'dates' => $dates,
            'sales' => $sales,
            'orders' => $orders
        ];
    }

    /**
     * 获取收入相关的统计数据
     * @param int $mchId
     * @return array
     */
    /**
     * 营业额统计。
     *
     * 口径说明(实测数据后重定的,与旧版有三处不同):
     *
     * 1) **有退款不再整单不计**。旧版条件是 `refund_status = 无退款`,一笔 1000 元订单只退了 10 元,
     *    营业额就少算 1000 —— 商家看到当天营业额突然塌一块必然来问。
     *    现在:成交额照算,退款单独统计并给出「净营业额 = 成交 - 退款」。
     *
     * 2) **pay_status 放开到 2/3**。它有三个"已付"状态(1已付/2部分退款/3已退款),
     *    旧版只认 1。实测库里 pay_status=3 的商家订单有 17 笔共 1078.60,全被漏掉了。
     *    钱**确实收到过**,不该从成交额里凭空消失。
     *
     * 3) **退款金额取 order_refund 表**,不取 order.refund_price ——
     *    实测后者在所有订单上都是 0(从未被写入),拿它算净额等于没减。
     *
     * 时间维度:成交按 pay_at(实际收款时间),退款按退款单的 created_at,取消按 cancel_at。
     */
    public function getRevenueStatistics(int $mchId): array
    {
        list($startTime, $endTime) = $this->getTimeRange();

        // 已付款的三种状态:1=已付 2=部分退款 3=已退款。钱都真实收到过,都算成交。
        $paidStatuses = [Order::PAY_STATUS_PAID, Order::PAY_STATUS_PARTIAL_REFUND, Order::PAY_STATUS_REFUNDED];

        $baseWhere = function ($alias = '') use ($mchId, $paidStatuses) {
            $p = $alias ? $alias . '.' : '';
            return [
                $p . 'mch_id'     => $mchId,
                $p . 'pay_status' => $paidStatuses,
                $p . 'is_delete'  => 0,
            ];
        };

        // ── 成交额:订单实付 ──
        $orderLevelQuery = Order::find()->alias('o')
            ->where($baseWhere('o'))
            ->andWhere(['!=', 'o.status', Order::STATUS_CLOSED]);
        if ($startTime && $endTime) {
            $orderLevelQuery->andWhere(['between', 'o.pay_at', $startTime, $endTime]);
        }
        $revenueStatsPart1 = $orderLevelQuery
            ->select([
                'total_pay_price'   => 'SUM(o.pay_price)',
                'total_order_count' => 'COUNT(o.id)',
            ])
            ->asArray()
            ->one();

        // ── 线上 / 收款码拆分 ──
        // ⚠ 与成交额**同源**:都用 o.pay_price(订单实付),按明细的 total_price 占比分摊。
        // 旧版这里直接 SUM(od.total_price)(商品原价),于是「线上+收款码 ≠ 总营业额」,
        // 商家拿计算器一加就对不上。分摊后两者恒等,差异只剩浮点尾数。
        $detailLevelQuery = Order::find()->alias('o')
            ->where($baseWhere('o'))
            ->andWhere(['!=', 'o.status', Order::STATUS_CLOSED]);
        if ($startTime && $endTime) {
            $detailLevelQuery->andWhere(['between', 'o.pay_at', $startTime, $endTime]);
        }
        $detailLevelQuery->leftJoin(['od' => OrderDetail::tableName()], 'o.id = od.order_id')
            ->leftJoin(['g' => Goods::tableName()], 'od.goods_id = g.id');

        // 「扫码收款」的商品类型由各插件 Plugin::getOrderBehaviors() 的 offline_payment 声明,
        // mch 底座不认识具体插件。没有插件声明时,全部算线上成交。
        $offlineTypes = OrderBehaviorRegistry::keysWith('offline_payment');
        $offlineList = $offlineTypes
            ? implode(',', array_map(function ($t) { return Yii::$app->db->quoteValue($t); }, $offlineTypes))
            : null;

        // 明细占订单商品总额的比例 × 订单实付 = 该明细分摊到的实收
        $share = 'od.total_price / NULLIF(o.goods_total_price, 0) * o.pay_price';
        $onlineExpr = $offlineList
            ? "SUM(CASE WHEN g.type NOT IN ({$offlineList}) OR g.type IS NULL THEN {$share} ELSE 0 END)"
            : "SUM({$share})";
        // ⚠ fallback 必须用 Expression('0'):Yii2 的 select(['qrcode' => "0"]) 会把纯数字当列名
        //   加反引号 → `0` AS `qrcode` → SQLSTATE 42S22 Unknown column '0'。
        $qrcodeExpr = $offlineList
            ? "SUM(CASE WHEN g.type IN ({$offlineList}) THEN {$share} ELSE 0 END)"
            : new \yii\db\Expression('0');

        $revenueStatsPart2 = $detailLevelQuery
            ->select([
                'goods_total_price' => 'SUM(od.total_price)',
                'online'            => $onlineExpr,
                'qrcode'            => $qrcodeExpr,
            ])
            ->asArray()
            ->one();

        // ── 退款:取 order_refund(order.refund_price 实测恒为 0,不可用)──
        // 只统计**已完成**的退款(status=3),申请中/已拒绝/已关闭的不算 —— 钱还没退出去。
        // 换货完成也是「已完成」,但没退钱(售后单上的金额只是申请时填的),不算
        $refundQuery = OrderRefund::find()
            ->where(['mch_id' => $mchId, 'is_delete' => 0, 'status' => OrderRefund::STATUS_COMPLETED])
            ->andWhere(['!=', 'type', \app\services\RefundService::TYPE_EXCHANGE]);
        if ($startTime && $endTime) {
            $refundQuery->andWhere(['between', 'created_at', $startTime, $endTime]);
        }

        $refundStats = $refundQuery
            ->select([
                'total_refund_amount' => 'SUM(refund_price)',
                'total_refund_count' => 'COUNT(id)',
            ])
            ->asArray()
            ->one();

        // 已关闭(取消)订单的查询
        $closedOrderQuery = Order::find()
            ->where(['mch_id' => $mchId, 'status' => Order::STATUS_CLOSED, 'is_delete' => 0]);

        if ($startTime && $endTime) {
            $closedOrderQuery->andWhere(['between', 'cancel_at', $startTime, $endTime]);
        }

        $closedOrderStats = $closedOrderQuery
            ->andWhere(['type' => Order::TYPE_NORMAL])
            ->select([
                'closed_order_count' => 'COUNT(id)',
                'closed_goods_price' => 'SUM(goods_total_price)',
            ])
            ->asArray()
            ->one();

        $totalPay = (float)($revenueStatsPart1['total_pay_price'] ?? 0);
        $totalRefund = (float)($refundStats['total_refund_amount'] ?? 0);

        return [
            'closed_order_count' => (int)($closedOrderStats['closed_order_count'] ?? 0),
            'closed_goods_price' => (float)($closedOrderStats['closed_goods_price'] ?? 0),
            'goods_total_price' => (float)($revenueStatsPart2['goods_total_price'] ?? 0),
            'total_pay_price' => $totalPay,
            // 净营业额 = 成交 - 已完成退款。商家真正到手的口径,不会因为退了一点就整单归零
            'net_amount' => round($totalPay - $totalRefund, 2),
            'total_order_count' => (int)($revenueStatsPart1['total_order_count'] ?? 0),
            'online' => (float)($revenueStatsPart2['online'] ?? 0),
            'qrcode' => (float)($revenueStatsPart2['qrcode'] ?? 0),
            'total_refund_amount' => $totalRefund,
            'total_refund_count' => (int)($refundStats['total_refund_count'] ?? 0),
        ];
    }

    /**
     * 获取订单状态相关的统计数据
     * @param int $mchId
     * @return array
     */
    public function getOrderStatistics(int $mchId): array
    {
        $stats = Order::find()
            ->where(['mch_id' => $mchId, 'is_delete' => 0, 'status' => Order::STATUS_TO_BE_SHIPPED])
            ->select([
                'toBeShipped' => "SUM(CASE WHEN deliver_type = '" . Order::DELIVER_TYPE_EXPRESS . "' THEN 1 ELSE 0 END)",
                'toBeDelivered' => "SUM(CASE WHEN deliver_type = '" . Order::DELIVER_TYPE_CITY . "' THEN 1 ELSE 0 END)",
                'toBePickedUp' => "SUM(CASE WHEN deliver_type = '" . Order::DELIVER_TYPE_PICKUP . "' THEN 1 ELSE 0 END)",
            ])
            ->asArray()
            ->one();

        $processingRefundCount = OrderRefund::find()
            ->where([
                'mch_id' => $mchId,
                'is_delete' => 0,
            ])
            // "进行中" 的状态包括：待审核(0), 已同意/待用户发货(1), 用户已发货(5)
            ->andWhere(['in', 'status', [
                OrderRefund::STATUS_PENDING,
                OrderRefund::STATUS_AGREED,
                OrderRefund::STATUS_USER_SHIPPED,

            ]])
            ->count();
        return [
            'toBeShipped' => (int)($stats['toBeShipped'] ?? 0),
            'toBeDelivered' => (int)($stats['toBeDelivered'] ?? 0),
            'toBePickedUp' => (int)($stats['toBePickedUp'] ?? 0),
            'processingRefund' => (int)$processingRefundCount, // 【新增】返回进行中售后数量
        ];
    }

    /**
     * 获取商品状态相关的统计数据
     * @param int $mchId
     * @return array
     */
    public function getGoodsStatistics(int $mchId): array
    {
        // 幽灵商品（收银台的默认收银商品等）不计入商家的商品统计。
        // 名单由各插件 goods_protected 声明，mch 底座不再认识 cashier。
        $goodsQuery = Goods::find()->where(['mch_id' => $mchId, 'is_delete' => 0]);
        if ($protectedTypes = OrderBehaviorRegistry::keysWith('goods_protected')) {
            $goodsQuery->andWhere(['not in', 'type', $protectedTypes]); // goods.type 是 NOT NULL，无 NULL 陷阱
        }
        $stats = $goodsQuery
            ->select([
                'onCheck' => "SUM(CASE WHEN is_check = 0 THEN 1 ELSE 0 END)",
                'onSale' => "SUM(CASE WHEN status = 1 AND is_check = 1 THEN 1 ELSE 0 END)",
                'offShelf' => "SUM(CASE WHEN status = 0 AND is_check = 1 THEN 1 ELSE 0 END)",
                'lowStock' => "SUM(CASE WHEN stock <= warning_stock AND stock > 0 THEN 1 ELSE 0 END)",
            ])
            ->asArray()
            ->one();

        return [
            'onCheck' => (int)($stats['onCheck'] ?? 0),
            'onSale' => (int)($stats['onSale'] ?? 0),
            'offShelf' => (int)($stats['offShelf'] ?? 0),
            'lowStock' => (int)($stats['lowStock'] ?? 0),
        ];
    }

    /**
     * 获取查询的时间范围
     * @return array
     */
    public function getTimeRange()
    {
        $startTime = null;
        $endTime = null;

        switch ($this->period) {
            case 'today':
                $startTime = strtotime(date('Y-m-d'));
                $endTime = time();
                break;
            case 'yesterday':
                $startTime = strtotime(date('Y-m-d', strtotime('-1 day')));
                $endTime = strtotime(date('Y-m-d')) - 1;
                break;
            case 'week':
                $startTime = strtotime(date('Y-m-d', strtotime('-6 day')));
                $endTime = time();
                break;
            case 'month':
                $startTime = strtotime(date('Y-m-01'));
                $endTime = time();
                break;
            case 'total':
            default:
                break;
        }
        return [$startTime, $endTime];
    }
}