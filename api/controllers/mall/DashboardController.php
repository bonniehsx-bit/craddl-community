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

namespace app\controllers\mall;

use app\core\ApiCode;
use app\models\Goods;
use app\models\Order;
use app\models\User;
use app\models\CommissionLog;
use Yii;
use yii\helpers\Json;

class DashboardController extends MallController
{
    /**
     * 有效成交订单的筛选条件 —— 销售额/订单数/趋势图全都走这一处。
     *
     * ⛔ 最早这些统计只有 `pay_at >= X`,没有任何过滤,退了钱的单照样挂在营业额里。
     *
     * ⛔ 上一版改成 `pay_status IN (1,2)` 仍然是错的 —— 那是**拿支付字段判断退款**。
     *    pay_status 的职责只有"付没付钱":Order::isPaid() 的实现就是 `>= 1`,
     *    2/3 全算已支付;实测两库中 pay_status=0 的 pay_at 全为 0、>=1 的全 >0,
     *    也就是说 0 vs >=1 已经完整表达了支付事实,2/3 没有增加任何支付信息。
     *    退款该由 refund_status(五个态) / status(关闭) / refund_price(金额) 表达。
     *
     * 现在的口径,三个问题各问各的字段:
     *    · 成交了吗   → pay_status >= 1
     *    · 交易终止了吗 → status != 0(生命周期本来就归 status 管)
     *    · 退了多少   → refund_price,见 paidAmountExpr()
     *
     * 全额退款的单靠"金额减成 0"自然出局,不再需要按 pay_status 剔除 ——
     * 少一个判断,也就少一处会和退款流程脱节的地方。
     *
     * 抽成方法而不是六处各写一遍 —— 口径改一次就得改六处,漏一处就对不上,
     * 而"报表对不上"是最难被发现的那类问题。
     */
    private static function paidOrderQuery(int $mallId)
    {
        return Order::find()
            ->where(['mall_id' => $mallId, 'is_delete' => 0])
            ->andWhere(['>=', 'pay_status', Order::PAY_STATUS_PAID])
            ->andWhere(['!=', 'status', Order::STATUS_CLOSED]);
    }

    /**
     * 实收金额表达式:支付额减去已退部分。
     *
     * refund_price 由 RefundSuccessDispatcher::syncOrderRefundPrice() 按支付流水回写
     * (2026-08-23 之前这个字段从来没被写过,减数恒为 0,等于没减)。
     *
     * GREATEST(...,0) 是兜底:退款额理论上不会超过支付额,真超了(数据异常)也不能
     * 让某一单算出负数去冲掉别单的营业额 —— 那种错比少算更难被发现。
     */
    private static function paidAmountExpr(): string
    {
        return 'GREATEST(pay_price - IFNULL(refund_price, 0), 0)';
    }

