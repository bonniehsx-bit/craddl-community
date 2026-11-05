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

/**
 * 优惠策略接口 - 所有优惠策略的统一契约
 * 
 * 设计原则：
 * 1. 每个策略独立计算，互不干扰
 * 2. 策略按顺序执行，后面的策略基于前面策略扣减后的金额
 * 3. 策略自己负责分摊优惠到商品明细
 */
interface DiscountStrategy
{
    /**
     * 策略名称(用于结果key)
     * 
     * @return string 例如: 'coupon', 'integral', 'first_order'
     */
    public function getName(): string;
    
    /**
     * 判断该策略是否适用
     * 
     * 用途：
     * - 根据订单上下文判断是否启用该优惠
     * - 例如：积分策略需要检查用户是否选择使用积分
     * 
     * @param OrderContext $context 订单上下文
     * @param DiscountConfig $config 优惠配置
     * @return bool true=适用, false=跳过
     */
    public function isApplicable(OrderContext $context, DiscountConfig $config): bool;
    
    /**
     * 计算优惠金额
     * 
     * @param string $remainingAmount 剩余可优惠金额(商品总价 - 前面已扣减的优惠)
     * @param array $items 商品列表(引用传递，可在此方法内修改)
     * @param OrderContext $context 订单上下文
     * @param DiscountConfig $config 优惠配置
     * @return array 优惠结果 ['amount' => '10.00', ...其他元数据]
     */
    public function calculate(
        string $remainingAmount,
        array &$items,
        OrderContext $context,
        DiscountConfig $config
    ): array;
}
