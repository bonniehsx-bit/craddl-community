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

use app\services\order\factory\OrderHandlerFactory;
use app\services\order\pipeline\CreationPayload;
use app\services\order\pipeline\StageInterface;

class ProcessOrderDetailsStage implements StageInterface
{
    public function handle($payload, \Closure $next)
    {
        /** @var CreationPayload $payload */

        $totalMchIncome = '0.00';

        foreach ($payload->previewData['goods_list'] as $itemData) {
            $goods = $payload->goodsMap[$itemData['goods_id']];

            // 获取 Handler
            $handler = OrderHandlerFactory::create(
                $payload->form->plugin ?? null,
                $goods->type
            );

            $attr = null;
            if (isset($itemData['goods_attr_id'])) {
                $attr = $payload->attrMap[$itemData['goods_attr_id']] ?? null;
            }

            // A. 保存订单详情
            $detail = $handler->saveOrderDetail($payload->order, $goods, $itemData, $payload->data);
            $totalMchIncome = bcadd($totalMchIncome, $detail->mch_income_price, 2);

            // A2. 商品快照(名称/图片/编码/原售价/成本):在这里统一写,各插件的 saveOrderDetail 不用管
            $detail->writeGoodsSnapshot($goods, $attr);

            // B. 扣减库存
            $handler->deductStock($goods, $attr, $itemData['num'], $itemData);
        }

        // 将累计收入暂存到 payload，供后续 Stage 使用
        $payload->data['_total_mch_income'] = $totalMchIncome;

        return $next($payload);
    }
}
