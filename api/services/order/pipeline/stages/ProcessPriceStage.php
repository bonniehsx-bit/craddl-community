<?php

/**
 * Copyright (c) 2026 佛山今日鼎盛信息科技有限公司
 * @author Refactored Version (Pipeline Pattern)
 * @date 2026-02-15
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

namespace app\services\order\pipeline\stages;

use app\services\order\pipeline\OrderPayload;
use app\services\order\pipeline\StageInterface;

/**
 * 订单创建阶段 3：价格与财务明细装配节点
 * * 职责：负责主订单金额计算、所有营销优惠的落地定格、以及初始化商家/平台财务字段。
 */
class ProcessPriceStage implements StageInterface
{
    /**
     * @param OrderPayload $payload
     * @param \Closure $next
     * @return mixed
     */
    public function handle($payload, \Closure $next)
    {
        $order = $payload->order;
        $data = $payload->requestData;
        $previewData = $payload->previewData;

        // 负基础价是异常数据，不能被后续 max(0, ...) 当作合法零元单。
        \app\services\order\OrderInputGuard::assertAmount($previewData['goods_total_price'] ?? null);
        \app\services\order\OrderInputGuard::assertAmount($previewData['express_price'] ?? null);

        // ==========================================
        // 1. 核心基础金额
        // ==========================================
        $order->goods_total_price = $previewData['goods_total_price'];
        $order->express_price = $previewData['express_price'];

        $order->total_price = bcadd($order->goods_total_price, $order->express_price, 2);

        // ==========================================

        // ==========================================
        $order->member_discount_price = $previewData['member_discount_price'] ?? '0.00';
        $order->coupon_cut_price = $previewData['coupon_cut_price'] ?? '0.00';
        // platform_subsidy_cut_price 字段已废弃，统一写 0；随机减走独立字段 platform_subsidy_random_minus
        $order->platform_subsidy_cut_price = '0.00';
        $order->integral_cut_price = $previewData['integral_cut_price'] ?? '0.00';
        $order->first_order_discount_price = $previewData['first_order_discount_price'] ?? '0.00';
        $order->pickup_discount_price = $previewData['pickup_discount_price'] ?? '0.00';
        $order->platform_subsidy_random_minus = $previewData['random_minus_price'] ?? '0.00';

        // ==========================================

        // ==========================================
        // 绑定优惠券ID:只记真正减了钱的那张(预览算出的券ID,防篡改)。
        // 没减钱就不记:否则取消订单时 revertCoupons 会把一张没被这单用掉的券「退回」
        if ($order->coupon_cut_price > 0 && !empty($previewData['user_coupon_id'])) {
            $order->user_coupon_id = $previewData['user_coupon_id'];
        } else {
            $order->user_coupon_id = 0;
        }

        // 绑定积分抵扣
        if (!empty($data['is_use_integral'])) {
            $order->is_use_integral = 1;
            $order->use_integral = $previewData['total_use_integral'] ?? 0;
        } else {
            $order->is_use_integral = 0;
            $order->use_integral = 0;
        }

        // ==========================================

        // ==========================================
        // 应付计算 = 总价 - 所有让用户少付的优惠
        // 注意：先前漏扣 platform_subsidy_random_minus 之外的"平台补贴"概念已废弃，
        // 现在每一项字段都对应一个清晰的让利来源，pay_price 必须减去所有项。
        $payable = $order->total_price;
        $payable = bcsub($payable, $order->coupon_cut_price, 2);              // 所有券（平台券+商家券）
        $payable = bcsub($payable, $order->first_order_discount_price, 2);    // 首单立减（商家承担）
        $payable = bcsub($payable, $order->integral_cut_price, 2);            // 积分抵扣
        $payable = bcsub($payable, $order->pickup_discount_price, 2);         // 自提优惠
        $payable = bcsub($payable, $order->platform_subsidy_random_minus, 2); // 支付随机减（平台补贴）
        $order->pay_price = max('0.00', $payable);


        // 运费收入归属：0=平台, >0=商户/服务商
        if ($order->mch_id > 0) {
            $order->express_income_mch_id = $order->mch_id;
        } else {
            $order->express_income_mch_id = 0;
        }
        $order->is_express_settled = 0; // 运费未结算状态标识

        // 平台收入和商家收入初始占位 (具体数值会在后续保存 OrderDetail 时通过剥洋葱算法汇总)
        $order->platform_income = '0.00';
        $order->mch_income = '0.00';

        return $next($payload);
    }
}
