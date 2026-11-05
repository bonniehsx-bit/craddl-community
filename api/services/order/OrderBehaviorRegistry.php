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

namespace app\services\order;

use app\models\Plugin as PluginModel;
use Yii;

/**
 * 订单行为注册表 —— 汇总各已安装插件 Plugin::getOrderBehaviors() 声明的订单类型特征。
 *
 * 存在的意义:此前核心与底座里散落着按「插件名 / 订单类型」写死的例外,例如
 *   core/cron/OrderSettleTask          ->andWhere(['!=', 'type', Order::TYPE_CASHIER])
 *   core/…/OrderSettlementService      if (in_array($order->plugin, ['yuda', 'hotel']))
 *   core/cron/PushRealFulfillmentTask  Goods::TYPE_CASHIER => '当面付交易已完成'
 *   mch/listeners/OrderListener        if (in_array($order->plugin, ['yuda', 'massage']))
 *   mch/cron/MchSettlementTask         ->andWhere(['!=', 'o.plugin', 'yuda'])
 * 每加一个业态就要改核心 —— 本注册表把它们换成插件的声明,核心从此不认识任何插件名。
 *
 * 索引键约定:
 *   · 订单级特征按 `Order.type` 索引;`Order.plugin` 作为兜底(历史数据 type 可能为空)。
 *     实测全库 (plugin,type) 组合中,插件订单二者恒同名(收银台从 mch 拆出后亦然:plugin=type=cashier)。
 *     双查 = 严格不弱于旧的「只按 plugin 判断」。
 *   · 商品级特征(goods_protected / fulfillment_text)按 `Goods.type` 索引,与订单类型同名。
 */
class OrderBehaviorRegistry
{
    /** @var array|null 请求级缓存 */
    private static $cache = null;

