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

namespace app\helpers;

use yii\helpers\Json;

class CityDeliveryHelper
{
    /**
     * 计算同城配送的核心方法
     * @param float $mch_lng 商家经度
     * @param float $mch_lat 商家纬度
     * @param float $user_lng 用户经度
     * @param float $user_lat 用户纬度
     * @param array $config 商家的同城配送配置数组
     * @return array [
     *      'code' => 0, // 0=成功, 1=失败
     *      'msg'  => '错误信息',
     *      'data' => [
     *          'fee'             => '10.00', // 计算出的运费
     *          'distance'        => 3.5,     // 实际距离
     *          'delivery_radius' => 5,       // 最大配送半径
     *          'is_out_of_range' => false,   // 是否超出范围
     *          'charge_detail'   => [...]    // 计费规则详情
     *      ]
     * ]
     * $config_array = [
     * 'enable' => (int)$postData['enable'],
     * 'delivery_radius' => (float)$postData['delivery_radius'],
     * 'charge_rules' => [
     * 'start_distance' => (float)$postData['start_distance'],
     * 'start_fee' => (string)$postData['start_fee'],
     * 'extra_fee_per_km' => (string)$postData['extra_fee_per_km'],
     * ],
     * // 其他可选配置...
     * ];
     *
     */
    public static function calculate($mch_lng = 116.4167, $mch_lat = 39.9244, $user_lng = 116.4167, $user_lat = 39.9244, $config = null)
    {

        $mch_lng = $mch_lng ?? 116.4167;
        $mch_lat = $mch_lat ?? 39.9244;
        $user_lng = $user_lng ?? 116.4167;
        $user_lat = $user_lat ?? 39.9244;

        // 1. 校验配置和参数
        if (empty($config['enable'])) {
            return self::error('商家未开启同城配送服务');
        }
        if (empty($mch_lng) || empty($mch_lat) || empty($user_lng) || empty($user_lat)) {
            return self::error('无法获取商家位置信息，请检查经纬度');
        }

        // 2. 计算距离 (单位：公里)
        $distance = DistanceHelper::getDistance($mch_lng, $mch_lat, $user_lng, $user_lat, 2);

        // 3. 校验是否在配送范围内
        $is_out_of_range = false;
        $delivery_radius = $config['delivery_radius'] ?? 0;
        if ($delivery_radius > 0 && bccomp($distance, $delivery_radius, 2) === 1) {
            $is_out_of_range = true;
        }

        // 4. 根据计费规则计算运费 (这是一个示例规则，您可以按需修改)
        $fee = '0.00';
        $charge_rules = $config['charge_rules'] ?? null;
        if ($charge_rules) {
            $fee = $charge_rules['start_fee'] ?? '0.00'; // 起步价
            $start_distance = $charge_rules['start_distance'] ?? 0; // 起步距离

            if (bccomp($distance, $start_distance, 2) === 1) { // distance > start_distance
                // 超出起步距离的部分
                $extra_distance = bcsub($distance, $start_distance, 2);
                $extra_fee_per_km = $charge_rules['extra_fee_per_km'] ?? '0.00'; // 每公里加价

                // 向上取整计算需要额外计费的公里数
                $extra_km_count = ceil($extra_distance);

                $fee = bcadd($fee, bcmul($extra_km_count, $extra_fee_per_km, 2), 2);
            }
        }

        // 5. 组装返回信息
        $data = [
            'fee' => $fee,
            'distance' => $distance,
            'delivery_radius' => $delivery_radius,
            'is_out_of_range' => $is_out_of_range,
            'charge_detail' => $charge_rules
        ];

        return self::success($data);
    }

    /**
     * 辅助方法：快速返回成功结果
     * @param array $data
     * @return array
     */
    private static function success($data = [])
    {
        return ['code' => 0, 'data' => $data];
    }

    /**
     * 辅助方法：快速返回失败结果
     * @param string $msg
     * @return array
     */
    private static function error($msg)
    {
        return ['code' => 1, 'msg' => $msg];
    }
}