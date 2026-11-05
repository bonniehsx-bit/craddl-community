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

namespace app\services\order\discount\calculators;

use app\models\User;
use app\models\UserCoupon;
use yii\helpers\Json;

/**
 * 优惠券计算器 - 复用原有逻辑
 * 
 * 职责：
 * 1. 查询可用优惠券
 * 2. 计算优惠金额
 * 3. 智能选择最优券
 * 
 * 注意：这个类直接复用了原有的 calculateCoupon 逻辑，未做修改
 */
class CouponCalculator
{
    /**
     * 计算优惠券逻辑 (智能版)
     * 
     * 逻辑定义：
     * 1. 传 -1 : 强制不使用优惠券 -> 返回 0
     * 2. 传 >0 : 指定使用某张券 -> 成功返回ID，失败返回 0
     * 3. 传  0 : 自动选择最优券 -> 有则返回最优ID，无则返回 0
     * 
     * @param string $originGoodsTotalPrice 商品原价
     * @param array $goodsList 商品列表
     * @param User $user 用户
     * @param int $mallId 商城ID
     * @param int $mchId 商户ID
     * @param int $userCouponId 用户选择的券ID
     * @return array [优惠券列表, 优惠金额, 适用商品IDs, 实际使用的券ID]
     */
    public function calculate(
        string $originGoodsTotalPrice,
        array $goodsList,
        User $user,
        int $mallId,
        int $mchId,
        int $userCouponId
    ): array {
        // 查询用户所有可用优惠券
        $userAllCoupons = UserCoupon::find()->alias('uc')
            ->joinWith(['coupon c'])
            ->where([
                'uc.user_id' => $user->id,
                'uc.mall_id' => $mallId,
                'uc.status' => UserCoupon::STATUS_UNUSE,
                'uc.is_delete' => 0,
            ])
            ->andWhere(['<=', 'uc.start_at', time()])
            ->andWhere(['>=', 'uc.end_at', time()])
            // 商户匹配规则：
            //  · 本商户券(uc.mch_id = $mchId)：自营订单取平台券(mchId=0)、商家订单取该商家券 —— 维持原隔离
            //  · 平台跨店券：平台券(uc.mch_id=0) 且模板 c.apply_to_mch=1 → 可用于任意商家订单(mchId>0)，优惠平台买单
            // 平台券用于商家商品时，承担方沿用 isPlatformCoupon(UserCoupon.mch_id===0) 判定，自动归平台补贴、不扣商家收入。
            ->andWhere(
                $mchId > 0
                    ? ['or', ['uc.mch_id' => $mchId], ['and', ['uc.mch_id' => 0], ['c.apply_to_mch' => 1]]]
                    : ['uc.mch_id' => 0]
            )
            ->all();

        if (empty($userAllCoupons)) {
            return [[], '0.00', [], 0, 0];
        }

        $availableCouponList = [];

        $maxCutPrice = '0.00';
        $bestCouponId = 0;
        $bestApplicableIds = [];
        $bestCouponMchId = 0;

        // 指定使用的容器
        $selectedCutPrice = '0.00';
        $selectedApplicableIds = [];
        $selectedCouponMchId = 0;

        // 强制转为整型，确保 -1 逻辑生效
        $targetId = (int)$userCouponId;

        foreach ($userAllCoupons as $userCoupon) {
            $applicableAmount = '0.00';
            $currentApplicableIds = [];

            // 1. 计算适用金额
            if ($userCoupon->appoint_type === 'goods') {
                $appointGoodsIds = Json::decode($userCoupon->appoint_relation) ?: [];
                foreach ($goodsList as $item) {
                    if (in_array($item['goods_id'], $appointGoodsIds)) {
                        $applicableAmount = bcadd($applicableAmount, $item['total_price'], 2);
                        $currentApplicableIds[] = $item['goods_id'];
                    }
                }
            } else {
                $applicableAmount = $originGoodsTotalPrice;
            }

            // 2. 门槛校验
            if (bccomp($applicableAmount, $userCoupon->min_price, 2) === -1) {
                continue;
            }

            // 3. 计算优惠金额
            $cutPrice = '0.00';
            if ($userCoupon->type == 0) { // 减免券
                $cutPrice = $userCoupon->sub_price;
            } else { // 折扣券
                $discount = $userCoupon->discount;
                $cutPrice = bcmul($applicableAmount, bcsub(1, bcdiv($discount, 10, 2), 2), 2);
            }

            // 优惠不能超过适用金额
            if (bccomp($cutPrice, $applicableAmount, 2) === 1) {
                $cutPrice = $applicableAmount;
            }

            // 4. 组装可用列表
            $couponData = $userCoupon->toArray();
            $couponData['name'] = $userCoupon->coupon->name ?? '优惠券';
            $couponData['cut_price'] = $cutPrice;
            $availableCouponList[] = $couponData;

            // 5. 寻找最优解 (用于 targetId == 0 的情况)
            if (bccomp($cutPrice, $maxCutPrice, 2) === 1) {
                $maxCutPrice = $cutPrice;
                $bestCouponId = $userCoupon->id;
                $bestApplicableIds = $currentApplicableIds;
                $bestCouponMchId = $userCoupon->mch_id; // [修复] 提取出资方ID (0为平台)
            }

            // 6. 寻找指定解 (用于 targetId > 0 的情况)
            if ($targetId > 0 && $userCoupon->id == $targetId) {
                $selectedCutPrice = $cutPrice;
                $selectedApplicableIds = $currentApplicableIds;
                $selectedCouponMchId = $userCoupon->mch_id; // [修复] 提取出资方ID (0为平台)
            }
        }

        // === 最终决策逻辑 ===

        // 情况A: 用户强制不使用 (-1)
        if ($targetId === -1) {
            return [$availableCouponList, '0.00', [], 0, 0];
        }

        // 情况B: 用户指定了某张券 (>0)
        if ($targetId > 0) {
            if (bccomp($selectedCutPrice, '0.00', 2) === 1) {
                return [$availableCouponList, $selectedCutPrice, $selectedApplicableIds, $targetId, $selectedCouponMchId];
            }
            // 如果指定的券无效，降级为不使用
            return [$availableCouponList, '0.00', [], 0, 0];
        }

        // 情况C: 默认自动选择 (0)
        if ($bestCouponId > 0) {
            return [$availableCouponList, $maxCutPrice, $bestApplicableIds, $bestCouponId, $bestCouponMchId];
        }

        // 情况D: 没券可用
        return [$availableCouponList, '0.00', [], 0, 0];
    }
}
