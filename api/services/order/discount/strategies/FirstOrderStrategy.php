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

namespace app\services\order\discount\strategies;

use app\models\Order;
use app\plugins\mch\services\FirstOrderQuotaService;
use app\services\order\context\DiscountConfig;
use app\services\order\context\OrderContext;
use app\services\order\discount\DiscountDistributor;

/**
 * 首单优惠策略
 */
class FirstOrderStrategy implements DiscountStrategy
{
    public function getName(): string
    {
        return 'first_order';
    }
    
    public function isApplicable(OrderContext $context, DiscountConfig $config): bool
    {
        if (!$context->user || !$context->mch) {
            return false;
        }
        
        // 商户未启用首单优惠
        if (!$context->mch->is_first_order_discount_enabled) {
            return false;
        }
        
        // 检查活动时间
        $now = time();
        if ($context->mch->first_order_discount_start_time && 
            strtotime($context->mch->first_order_discount_start_time) > $now) {
            return false;
        }
        if ($context->mch->first_order_discount_end_time && 
            strtotime($context->mch->first_order_discount_end_time) < $now) {
            return false;
        }
        
        // 检查是否平台首单
        $isPlatformFirstOrder = !Order::find()
            ->where(['user_id' => $context->user->id, 'pay_status' => Order::PAY_STATUS_PAID])
            ->exists();
        
        if (!$isPlatformFirstOrder) {
            return false;
        }
        
        // 这个人已经有一笔在用的首单(下了单没取消、或付过款没退完):不再给。
        // 只看「有没有已付款订单」拦不住没付款的那几单 —— 以前可以连开几单都带首单立减
        if (FirstOrderQuotaService::hasActiveUse((int)$context->mch->mall_id, (int)$context->user->id)) {
            return false;
        }

        // 名额:first_order_discount_total_stock 是「剩余桶数」(下单时扣、取消/退款时还)。
        // ⛔ 原来拿「已用日志条数」去和它比 —— 两边一个是已用、一个是剩余,用到一半活动就关了。
        // ⛔ 要比的是「这一单要占几桶」,不能只看 > 0:一桶 = 一行明细,剩 2 桶、购物车 3 件时
        //    这里放行,下单时占不到就整单回滚,买家每次重试都一样,这个购物车永远下不了单。
        //    名额不够就不给首单价,订单照常下。真正占名额在下单时的条件扣减(FirstOrderQuotaService::reserve)
        $barrels = max(1, is_array($context->items) ? count($context->items) : 0);
        if ((int)$context->mch->first_order_discount_total_stock < $barrels) {
            return false;
        }

        return true;
    }
    
    public function calculate(
        string $remainingAmount,
        array &$items,
        OrderContext $context,
        DiscountConfig $config
    ): array {
        $discountAmount = (string)$context->mch->first_order_discount_amount;
        
        // 不能超过剩余金额
        if (bccomp($discountAmount, $remainingAmount, 2) === 1) {
            $discountAmount = $remainingAmount;
        }
        
        // 分摊到商品
        DiscountDistributor::distribute(
            $items,
            $discountAmount,
            'first_order_discount_price',
            []
        );
        
        return [
            'amount' => $discountAmount,
        ];
    }
}
