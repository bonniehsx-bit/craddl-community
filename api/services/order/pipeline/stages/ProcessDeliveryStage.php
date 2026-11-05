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

namespace app\services\order\pipeline\stages;

use app\helpers\CodeHelper;
use app\models\Order;
use app\models\UserAddress;
use app\plugins\mch\models\Mch;
use app\services\order\PickupAddressResolver;
use app\services\order\pipeline\OrderPayload;
use app\services\order\pipeline\StageInterface;
use yii\helpers\Json;

/**
 * 订单创建阶段 2：物流配送装配节点
 */
class ProcessDeliveryStage implements StageInterface
{
    public function handle($payload, \Closure $next)
    {
        $order = $payload->order;
        $data = $payload->requestData;
        $previewData = $payload->previewData;

        $deliverType = $data['deliver_type'] ?? $order->deliver_type;
        if (!$deliverType) {
            $deliverType = 'express';
        }
        $order->deliver_type = $deliverType;

        // ==========================================
        // 场景 A: 快递与同城配送 (提取收货地址)
        // ==========================================
        if (in_array($deliverType, ['express', 'city'])) {
            if (empty($data['address_id'])) {
                throw new \Exception('请选择收货地址');
            }

            // [安全修补] 增加越权拦截：必须查询属于当前用户的未删除地址
            // 注意：此时 $payload 是 OrderPayload，包含了 $order 模型，没有 $user 模型
            $userAddress = UserAddress::findOne([
                'id' => $data['address_id'],
                'user_id' => $payload->order->user_id,
                'is_delete' => 0
            ]);
            if (!$userAddress) {
                throw new \Exception('收货地址不存在或属于其他用户');
            }

            $order->address = $userAddress->address;
            $order->name = $userAddress->name;
            $order->mobile = $userAddress->mobile;
            $order->province_code = $userAddress->province_code;
            $order->city_code = $userAddress->city_code;
            $order->county_code = $userAddress->county_code;
            $order->town_code = $userAddress->town_code ?? '';
            $order->latitude = $userAddress->latitude ?? '';
            $order->longitude = $userAddress->longitude ?? '';
        }

        // ==========================================
        // 统一生成自提或无需物流业务线的统一核销码
        // ==========================================
        if (in_array($deliverType, [Order::DELIVER_TYPE_PICKUP, Order::DELIVER_TYPE_NONE])) {
            try {
                // 生成提货或通用凭证核销码 (前缀为用户ID，防重复且易溯源)
                $order->pickup_code = CodeHelper::generateUniqueCode((string)$order->user_id, Order::class, 'pickup_code');
            } catch (\Exception $e) {
                $order->pickup_code = CodeHelper::generateCode((string)$order->user_id);
            }
        }

        // ==========================================
        // 场景 B: 到店自提专用 (取商户地址)
        // ==========================================
        if ($deliverType === Order::DELIVER_TYPE_PICKUP) {
            // 提货人默认取前端填写的值;「用户选地址」模式下改由地址里的收货人提供(前端已不再单独填)
            $contactName = $data['name'] ?? '';
            $contactMobile = $data['mobile'] ?? '';

            if ($order->mch_id > 0) {
                // 商家自提:地址取商户门店(不变)。优先从上下文取,减小查库压力
                $mch = $previewData['mch'] ?? Mch::findOne($order->mch_id);
                if (!$mch) {
                    throw new \Exception('自提商户数据异常');
                }
                $order->address = $mch->address;
                $order->latitude = $mch->latitude;
                $order->longitude = $mch->longitude;
            } else {
                // 平台自提:地址来源由 mall_setting.pickup_address_type 决定
                $pickupType = PickupAddressResolver::type((int)$order->mall_id);
                if ($pickupType === PickupAddressResolver::TYPE_USER_ADDRESS) {
                    // 与快递配送完全一致:必须选一个属于自己的收货地址,不做任何兜底
                    if (empty($data['address_id'])) {
                        throw new \Exception('请选择收货地址');
                    }
                    $userAddress = UserAddress::findOne([
                        'id' => $data['address_id'],
                        'user_id' => $order->user_id,
                        'is_delete' => 0
                    ]);
                    if (!$userAddress) {
                        throw new \Exception('收货地址不存在或属于其他用户');
                    }
                    $order->address = $userAddress->address;
                    $order->province_code = $userAddress->province_code;
                    $order->city_code = $userAddress->city_code;
                    $order->county_code = $userAddress->county_code;
                    $order->town_code = $userAddress->town_code ?? '';
                    $order->latitude = $userAddress->latitude ?? '';
                    $order->longitude = $userAddress->longitude ?? '';
                    // 提货人 = 该地址的收货人,不再让用户重复填一遍
                    $contactName = (string)$userAddress->name;
                    $contactMobile = (string)$userAddress->mobile;
                } else {
                    // 平台自提点:pickup_* 留空则回退商城注册信息
                    $point = PickupAddressResolver::platformPoint((int)$order->mall_id);
                    $order->address = $point['address'];
                    $order->latitude = $point['latitude'];
                    $order->longitude = $point['longitude'];
                }
            }
            $order->name = $contactName;
            $order->mobile = $contactMobile;
        }

        // ==========================================
        // 场景 C: 同城配送特有字段 (距离与期望时间)
        // ==========================================
        if ($deliverType === 'city') {
            $cityInfo = $previewData['city_delivery_info'] ?? [];

            // [硬拦截] 收货地址缺定位信息 → 无法算距离,拒绝建单(否则会以运费0、不校验范围静默放行)
            if (!empty($cityInfo['is_location_missing'])) {
                throw new \Exception('该收货地址未设置定位信息,无法用于同城配送,请在地址中选择地理位置,或改用其他配送方式');
            }
            // [硬拦截·仅创建阶段跑,预览不跑] 超出商家配送范围 → 拒绝建单(预览层只回标记供前端警告)
            if (!empty($cityInfo['is_out_of_range'])) {
                throw new \Exception(sprintf(
                    '收货地址超出商家同城配送范围(约 %skm,上限 %skm),请更换地址或改用其他配送方式',
                    $cityInfo['distance'] ?? '?',
                    $cityInfo['delivery_radius'] ?? '?'
                ));
            }
            // [硬拦截] 商品金额未达最低起送门槛 → 拒绝建单
            $minAmount = (float)($cityInfo['min_order_amount'] ?? 0);
            if ($minAmount > 0) {
                $goodsTotal = (float)($previewData['goods_total_price'] ?? 0);
                if (bccomp(number_format($goodsTotal, 2, '.', ''), number_format($minAmount, 2, '.', ''), 2) === -1) {
                    throw new \Exception(sprintf(
                        '未达同城配送起送金额(需满 ¥%s,当前商品 ¥%s)',
                        number_format($minAmount, 2, '.', ''),
                        number_format($goodsTotal, 2, '.', '')
                    ));
                }
            }

            $cityInfo['delivery_time'] = $data['delivery_time'] ?? '';
            $order->city_delivery_info = Json::encode($cityInfo, JSON_UNESCAPED_UNICODE);
            $order->distance = $cityInfo['distance'] ?? 0;
            $order->is_immediately = (int)($data['is_immediately'] ?? 1);
            $order->delivery_time = ($order->is_immediately == 0) ? ($data['delivery_time'] ?? '') : '';
        }

        return $next($payload);
    }
}
