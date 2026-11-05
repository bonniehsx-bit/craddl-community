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

use app\models\Order;
use app\services\order\pipeline\CreationPayload;
use app\services\order\pipeline\StageInterface;

class CreateOrderRecordStage implements StageInterface
{
    public function handle($payload, \Closure $next)
    {
        /** @var CreationPayload $payload */

        $orderNo = Order::generateOrderNo($payload->user->id);
        $order = new Order();
        $order->mall_id = $payload->mall_id;
        $order->user_id = $payload->user->id;
        // 下单时会员等级快照(主框架通用能力):供分润/分销插件按「下单那一刻身份」计算,避免读实时 User.level 受异步升级/延迟支付影响
        $order->user_level_id = (int)($payload->user->level_id ?? 0);
        // 推荐人快照:下单时的直属上级。-1 平台直属 / -2 游客不算推荐人;此刻还没绑的,等 bindParent 绑上后补
        $order->referrer_id = max(0, (int)($payload->user->parent_id ?? 0));
        $order->user_level = (int)($payload->user->level ?? 0);
        $order->order_no = $orderNo;
        $order->type = $payload->form->type;
        $order->plugin = $payload->form->plugin ?? 'mall';
        $order->is_auto_complete = !empty($payload->data['_is_auto_complete']) ? 1 : 0;
        $order->remarks = $payload->data['remarks'] ?? '';

        // 获取商户ID
        $firstItem = $payload->data['goods_list'][0] ?? [];
        $firstGoods = $payload->goodsMap[$firstItem['goods_id'] ?? 0] ?? null;
        if ($firstGoods) {
            $order->mch_id = $payload->mainHandler->getMchId($firstGoods, $payload->data);
        }

        // 填充业务字段
        $order = $payload->mainHandler->fillOrderData(
            $order,
            $payload->data,
            $payload->previewData
        );

        \app\services\order\OrderInputGuard::assertOrderAmounts($order);
        if (!$order->save()) {
            throw new \Exception('主订单保存失败: ' . $this->getErrorMsg($order));
        }

        $payload->order = $order;

        return $next($payload);
    }

    private function getErrorMsg($model)
    {
        $errors = $model->getFirstErrors();
        return reset($errors);
    }
}
