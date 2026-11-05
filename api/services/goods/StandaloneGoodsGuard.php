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

namespace app\services\goods;

use app\models\Goods;
use app\services\order\OrderBehaviorRegistry;

/**
 * 专属商品(standalone)守卫 —— 核心各通用入口统一调这里,别各自手写判断。
 *
 * 专属商品 = 插件用商品编辑器建的正经商品(为了借用价格 / 会员价 / 分润),但只能走插件自己的入口下单。
 * 哪些类型是专属的,由插件在 Plugin::getOrderBehaviors() 里声明 standalone(核心不认识任何插件名)。
 *
 * 四道闸(方案见 docs/方案-插件专属商品类型隔离.md):
 *   ① 下单管线:专属商品必须由「type 相同」的 handler 处理,落到别的 handler(含普通 handler 兜底)一律拒
 *   ② 读取:商城通用列表不展示;通用详情页跳到专属入口
 *   ③ 入口:推广 / 海报 / 分享的落地页走 entryPath()
 *   ④ 测试:php yii test-goods-entry/run 按注册表逐类型断言
 */
class StandaloneGoodsGuard
{
    /** 拒绝时给用户看的话 —— 不暴露 type / handler 这类技术词 */
    const REJECT_MSG = '该商品不支持在商城直接购买,请从活动页面下单';

    public static function isStandalone($goods): bool
    {
        return $goods && OrderBehaviorRegistry::isStandalone((string)$goods->type);
    }

    /** 专属入口;普通商品或没声明入口的返回 null */
    public static function entryPath($goods): ?string
    {
        if (!self::isStandalone($goods)) {
            return null;
        }
        return OrderBehaviorRegistry::entryPath((string)$goods->type, (int)$goods->id);
    }

    /**
     * 商品的 C 端落地页:专属商品走插件入口,其余走商城通用详情页。
     * 推广选品、商品海报、分享卡片这类「把商品发出去」的地方一律用它,别手写 /others/goods/goods?id=。
     */
    public static function landingPath(string $goodsType, int $goodsId): string
    {
        if (OrderBehaviorRegistry::isStandalone($goodsType)) {
            $entry = OrderBehaviorRegistry::entryPath($goodsType, $goodsId);
            if ($entry) {
                return $entry;
            }
        }
        return '/others/goods/goods?id=' . $goodsId;
    }

    /**
     * 通用入口(加购 / 普通下单)拒绝专属商品。
     *
     * @param Goods[]|int[] $goodsOrIds 商品模型或商品 ID
     * @throws \Exception
     */
    public static function assertGenericPurchasable(array $goodsOrIds): void
    {
        if (!$goodsOrIds || !OrderBehaviorRegistry::keysWith('standalone')) {
            return;
        }
        $types = [];
        $ids = [];
        foreach ($goodsOrIds as $g) {
            if ($g instanceof Goods) {
                $types[] = (string)$g->type;
            } elseif ((int)$g > 0) {
                $ids[] = (int)$g;
            }
        }
        if ($ids) {
            $types = array_merge($types, Goods::find()->select('type')->where(['id' => array_values(array_unique($ids))])->column());
        }
        foreach (array_unique($types) as $t) {
            if (OrderBehaviorRegistry::isStandalone((string)$t)) {
                throw new \Exception(self::REJECT_MSG);
            }
        }
    }

    /**
     * 下单管线(逐商品阶段)的不变量:专属商品必须由 getOrderType() === goods.type 的 handler 处理。
     *
     * 为什么只看「type 相同」:下单管线解析 handler 有两处(主 handler 按客户端传的 plugin/type,
     * 逐商品按 goods.type),任何一处没对上就会静默落到普通 handler 或别的插件 handler ——
     * 插件的建单副作用(接单资料、充值单、缴费单)不执行,订单却照常建出、照常收钱(plaza 购电、充值都踩过)。
     *
     * @param object $handler 已解析出的 handler(主或逐商品)
     * @throws \Exception
     */
    public static function assertHandlerOwns($handler, Goods $goods): void
    {
        if (!self::isStandalone($goods)) {
            return;
        }
        $handlerType = method_exists($handler, 'getOrderType') ? (string)$handler->getOrderType() : '';
        if ($handlerType !== (string)$goods->type) {
            \Yii::warning("[专属商品] 商品 #{$goods->id}(type={$goods->type})被 " . get_class($handler)
                . "(type={$handlerType})处理,已拒绝下单", 'order.submit');
            throw new \Exception(self::REJECT_MSG);
        }
    }

    /**
     * 商城通用商品列表的排除条件(推荐 / DIY 商品组 / 分类 / 收藏 / 足迹 ……)。
     * 无任何声明时返回 null,调用方 `if ($c) $q->andWhere($c)`。
     *
     * @param string $typeCol 商品类型列名(含别名,如 'g.type')
     */
    public static function listCondition(string $typeCol = 'type'): ?array
    {
        return OrderBehaviorRegistry::goodsExcludeCondition('standalone', $typeCol);
    }
}