    public function actionData()
    {
        $mallId = \Yii::$app->mall->id;

        // --- 时间范围定义 ---
        $todayStart = strtotime(date('Y-m-d 00:00:00'));

        // 昨日 —— 只截到**同一时刻**,不是整天。
        //
        // ⛔ 原来分母取昨日 23:59:59 的全天数据,分子却只有今天已过的这几个小时。
        //    结果是早上打开首页永远显示大幅下跌,跌幅只反映"现在几点",
        //    而老板会以为生意出了问题。同时段比才是环比。
        $yesterdayStart = strtotime(date('Y-m-d 00:00:00', strtotime('-1 day')));
        $yesterdayEnd = $yesterdayStart + (time() - $todayStart);

        // --- 0. PV/UV 数据获取 (实时) ---
        $redis = Yii::$app->redis;
        $date = date('Ymd');
        $pvKey = "jxmall:stat:pv:{$mallId}:{$date}";
        $uvKey = "jxmall:stat:uv:{$mallId}:{$date}";
        
        $todayPv = (int)$redis->get($pvKey);
        $todayUv = (int)$redis->scard($uvKey);

        // --- 1. 核心指标卡片数据 (含环比) ---

        // 1.1 销售额
        $todaySales = (float)(self::paidOrderQuery($mallId)
            ->andWhere(['>=', 'pay_at', $todayStart])
            ->sum(self::paidAmountExpr()) ?? 0);

        $yesterdaySales = (float)(self::paidOrderQuery($mallId)
            ->andWhere(['between', 'pay_at', $yesterdayStart, $yesterdayEnd])
            ->sum(self::paidAmountExpr()) ?? 0);

        // 1.2 订单数
        $todayOrderCount = (int)self::paidOrderQuery($mallId)
            ->andWhere(['>=', 'pay_at', $todayStart])
            ->count();

        $yesterdayOrderCount = (int)self::paidOrderQuery($mallId)
            ->andWhere(['between', 'pay_at', $yesterdayStart, $yesterdayEnd])
            ->count();

        // 计算增长率函数
        $calcRate = function ($today, $yesterday) {
            if ($yesterday == 0) return $today > 0 ? 100 : 0;
            return round(($today - $yesterday) / $yesterday * 100, 2);
        };

        $cardData = [
            // 今日销售额
            'today_sales' => $todaySales,
            'yesterday_sales' => $yesterdaySales,
            'sales_rate' => $calcRate($todaySales, $yesterdaySales), // 环比增长率

            // 今日访问量 (PV)
            'today_pv' => $todayPv,
            // 今日访客数 (UV)
            'today_uv' => $todayUv,

            // 今日订单数
            'today_order_count' => $todayOrderCount,
            'yesterday_order_count' => $yesterdayOrderCount,
            'order_rate' => $calcRate($todayOrderCount, $yesterdayOrderCount),

            // 总用户数
            'total_users' => (int)User::find()
                ->where(['mall_id' => $mallId, 'is_delete' => 0])
                ->count(),
            // 今日新增用户
            'new_users' => (int)User::find()
                ->where(['mall_id' => $mallId, 'is_delete' => 0])
                ->andWhere(['>=', 'created_at', $todayStart])
                ->count(),

            // 待审核提现 (前端"急"标签)
            //
            // 提现已全量迁到 withdraw 插件(plugin_withdraw_order),老 cash 链正在下线。
            // 原本数的是老 Cash 表 —— 新提现单不落那张表,导致首页这个「急」标签永远不亮,
            // 提现单积压也看不见。现走 WithdrawStatEvent 由提现插件应答(核心不依赖插件)。
            'pending_cash' => $this->countPendingWithdraw($mallId),
        ];

        // --- 2. 订单状态漏斗 (新增统计) ---
        // 待发货 (status = 20)
        $orderToShip = (int)Order::find()
            ->where(['mall_id' => $mallId, 'is_delete' => 0, 'status' => Order::STATUS_TO_BE_SHIPPED])
            ->count();

        // 售后中 (refund_status > 0)
        $orderRefund = (int)Order::find()
            ->where(['mall_id' => $mallId, 'is_delete' => 0])
            ->andWhere(['refund_status' => [Order::REFUND_STATUS_APPLIED, Order::REFUND_STATUS_PROCESSING]]) // 排除已拒绝/关闭的
            ->count();

        // 库存预警商品数。
        //
        // ⛔ 必须限定 type=normal。原来这里统计**全部 10 种商品类型**,而卡片点进去的
        //    商品列表(GoodsListForm)默认只查 normal —— 两边口径不同,点进去数量对不上。
        //    实测测试服:卡片显示 2 件,列表返回 0 件;那 2 件是 lucky(拼团)和
        //    emotion(情感分析),都是插件商品,各有自己的管理页,根本不在这个列表里。
        //    更根本的是**语义不同**:拼团商品的 stock=8 是剩余名额,不是快卖光了,
        //    拿它报"库存预警"本身就不成立。
        //
        // 阈值走 Goods::LOW_STOCK_THRESHOLD,与商品列表的 low_stock 筛选同一个来源。
        $lowStockCount = (int)Goods::find()
            ->where([
                'mall_id'   => $mallId,
                'is_delete' => 0,
                'status'    => 1,
                'type'      => Goods::TYPE_NORMAL,
            ])
            ->andWhere(['<', 'stock', Goods::LOW_STOCK_THRESHOLD])
            ->count();

        $pipelineData = [
            'to_ship' => $orderToShip,
            'refunding' => $orderRefund,
            'low_stock' => $lowStockCount,
            // 需要人工处理的退款(失败且重试用完 / 处理中没结果 / 后续没做完),首页有就提醒
            'refund_attention' => \app\services\RefundAttentionService::count($mallId),
        ];

        // --- 3. 佣金统计 (基于 CommissionLog) ---
        // 累计佣金支出
        $totalCommission = (float)(CommissionLog::find()
            ->where(['mall_id' => $mallId, 'is_delete' => 0, 'status' => CommissionLog::STATUS_SETTLED])
            ->sum('commission_amount') ?? 0);

        // 今日产生佣金 (待结算 + 已结算,**不含已失效**)
        // ⛔ 已失效 = 订单退款后被回滚的佣金,那笔钱没发出去。
        //    而退款常常和下单同一天,不排除的话当天就会被算进"今日产生佣金"
        //    (实测某库 523 条、¥18,176 都是当天创建当天失效的)。
        $todayCommission = (float)(CommissionLog::find()
            ->where(['mall_id' => $mallId, 'is_delete' => 0])
            ->andWhere(['!=', 'status', CommissionLog::STATUS_INVALID])
            ->andWhere(['>=', 'created_at', $todayStart])
            ->sum('commission_amount') ?? 0);

        $commissionData = [
            'total' => $totalCommission,
            'today' => $todayCommission
        ];

        // --- 4. 折线图数据 (近15天趋势) ---
        $days = 15;
        $chartData = [
            'dates' => [],
            'sales' => [],
            'orders' => [],
            'pv' => [],
            'uv' => []
        ];

        // 批量查询过去14天的历史统计记录 (提高性能，避免for循环里重复查库)
        $historyStatsRaw = \app\models\DailyStatistic::find()
            ->where(['mall_id' => $mallId])
            ->andWhere(['>=', 'date', date('Y-m-d', strtotime("-14 days"))])
            ->asArray()
            ->all();
        $historyStats = array_column($historyStatsRaw, null, 'date');

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $start = strtotime($date . ' 00:00:00');
            $end = strtotime($date . ' 23:59:59');

            $chartData['dates'][] = date('m-d', $start);

            // 当天销售额 —— 注释原本就写着「已支付」,但代码里并没有这个条件,
            // 注释与实现对不上。现在统一走 paidOrderQuery,名副其实。
            $daySales = self::paidOrderQuery($mallId)
                ->andWhere(['>=', 'pay_at', $start])
                ->andWhere(['<=', 'pay_at', $end])
                ->sum(self::paidAmountExpr());
            $chartData['sales'][] = (float)($daySales ?? 0);

            // 当天订单量 (已支付)
            $dayOrders = self::paidOrderQuery($mallId)
                ->andWhere(['>=', 'pay_at', $start])
                ->andWhere(['<=', 'pay_at', $end])
                ->count();
            $chartData['orders'][] = (int)$dayOrders;

            // 当天访问量
            if ($i === 0) { // 今天 (实时 Redis 数据)
                $chartData['pv'][] = $todayPv;
                $chartData['uv'][] = $todayUv;
            } else { // 历史数据 (MySQL)
                $dateKey = date('Y-m-d', $start);
                if (isset($historyStats[$dateKey])) {
                    $chartData['pv'][] = (int)$historyStats[$dateKey]['pv_count'];
                    $chartData['uv'][] = (int)$historyStats[$dateKey]['uv_count'];
                } else {
                    $chartData['pv'][] = 0;
                    $chartData['uv'][] = 0;
                }
            }
        }

