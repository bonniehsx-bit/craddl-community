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

use app\services\order\context\DiscountConfig;
use app\services\order\context\OrderContext;
use app\services\order\discount\calculators\CouponCalculator;
use app\services\order\discount\DiscountDistributor;

/**
 * 优惠券策略
 *
 * 支持三种模式：
 * 1. user_coupon_id = -1: 强制不使用优惠券
 * 2. user_coupon_id =  0: 自动选择最优券
 * 3. user_coupon_id > 0: 使用指定优惠券
 */
class CouponStrategy implements DiscountStrategy
{
    public function getName(): string
    {
        return 'coupon';
    }

    public function isApplicable(OrderContext $context, DiscountConfig $config): bool
    {
        // 只要有用户，就应该执行优惠券策略（为了拉取列表），抵扣金额的计算交给内部的 Calculator 判断
        return $context->user !== null;
    }

    public function calculate(
        string         $remainingAmount,
        array          &$items,
        OrderContext   $context,
        DiscountConfig $config
    ): array {
        $calculator = new CouponCalculator();

        list($couponList, $cutPrice, $applicableGoodsIds, $calculatedCouponId, $calculatedCouponMchId) =
            $calculator->calculate(
                $remainingAmount,
                $items,
                $context->user,
                $context->mallId,
                $context->mchId,
                $config->userCouponId
            );

        // 平台券和商家券统一写入 coupon_cut_price
        // 商家结算时通过 UserCoupon.mch_id 判断承担方（平台券不扣商家收入）
        if (bccomp($cutPrice, '0.00', 2) > 0) {
            DiscountDistributor::distribute(
                $items,
                $cutPrice,
                'coupon_cut_price',
                $applicableGoodsIds
            );
        }

        return [
            'amount' => $cutPrice,
            'coupon_id' => $calculatedCouponId, // 实际使用的券ID(可能是自动选择的)
            'coupon_mch_id' => $calculatedCouponMchId, // 出资方ID(0=平台券,>0=商家券)
            'applicable_goods_ids' => $applicableGoodsIds,
            'coupon_list' => $couponList, // 可用券列表(供前端展示)
        ];
    }
}
