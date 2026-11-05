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
 * 优惠配置 - 用户的优惠选择偏好
 * 
 * 职责：
 * 1. 封装用户主动选择的优惠参数
 * 2. 提供优惠策略的判断依据
 */
class DiscountConfig
{
    /**
     * @var int 优惠券ID
     * -1: 强制不使用优惠券
     *  0: 自动选择最优优惠券
     * >0: 指定使用某张优惠券
     */
    public $userCouponId = 0;
    
    /**
     * @var bool 是否使用积分抵扣
     */
    public $isUseIntegral = false;
    
    /**
     * @var string|null 锁定的随机立减金额(用于preview->submit一致性)
     */
    public $lockedRandomMinus = null;
    
    /**
     * 从请求数据构建配置
     * 
     * @param array $data
     * @return self
     */
    public static function fromRequestData(array $data): self
    {
        $config = new self();
        
        // 优惠券选择
        if (isset($data['user_coupon_id'])) {
            $config->userCouponId = (int)$data['user_coupon_id'];
        }
        
        // 积分抵扣
        $config->isUseIntegral = !empty($data['is_use_integral']);
        
        // 锁定的随机立减(从preview带过来):仅采纳"有效正数"。
        // 0 / 空串 / 缺失 → 保持 null,交由策略按"每单确定性"重新计算(见 RandomMinusStrategy::calculateRandomAmount)。
        // 【为何排除 0】旧版/未更新的前端会把它初始化成 '0.00' 且从不回填,若采纳就会把随机减锁死成 0;
        //   而确定性算法本身保证同一单 preview==submit 同值,无需也不该信任客户端回传的"锁定值 0"。
        if (isset($data['locked_random_minus_price']) && is_numeric($data['locked_random_minus_price']) && (float)$data['locked_random_minus_price'] > 0) {
            $config->lockedRandomMinus = $data['locked_random_minus_price'];
        }
        
        return $config;
    }
    
    /**
     * 是否强制不使用优惠券
     * 
     * @return bool
     */
    public function isCouponDisabled(): bool
    {
        return $this->userCouponId === -1;
    }
    
    /**
     * 是否指定了优惠券
     * 
     * @return bool
     */
    public function hasCouponSelected(): bool
    {
        return $this->userCouponId > 0;
    }
    
    /**
     * 是否需要自动选择优惠券
     * 
     * @return bool
     */
    public function shouldAutoSelectCoupon(): bool
    {
        return $this->userCouponId === 0;
    }
}