        // --- 5. 饼图数据 (用户来源) ---
        $userPlatformData = User::find()->alias('u')
            ->select(['u.platform', 'count(*) as count'])
            ->where(['u.mall_id' => $mallId, 'u.is_delete' => 0])
            ->groupBy('u.platform')
            ->asArray()
            ->all();

        // ⛔ key 必须用 User 的常量,不能手写字符串。
        //    原来写的是小写连字符('mp-wx'/'mobile'/...),而库里存的是
        //    大写的 MPWX/MOBILE/WECHAT —— 一个都匹配不上,
        //    242 个用户全落到「其他来源」,饼图只有一块,而且不报错。
        $platformMap = [
            User::PLATFORM_MPWX   => '微信小程序',
            User::PLATFORM_WECHAT => '微信公众号',
            User::PLATFORM_MOBILE => 'H5/手机',
            User::PLATFORM_WXAPP  => 'APP',
        ];

        $pieData = [];
        foreach ($userPlatformData as $item) {
            $key = $item['platform'];
            $name = isset($platformMap[$key]) ? $platformMap[$key] : '其他来源';
            $pieData[] = [
                'name' => $name,
                'value' => (int)$item['count']
            ];
        }

        // --- 6. 排行榜 (商品销量 Top 5) ---
        $topGoodsRaw = Goods::find()
            // status=1 才是在售。不过滤的话下架商品也会挤进榜单 ——
            // 实测 bonnie 的 Top5 里就有一个 status=0 的
            ->where(['mall_id' => $mallId, 'is_delete' => 0, 'status' => 1])
            ->orderBy(['sales' => SORT_DESC])
            ->limit(5)
            ->select(['name', 'sales', 'cover_pic'])
            ->asArray()
            ->all();

        $topGoods = [];
        foreach ($topGoodsRaw as $goods) {
            $picUrl = $goods['cover_pic'];
            try {
                $picArr = Json::decode($goods['cover_pic']);
                if (is_array($picArr) && !empty($picArr)) {
                    $picUrl = $picArr[0];
                }
            } catch (\Exception $e) {
                // ignore
            }

            $topGoods[] = [
                'name' => $goods['name'],
                'sales' => (int)$goods['sales'],
                'cover_pic' => $picUrl
            ];
        }