    /** 全部已安装插件声明的订单行为:key => 特征数组 */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $list = [];
        foreach (PluginModel::find()->where(['is_delete' => 0])->all() as $item) {
            $class = '\\app\\plugins\\' . $item->name . '\\Plugin';
            if (!class_exists($class)) {
                continue; // 孤儿安装记录:插件文件不存在
            }
            try {
                $behaviors = (new $class())->getOrderBehaviors();
            } catch (\Throwable $e) {
                Yii::warning('[OrderBehaviorRegistry] 插件 ' . $item->name . ' getOrderBehaviors 失败: ' . $e->getMessage());
                continue;
            }
            if (!is_array($behaviors)) {
                continue;
            }
            foreach ($behaviors as $key => $behavior) {
                if ($key === '' || !is_array($behavior)) {
                    continue;
                }
                $key = (string)$key;
                $list[$key] = array_merge($list[$key] ?? [], $behavior);
            }
        }
        return self::$cache = $list;
    }

    /** 按 key(订单类型 / 商品类型)取特征 */
    public static function ofKey(string $key): array
    {
        if ($key === '') {
            return [];
        }
        return self::all()[$key] ?? [];
    }

    /**
     * 解析某订单的特征:`type` 优先,`plugin` 兜底后被 type 覆盖。
     * 两者都查 = 严格不弱于「只按 plugin 判断」的旧行为,历史数据(type 为空)也不漏。
     *
     * @param \app\models\Order $order
     */
    public static function ofOrder($order): array
    {
        $all = self::all();
        $byPlugin = $all[(string)($order->plugin ?? '')] ?? [];
        $byType = $all[(string)($order->type ?? '')] ?? [];
        return array_merge($byPlugin, $byType);
    }

    /** 本类订单自主推进 settlement_status,核心 OrderSettleTask 不碰 */
    public static function isSelfSettled($order): bool
    {
        return !empty(self::ofOrder($order)['self_settled']);
    }

    /** 禁止走基础订单管线手动确认收货 */
    public static function blocksConfirm($order): bool
    {
        return !empty(self::ofOrder($order)['block_confirm']);
    }

    /** 禁止走基础物流体系发货(block_confirm 的孪生:一个管发货,一个管收货) */
    public static function blocksShip($order): bool
    {
        return !empty(self::ofOrder($order)['block_ship']);
    }

    /** 商家单的 express_price(运费/附加费)不论配送方式一律归商家 */
    public static function expressIncomeToMch($order): bool
    {
        return !empty(self::ofOrder($order)['express_income_to_mch']);
    }

    /** 商家货款不由 mch 结算(收益另有归属) */
    public static function isMchSettlementExempt($order): bool
    {
        return !empty(self::ofOrder($order)['mch_settlement_exempt']);
    }

    /** 该类商品不可被编辑 / 删除 / 上下架 */
    public static function isGoodsProtected(string $goodsType): bool
    {
        return !empty(self::ofKey($goodsType)['goods_protected']);
    }

    /**
     * 真实履约推送文案。有商品摘要且声明了 {summary} 模板时优先用模板。
     * @return string|null null = 本类型未声明,调用方走自己的默认文案
     */
    public static function fulfillmentText(string $orderType, string $goodsSummary = ''): ?string
    {
        $behavior = self::ofKey($orderType);
        if ($goodsSummary !== '' && isset($behavior['fulfillment_text_with_summary'])) {
            return str_replace('{summary}', $goodsSummary, (string)$behavior['fulfillment_text_with_summary']);
        }
        return isset($behavior['fulfillment_text']) ? (string)$behavior['fulfillment_text'] : null;
    }

    /**
     * 声明了某特征的全部 key。
     * @return string[] 可能为空数组
     */
    public static function keysWith(string $flag): array
    {
        $keys = [];
        foreach (self::all() as $key => $behavior) {
            if (!empty($behavior[$flag])) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    /**
     * 生成「排除声明了该特征的订单」的 SQL 条件,与 ofOrder() 的「type 优先、plugin 兜底」双查口径一致。
     *
     * ⚠️ 两个必须由本方法统一兜住的 SQL 陷阱,调用方切勿手写:
     *   1. `NOT IN ()` 空数组 → 条件恒 false,整表被清空。故无 key 时返回 null。
     *   2. `order.plugin` 是 **nullable** 列,而 SQL 里 `NULL NOT IN ('x')` 求值为 NULL(不是 true),
     *      该行会被静默过滤掉。若直接写 `['not in','plugin',$keys]`,生产上任何 plugin IS NULL 的
     *      订单都将永远进不了结算任务 —— 卡单资损。故 plugin 列必须显式放行 NULL。
     *      (`order.type` 是 NOT NULL,无此问题,但一并按同一形状处理更稳。)
     *
     * @param string $flag       特征名,如 self_settled / mch_settlement_exempt
     * @param string $typeCol    订单类型列名(含别名,如 'o.type')
     * @param string $pluginCol  订单插件列名(含别名,如 'o.plugin')
     * @return array|null null = 无任何声明,调用方应跳过 andWhere
     */
    public static function excludeCondition(string $flag, string $typeCol = 'type', string $pluginCol = 'plugin'): ?array
    {
        $keys = self::keysWith($flag);
        if (!$keys) {
            return null;
        }
        return [
            'and',
            ['or', [$typeCol => null], ['not in', $typeCol, $keys]],
            ['or', [$pluginCol => null], ['not in', $pluginCol, $keys]],
        ];
    }

    // ────────────────────────────────────────────────────────────────────
    //  专属商品(standalone):不进商城通用链路,只能走插件自己的入口下单
    //  方案见 docs/方案-插件专属商品类型隔离.md
    // ────────────────────────────────────────────────────────────────────

    /** 该商品类型是否声明为专属商品(商城通用详情/加购/普通下单/列表一律不认) */
    public static function isStandalone(string $goodsType): bool
    {
        return $goodsType !== '' && !empty(self::ofKey($goodsType)['standalone']);
    }

    /**
     * 专属商品的 C 端入口(推广链接、分享、旧链接跳转都指向它)。
     * 声明里的 {goods_id} 会被替换;没声明入口返回 null(例如收银台、账单缴费这种不对外售卖的占位商品)。
     */
    public static function entryPath(string $goodsType, int $goodsId): ?string
    {
        $path = (string)(self::ofKey($goodsType)['entry_path'] ?? '');
        if ($path === '') {
            return null;
        }
        return str_replace('{goods_id}', (string)$goodsId, $path);
    }

    /**
     * 「排除声明了某特征的**商品**」的 SQL 条件(商品表只有 type 列,与 excludeCondition 的订单双查口径不同)。
     * 无任何声明时返回 null,调用方跳过 andWhere(避免 NOT IN () 恒 false)。
     *
     * @param string $typeCol 商品类型列名(含别名,如 'g.type')
     */
    public static function goodsExcludeCondition(string $flag, string $typeCol = 'type'): ?array
    {
        $keys = self::keysWith($flag);
        if (!$keys) {
            return null;
        }
        return ['or', [$typeCol => null], ['not in', $typeCol, $keys]];
    }

    /** 单元测试用:清空请求级缓存 */
    public static function flushCache(): void
    {
        self::$cache = null;
    }
}
