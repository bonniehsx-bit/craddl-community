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

namespace app\services\order\pipeline\stages\preview;

use app\services\order\pipeline\StageInterface;

class FormatPreviewResponseStage implements StageInterface
{
    public function handle($payload, \Closure $next)
    {
        $context = $payload->context;
        $basePricing = $payload->basePricing;
        $discountResult = $payload->discountResult;
        $data = $payload->requestData;

        // payableAmount 已是"商品总价 - 各项优惠(含随机减,DiscountEngine 循环里已逐项扣减)"后的应付额,
        // 直接加运费即为最终合计,等于提交时算出的 pay_price。
        // 【历史 bug】此处曾把它误当作"随机减之前"再减一次 random_minus → 预览合计双扣随机减
        //   (商品 100、随机减 0.85 时合计显示 98.30),而提交价只扣一次(99.15),预览与实付对不上。
        $finalTotal = max('0.00', bcadd($discountResult->payableAmount, $basePricing->expressPrice, 2));

        $payload->responseData = array_merge(
            [
                'mch_id' => $context->mchId,
                'mch' => $context->mch,
                'delivery_type' => $context->deliverType,
                'goods_list' => $discountResult->items,
                'default_address' => $context->address,
                'mobile' => $data['mobile'] ?? '',
                'name' => $data['name'] ?? '',
                'total_price' => number_format($finalTotal, 2, '.', ''),
                'user_integral' => $context->getUserIntegral(),
                'delivery_time' => $context->deliveryTime,
                'expected_rewards' => ['integral' => '0.00'],
                'yuda_party_info' => $data['yuda_party_info'] ?? null,
            ],
            $basePricing->toArray(),
            $discountResult->toArray()
        );

        // 平台自营(无 mch)时,带上「是否支持自提 + 自提点」,供前端渲染自提 tab 与取货点;
        // 商家单的自提信息走 mch.delivery_options / mch 门店,不在此处。
        if (empty($context->mchId)) {
            $mallId = (int)\Yii::$app->mall->id;
            $s = \app\helpers\OptionHelper::get('mall_setting', $mallId) ?: [];
            $payload->responseData['support_pickup'] = (int)($s['support_pickup'] ?? 0);

            // 自提地址类型:platform=下发固定取货点;user_address=前端走与快递一致的选地址流程(不下发取货点)
            $pickupType = \app\services\order\PickupAddressResolver::type($mallId);
            $payload->responseData['pickup_address_type'] = $pickupType;
            $payload->responseData['pickup_point'] = $pickupType === \app\services\order\PickupAddressResolver::TYPE_PLATFORM
                ? \app\services\order\PickupAddressResolver::platformPoint($mallId)
                : null;
        }

        return $next($payload);
    }
}
