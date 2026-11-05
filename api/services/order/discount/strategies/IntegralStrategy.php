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
use app\services\order\discount\calculators\IntegralCalculator;

/**
 * 积分抵扣策略
 */
class IntegralStrategy implements DiscountStrategy
{
    public function getName(): string
    {
        return 'integral';
    }
    
    public function isApplicable(OrderContext $context, DiscountConfig $config): bool
    {
        // 必须有用户、用户选择使用积分、订单支持积分
        return $context->user && $config->isUseIntegral;
    }
    
    public function calculate(
        string $remainingAmount,
        array &$items,
        OrderContext $context,
        DiscountConfig $config
    ): array {
        $calculator = new IntegralCalculator();
        
        // 调用计算器(会直接修改$items,因为积分分摊逻辑比较特殊)
        list($cutPrice, $useIntegral) = $calculator->calculate(
            $context->user,
            $items,
            $remainingAmount
        );
        
        return [
            'amount' => $cutPrice,
            'use_integral' => $useIntegral,
        ];
    }
}
