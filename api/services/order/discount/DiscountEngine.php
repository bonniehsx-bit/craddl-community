<?php

/**
 * Copyright (c) 2026 佛山今日鼎盛信息科技有限公司
 * @author Refactored Version
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

namespace app\services\order\discount;

use app\services\order\context\BasePricing;
use app\services\order\context\DiscountConfig;
use app\services\order\context\DiscountResult;
use app\services\order\context\OrderContext;
use app\services\order\discount\strategies\CouponStrategy;
use app\services\order\discount\strategies\FirstOrderStrategy;
use app\services\order\discount\strategies\IntegralStrategy;
use app\services\order\discount\strategies\PickupStrategy;
use app\services\order\discount\strategies\RandomMinusStrategy;

/**
 * 核心优惠计算引擎
 * 负责调度所有营销策略，并串行计算优惠金额和分摊
 */
class DiscountEngine
{
    /**
     * @var \app\services\order\discount\strategies\DiscountStrategy[] 已注册的优惠策略
     */
    private $strategies = [];

    public function __construct()
    {
        $this->registerStrategies();
    }

    /**
     * 注册策略池
     * 注意：数组的顺序决定了优惠计算的先后优先级！
     */
    private function registerStrategies(): void
    {
        $this->strategies = [
            new CouponStrategy(),       // 1. 优惠券
            new FirstOrderStrategy(),   // 2. 首单立减
            new IntegralStrategy(),     // 3. 积分抵扣
            new PickupStrategy(),       // 4. 自提折扣
            new RandomMinusStrategy(),  // 5. 随机立减
        ];
    }

    /**
     * 执行优惠计算
     * * @param BasePricing $pricing 基础定价(包含商品价+运费)
     * @param OrderContext $context 订单上下文
     * @param DiscountConfig $config 用户前端传来的优惠配置(如选中的券ID等)
     * @return DiscountResult
     */
    public function apply(
        BasePricing $pricing,
        OrderContext $context,
        DiscountConfig $config
    ): DiscountResult {
        // 获取商品列表的引用 (因为策略底层 DiscountDistributor 会直接修改子商品的均摊金额)
        $items = &$pricing->items;

        // 初始可被优惠抵扣的基数 = 商品总价 (运费通常不参与折扣)
        $remainingAmount = $pricing->goodsTotalPrice;

        // 存储各项优惠的执行结果
        $discounts = [];

        // 依次穿透执行各个策略
        foreach ($this->strategies as $strategy) {
            // 1. 检查该策略当前是否适用
            if (!$strategy->isApplicable($context, $config)) {
                continue;
            }

            // 2. 执行核心计算与明细分摊
            $result = $strategy->calculate(
                $remainingAmount,
                $items,
                $context,
                $config
            );

            $discountAmount = $result['amount'] ?? '0.00';

            // 💥【关键修复】：无论抵扣金额是否大于0，都必须保存该策略的执行结果！
            // 这样哪怕前端传 user_coupon_id = -1，算出来的抵扣金额是 0，
            // 也能把 result 里面的 coupon_list 原封不动带回给前端，防止列表丢失。
            $discounts[$strategy->getName()] = $result;

            // 3. 只有真正产生抵扣金额时，才去扣减订单剩余的待付基数
            if (bccomp($discountAmount, '0.00', 2) > 0) {
                $remainingAmount = bcsub($remainingAmount, $discountAmount, 2);
                $remainingAmount = max('0.00', $remainingAmount);
            }
        }

        return new DiscountResult($discounts, $items, $remainingAmount);
    }

    /**
     * 获取当前引擎已注册的所有策略类名 (主要用于 Debug 或 日志记录)
     */
    public function getRegisteredStrategies(): array
    {
        return array_map(function ($strategy) {
            return get_class($strategy);
        }, $this->strategies);
    }
}
