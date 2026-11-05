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

namespace app\services\order\pipeline\stages\creation;

use app\handlers\order\NormalOrderHandler;
use app\models\Goods;
use app\services\goods\StandaloneGoodsGuard;
use app\services\order\factory\OrderHandlerFactory;
use app\services\order\pipeline\CreationPayload;
use app\services\order\pipeline\StageInterface;

class InitializeHandlerStage implements StageInterface
{
    public function handle($payload, \Closure $next)
    {
        /** @var CreationPayload $payload */

        $rawGoodsList = $payload->data['goods_list'] ?? [];
        if (empty($rawGoodsList)) {
            throw new \Exception('商品列表不能为空');
        }

        $firstItem = $rawGoodsList[0];
        $firstGoods = $payload->goodsMap[$firstItem['goods_id']] ?? null;
        if (!$firstGoods) {
            throw new \Exception('商品数据异常');
        }

        // 1. 获取主 Handler
        $payload->mainHandler = OrderHandlerFactory::create(
            $payload->form->plugin ?? null,
            $payload->form->type
        );

        // 自动填充 form type
        if (empty($payload->form->type)) {
            $payload->form->type = $firstGoods->type;
        }

        // 2. 循环校验每一件商品的库存与权限
        // 整单自动完成 = **每一件**都要自动完成。原来是「有一件开了就整单自动完成」:
        // 购物车把虚拟商品(开了支付后自动完成)和实物一起结算时,一付款整单就发货收货结算,
        // 实物没发出去订单已结算,顾客连售后都申请不了。单件订单(插件订单基本都是)结果不变。
        $isAutoComplete = true;
        foreach ($rawGoodsList as $item) {
            $goods = $payload->goodsMap[$item['goods_id']] ?? null;
            if (!$goods)
                throw new \Exception("商品(ID:{$item['goods_id']})不存在");

            $handler = OrderHandlerFactory::create(
                $payload->form->plugin ?? null,
                $goods->type
            );

            // 专属商品(插件声明 standalone)必须由 type 相同的 handler 处理,主 / 逐商品两处都要对上,
            // 否则插件的建单副作用不执行、订单却照常收钱。见 StandaloneGoodsGuard::assertHandlerOwns
            StandaloneGoodsGuard::assertHandlerOwns($payload->mainHandler, $goods);
            StandaloneGoodsGuard::assertHandlerOwns($handler, $goods);
            if ($handler instanceof NormalOrderHandler && !in_array((string)$goods->type, [Goods::TYPE_NORMAL, ''], true)) {
                // 非专属的插件商品落到普通 handler:暂不拦(声明制),但留痕,方便发现漏声明的类型
                \Yii::warning("[下单] 商品 #{$goods->id}(type={$goods->type})由普通 handler 处理,plugin=" . ($payload->form->plugin ?? ''), 'order.submit');
            }

            // 核心: 传递解析后的数组
            $handler->checkStockAndPermission($goods, $item, $payload->attrMap, $payload->data);

            if ($isAutoComplete && !$handler->isAutoComplete($goods)) {
                $isAutoComplete = false;
            }
        }

        // 将 isAutoComplete 暂存到 payload 的临时属性 (或者直接修改 form/data，这里建议存入 data)
        $payload->data['_is_auto_complete'] = $isAutoComplete;

        return $next($payload);
    }
}