        return $this->asJson([
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => 'success',
            'data' => [
                'mall_name' => \Yii::$app->mall->name,
                'card' => $cardData,
                'pipeline' => $pipelineData,
                'commission' => $commissionData,
                'chart' => $chartData,
                'pie' => $pieData,
                'top_goods' => $topGoods,
                'geo' => $this->buildGeoData($mallId, $todayStart),
            ]
        ]);
    }

    /**
     * 大屏中间 3D 地图:按收货省份统计成交单。
     *
     * 口径同上(paidOrderQuery / paidAmountExpr),按 order.province_code(国标 6 位行政区划码,
     * 和前端地图数据的 adcode 对得上)分组。自提/虚拟商品等没有收货省份的单不上地图,单独给个数。
     * 起点(飞线从哪儿出发)取商城设置里的门店经纬度;没配就由前端用订单最多的省份兜底。
     */
    private function buildGeoData(int $mallId, int $todayStart): array
    {
        $since = $todayStart - 29 * 86400; // 近 30 天(含今天)
        $norm = function ($code) {
            $code = (string)$code;
            return preg_match('/^\d{6}$/', $code) ? substr($code, 0, 2) . '0000' : '';
        };

        $rows = self::paidOrderQuery($mallId)
            ->select(['province_code', 'cnt' => 'COUNT(*)', 'amount' => 'SUM(' . self::paidAmountExpr() . ')'])
            ->andWhere(['>=', 'pay_at', $since])
            ->groupBy('province_code')
            ->asArray()->all();
        $provinces = [];
        $noRegion = 0;
        foreach ($rows as $r) {
            $code = $norm($r['province_code']);
            if ($code === '') {
                $noRegion += (int)$r['cnt'];
                continue;
            }
            if (!isset($provinces[$code])) {
                $provinces[$code] = ['code' => (int)$code, 'count' => 0, 'amount' => 0.0, 'today' => 0];
            }
            $provinces[$code]['count'] += (int)$r['cnt'];
            $provinces[$code]['amount'] += (float)$r['amount'];
        }

        $todayRows = self::paidOrderQuery($mallId)
            ->select(['province_code', 'cnt' => 'COUNT(*)'])
            ->andWhere(['>=', 'pay_at', $todayStart])
            ->groupBy('province_code')
            ->asArray()->all();
        foreach ($todayRows as $r) {
            $code = $norm($r['province_code']);
            if ($code !== '' && isset($provinces[$code])) {
                $provinces[$code]['today'] += (int)$r['cnt'];
            }
        }
        foreach ($provinces as &$p) {
            $p['amount'] = round($p['amount'], 2);
        }
        unset($p);
        usort($provinces, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        // 最近几笔成交:地图旁的实时滚动条用。只给省份、金额、时间,不带买家信息
        $recent = [];
        $recentRows = self::paidOrderQuery($mallId)
            ->select(['province_code', 'pay_at', 'amount' => self::paidAmountExpr()])
            ->andWhere(['>=', 'pay_at', $since])
            ->orderBy(['pay_at' => SORT_DESC])
            ->limit(8)
            ->asArray()->all();
        foreach ($recentRows as $r) {
            $recent[] = [
                'code' => (int)($norm($r['province_code']) ?: 0),
                'amount' => round((float)$r['amount'], 2),
                'pay_at' => (int)$r['pay_at'],
            ];
        }

        $setting = \app\helpers\MallSettingHelper::get(null, $mallId, []);
        $lat = isset($setting['lat']) && is_numeric($setting['lat']) ? (float)$setting['lat'] : 0;
        $lon = isset($setting['lon']) && is_numeric($setting['lon']) ? (float)$setting['lon'] : 0;
        // 只认国内范围内的坐标,填错/没填都当没配
        $origin = ($lon > 73 && $lon < 136 && $lat > 3 && $lat < 54) ? [$lon, $lat] : null;

        return [
            'days' => 30,
            'provinces' => array_values($provinces),
            'no_region' => $noRegion,
            'recent' => $recent,
            'origin' => $origin,
        ];
    }

    /**
     * 待审核提现单数。
     *
     * 走 WithdrawStatEvent 问、提现插件答 —— 核心不认识插件的表/模型,
     * 插件未装则无人应答、回落 0(主框架不依赖插件,见事件类注释)。
     */
    private function countPendingWithdraw(int $mallId): int
    {
        $e = new \app\events\WithdrawStatEvent(['mallId' => $mallId]);
        Yii::$app->trigger(\app\events\WithdrawStatEvent::NAME, $e);
        return (int) ($e->pendingCount ?? 0);
    }
}