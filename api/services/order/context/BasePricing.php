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
 * 基础定价结果 - 封装订单基础定价（优惠前）
 * 
 * 职责：
 * 1. 记录商品总价、会员优惠、运费等基础价格
 * 2. 为优惠计算提供基准金额
 */
class BasePricing
{
    /**
     * @var string 商品总价(应用会员折扣后)
     */
    public $goodsTotalPrice = '0.00';
    
    /**
     * @var string 会员优惠总额
     */
    public $memberDiscountPrice = '0.00';
    
    /**
     * @var string 运费
     */
    public $expressPrice = '0.00';
    
    /**
     * @var array 商品明细列表(已计算单价、总价、会员优惠)
     */
    public $items = [];
    
    /**
     * @var bool 订单是否支持积分抵扣(只要有一个商品支持即可)
     */
    public $canUseIntegral = false;
    
    /**
     * @var array|null 同城配送信息
     */
    public $cityDeliveryInfo = null;
    
    /**
     * 构造函数
     * 
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        if (isset($data['goods_total_price'])) {
            $this->goodsTotalPrice = $data['goods_total_price'];
        }
        
        if (isset($data['member_discount_price'])) {
            $this->memberDiscountPrice = $data['member_discount_price'];
        }
        
        if (isset($data['express_price'])) {
            $this->expressPrice = $data['express_price'];
        }
        
        if (isset($data['items'])) {
            $this->items = $data['items'];
        }
        
        if (isset($data['can_use_integral'])) {
            $this->canUseIntegral = $data['can_use_integral'];
        }
        
        if (isset($data['city_delivery_info'])) {
            $this->cityDeliveryInfo = $data['city_delivery_info'];
        }
    }
    
    /**
     * 获取订单总价(商品+运费)
     * 
     * @return string
     */
    public function getTotalPrice(): string
    {
        return bcadd($this->goodsTotalPrice, $this->expressPrice, 2);
    }
    
    /**
     * 转为数组(用于响应)
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'goods_total_price' => number_format($this->goodsTotalPrice, 2, '.', ''),
            'member_discount_price' => number_format($this->memberDiscountPrice, 2, '.', ''),
            'express_price' => number_format($this->expressPrice, 2, '.', ''),
            'can_use_integral' => $this->canUseIntegral,
            'city_delivery_info' => $this->cityDeliveryInfo,
        ];
    }
}
