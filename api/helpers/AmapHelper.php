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

use app\core\ApiCode;
use app\models\Address;
use Exception;

class AmapHelper
{
    /**
     * 根据经纬度进行逆地理编码查询
     * @param string $location "经度,纬度" 的字符串
     * @param int $mall_id 商城ID
     * @param string $output 返回数据格式, 可选值为：json, xml
     * @param int $radius 查询半径, 单位：米
     * @param string $extensions 返回结果控制, 可选值为：base, all
     * @return array
     */
    public static function reverseGeocode($location, $mall_id, $output = 'json', $radius = 1000, $extensions = '')
    {
        // 从配置中获取高德Key
        $setting = OptionHelper::get('mall_setting', $mall_id);
        if (!$setting || !isset($setting['amap_ak_api']) || empty($setting['amap_ak_api'])) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '高德地图接口配置有误');
        }
        $key = $setting['amap_ak_api'];

        if (empty($location)) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '经纬度信息不能为空');
        }

        $url = "https://restapi.amap.com/v3/geocode/regeo";

        $params = [
            'key' => $key,
            'location' => $location,
            'output' => $output,
            'radius' => $radius,
            'extensions' => $extensions,
        ];

        try {
            $res = HttpHelper::get($url, $params);

            if (!$res) {
                return ResponseHelper::send(ApiCode::CODE_FAIL, '请求高德接口失败');
            }

            if (isset($res['status']) && $res['status'] == '1') {
                $result = $res['regeocode'];
                $province_code = Address::find()->andWhere(['like', 'province_name', $result['addressComponent']['province']])->select('province_code')->scalar();
                if ($province_code) {
                    $result['addressComponent']['province_code'] = $province_code;
                }
                if ($result['addressComponent']['township']) {
                    $result['addressComponent']['town'] = $result['addressComponent']['township'];
                }
                return ResponseHelper::send(ApiCode::CODE_SUCCESS, '获取成功', ['result' => $result]);
            } else {
                $errorMessage = isset($res['info']) ? $res['info'] : '未知错误';
                return ResponseHelper::send(ApiCode::CODE_FAIL, $errorMessage);
            }

        } catch (Exception $e) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '请求异常：' . $e->getMessage());
        }
    }

    /**
     * 测量一个或多个起点到一个终点的距离
     * @param string $origins 起点经纬度, 格式为 "经度,纬度"。多个起点用 "|" 分隔
     * @param string $destination 终点经纬度, 格式为 "经度,纬度"
     * @param int $mall_id 商城ID
     * @param int $type 路径计算方式：0=直线距离；1=驾车导航距离（默认）；2=公交规划距离；3=步行规划距离
     * @param string $output 返回数据格式, 可选值为：json, xml
     * @return array
     */
    public static function calculateDistance($origins, $destination, $mall_id, $type = 1, $output = 'json')
    {
        // 从配置中获取高德Key
        $setting = OptionHelper::get('mall_setting', $mall_id);
        if (!$setting || !isset($setting['amap_ak_api']) || empty($setting['amap_ak_api'])) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '高德地图接口配置有误');
        }
        $key = $setting['amap_ak_api'];

        if (empty($origins) || empty($destination)) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '起点和终点经纬度信息不能为空');
        }

        $url = "https://restapi.amap.com/v3/distance";

        $params = [
            'key' => $key,
            'origins' => $origins,
            'destination' => $destination,
            'type' => $type,
            'output' => $output,
        ];

        try {
            $res = HttpHelper::get($url, $params);

            if (!$res) {
                return ResponseHelper::send(ApiCode::CODE_FAIL, '请求高德接口失败');
            }

            if (isset($res['status']) && $res['status'] == '1') {
                return ResponseHelper::send(ApiCode::CODE_SUCCESS, '获取成功', ['result' => $res]);
            } else {
                $errorMessage = isset($res['info']) ? $res['info'] : '未知错误';
                return ResponseHelper::send(ApiCode::CODE_FAIL, $errorMessage);
            }

        } catch (Exception $e) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '请求异常：' . $e->getMessage());
        }
    }
}