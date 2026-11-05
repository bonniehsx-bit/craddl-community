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

namespace app\services\order\context;

/**
 * 优惠结果 - 封装优惠计算的完整结果
 * 
 * 职责：
 * 1. 记录各项优惠金额
 * 2. 记录优惠后的商品明细
 * 3. 记录优惠相关的元数据(如优惠券ID、积分使用量)
 */
class DiscountResult
{
    /**
     * @var array 各项优惠明细
     * [
     *   'coupon' => ['amount' => '10.00', 'coupon_id' => 123, ...],
     *   'integral' => ['amount' => '5.00', 'use_integral' => 500],
     *   ...
     * ]
     */
    public $discounts = [];

    /**
     * @var array 应用优惠后的商品列表
     */
    public $items = [];

    /**
     * @var string 优惠后的应付金额(商品总价 - 各项优惠)
     */
    public $payableAmount = '0.00';

    /**
     * 构造函数
     * 
     * @param array $discounts 优惠明细
     * @param array $items 商品列表
     * @param string $payableAmount 应付金额
     */
    public function __construct(array $discounts, array $items, string $payableAmount)
    {
        $this->discounts = $discounts;
        $this->items = $items;
        $this->payableAmount = $payableAmount;
    }

    /**
     * 获取指定优惠的金额
     * 
     * @param string $name 优惠名称
     * @return string
     */
    public function getDiscountAmount(string $name): string
    {
        return $this->discounts[$name]['amount'] ?? '0.00';
    }

    /**
     * 获取优惠券ID(如果使用了优惠券)
     * 
     * @return int
     */
    public function getCouponId(): int
    {
        return (int)($this->discounts['coupon']['coupon_id'] ?? 0);
    }

    /**
     * 获取使用的积分数量
     * 
     * @return int
     */
    public function getUseIntegral(): int
    {
        return (int)($this->discounts['integral']['use_integral'] ?? 0);
    }

    /**
     * 获取优惠券列表(供前端选择)
     *
     * @return array
     */
    public function getCouponList(): array
    {
        return $this->discounts['coupon']['coupon_list'] ?? [];
    }

    /**
     * 获取订单总的优惠券抵扣额（含平台券+商家券）
     */
    public function getCouponCutPrice(): string
    {
        $total = '0.00';
        foreach ($this->items as $item) {
            $val = $item['coupon_cut_price'] ?? '0.00';
            $total = bcadd($total, $val, 2);
        }
        return $total;
    }

    /**
     * 获取订单总的支付随机减抵扣额（平台补贴，反风控）
     */
    public function getRandomMinusAmount(): string
    {
        $total = '0.00';
        foreach ($this->items as $item) {
            $val = $item['platform_subsidy_random_minus'] ?? '0.00';
            $total = bcadd($total, $val, 2);
        }
        return $total;
    }

    /**
     * 获取使用的优惠券出资方ID（0=平台券，>0=商家券）
     */
    public function getCouponMchId(): int
    {
        return (int)($this->discounts['coupon']['coupon_mch_id'] ?? 0);
    }

    /**
     * 转为数组(用于响应)
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'coupon_cut_price' => $this->getCouponCutPrice(),
            'platform_subsidy_random_minus' => $this->getRandomMinusAmount(),
            'first_order_discount_price' => number_format($this->getDiscountAmount('first_order'), 2, '.', ''),
            'integral_cut_price' => number_format($this->getDiscountAmount('integral'), 2, '.', ''),
            'pickup_discount_price' => number_format($this->getDiscountAmount('pickup'), 2, '.', ''),
            'random_minus_price' => number_format($this->getDiscountAmount('random_minus'), 2, '.', ''),
            'user_coupon_id' => $this->getCouponId(),
            'coupon_mch_id' => $this->getCouponMchId(),
            'total_use_integral' => $this->getUseIntegral(),
            'coupon_list' => $this->getCouponList(),
        ];
    }
}
