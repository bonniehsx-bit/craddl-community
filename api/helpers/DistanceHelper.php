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

use app\models\Address;

class DistanceHelper
{
    const EARTH_RADIUS = 6371393; // 地球半径，单位：米

    /**
     * 根据两点的经纬度计算距离
     *
     * @param float $lng1 起点经度
     * @param float $lat1 起点纬度
     * @param float $lng2 终点经度
     * @param float $lat2 终点纬度
     * @param bool $km 是否以公里为单位 (true:公里, false:米)
     * @param int $decimal 精度，保留小数位数
     * @return float 距离数值
     */
    public static function getDistance($lng1, $lat1, $lng2, $lat2, $km = true, $decimal = 2)
    {
        // 将角度转换为弧度
        $radLat1 = deg2rad($lat1);
        $radLat2 = deg2rad($lat2);
        $radLng1 = deg2rad($lng1);
        $radLng2 = deg2rad($lng2);

        // 计算经纬度的差值
        $a = $radLat1 - $radLat2;
        $b = $radLng1 - $radLng2;

        // 使用 Haversine 公式计算距离
        $distance = 2 * asin(sqrt(
                pow(sin($a / 2), 2) +
                cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2)
            )) * self::EARTH_RADIUS;

        if ($km) {
            $distance /= 1000;
        }

        return round($distance, $decimal);
    }

    /**
     * [兼容旧方法] 根据起点坐标和终点坐标测距离
     * 强烈建议您在新的代码中切换到使用 getDistance() 方法
     *
     * @param array $from [经度, 纬度]
     * @param array $to [经度, 纬度]
     * @param bool $km 是否以公里为单位
     * @param int $decimal 精度
     * @return float
     */
    public static function get_distance($from, $to, $km = true, $decimal = 2)
    {
        // 关键修正：删除 sort() 调用
        // sort($from);
        // sort($to);

        // 假设传入的格式固定为 [经度, 纬度]
        $lng1 = $from[0];
        $lat1 = $from[1];
        $lng2 = $to[0];
        $lat2 = $to[1];

        return self::getDistance($lng1, $lat1, $lng2, $lat2, $km, $decimal);
    }

    /**
     * 通过经纬度获取地理位置信息（逆地理编码）
     *
     * @param float $lon 经度
     * @param float $lat 纬度
     * @param int $mall_id
     * @return array
     */
    public static function getAddressByLocation($lon, $lat, $mall_id = 1)
    {
        $setting = OptionHelper::get('mall_setting', $mall_id);
        if (empty($setting['baidu_ak'])) {
            // 返回一个结构统一的错误，而不是混合 status 和 message
            return ['code' => -1, 'msg' => '百度地图AK未配置'];
        }
        $apiUrl = 'https://api.map.baidu.com/reverse_geocoding/v3/';
        $params = [
            'ak' => $setting['baidu_ak'],
            'output' => 'json',
            'coordtype' => 'gcj02ll', // wgs84ll 是通用GPS坐标，通常是正确的选择
            'location' => "{$lat},{$lon}", // 百度API要求纬度在前，经度在后
        ];
        try {
            $response = HttpHelper::get($apiUrl, $params);
            if (isset($response['status']) && $response['status'] === 0) {
                $result = $response['result']['addressComponent'];
                $address = Address::find()->andWhere(['like', 'province_name', $result['province']])->select('province_code')->scalar();
                if ($address) {
                    $response['result']['addressComponent']['province_code'] = $address ?? null;
                }
                return ['code' => 0, 'msg' => '获取成功', 'data' => $response];
            } else {
                return ['code' => -1, 'msg' => $response['message'] ?? '百度地图API请求失败', 'data' => $response];
            }
        } catch (\Exception $e) {
            return ['code' => -1, 'msg' => '请求异常: ' . $e->getMessage()];
        }
    }
}