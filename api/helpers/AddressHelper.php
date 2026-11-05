<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-03
 * Time: 9:50
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

class AddressHelper
{
    const ADDRESS_DATA = 'address_data';
    const AREA_DATA = 'area_data';

    public static function getProvince($province_code)
    {
        $address = Address::findOne(['province_code' => $province_code]);
        if ($address) {
            return $address->province_name;
        }
        return null;
    }

    public static function getCity($city_code)
    {
        $address = Address::findOne(['city_code' => $city_code]);
        if ($address) {
            // [笔误修正] 应该返回 city_name
            return $address->city_name;
        }
        return null;
    }

    public static function getCounty($county_code)
    {
        $address = Address::findOne(['county_code' => $county_code]);
        if ($address) {
            return $address->county_name;
        }
        return null;
    }

    public static function getTown($town_code)
    {
        $address = Address::findOne(['town_code' => $town_code]);
        if ($address) {
            return $address->town_name;
        }
        return null;
    }

    /**
     * [修正版] 根据code获取地址模型
     * @param $province_code
     * @param $city_code
     * @param $county_code
     * @param $town_code
     * @return Address|null
     */
    public static function getArea($province_code, $city_code, $county_code, $town_code)
    {
        $query = Address::find()->where([
            'province_code' => $province_code,
            'city_code' => $city_code,
            'county_code' => $county_code
        ]);

        if ($town_code) {
            // 如果传入了 town_code，则精确匹配
            $query->andWhere(['town_code' => $town_code]);
        } else {
            // 如果不传 town_code，应该找代表区县级本身的记录
            $query->andWhere(['or', ['town_code' => null], ['town_code' => ''], ['town_code' => '0']]);
        }

        return $query->one();
    }

    public static function getTownList($county_code)
    {
        $list = Address::find()
            ->where(['county_code' => $county_code])
            ->andWhere(['>', 'town_code', 0]) // 过滤掉代表区县本身的记录
            ->select('town_code as code, town_name as name')
            ->distinct() // 确保乡镇不重复
            ->asArray()
            ->all();

        foreach ($list as &$town) {
            $town['level'] = 4;
        }
        unset($town);
        return $list;
    }

    /**
     * [极致性能优化版] 获取省市区三级联动数据 (不含镇)
     * @return mixed
     */
    public static function getAreaData()
    {
        $data = CacheHelper::get(self::AREA_DATA);
        if ($data) {
            return $data;
        }

        // 1. 一次性获取所有省、市、区的数据
        // 名称列按 code 分组聚合(MAX):name 与 code 一一对应,MAX 即取该 code 的名称,
        // 同时兼容 MySQL only_full_group_by(非聚合且不在 GROUP BY 的列会报 1055)。
        $allList = Address::find()
            ->select([
                'province_code',
                'province_name' => 'MAX(province_name)',
                'city_code',
                'city_name' => 'MAX(city_name)',
                'county_code',
                'county_name' => 'MAX(county_name)',
            ])
            ->groupBy(['province_code', 'city_code', 'county_code'])
            ->orderBy(['province_code' => SORT_ASC, 'city_code' => SORT_ASC, 'county_code' => SORT_ASC])
            ->asArray()
            ->all();

        // 2. 在内存中高效构建树状结构
        $provinceMap = [];
        $cityMap = [];

        foreach ($allList as $item) {
            // 处理省 (使用引用，直接操作最终数组)
            if (!isset($provinceMap[$item['province_code']])) {
                $provinceMap[$item['province_code']] = [
                    'code' => $item['province_code'],
                    'name' => $item['province_name'],
                    'level' => 1,
                    'list' => []
                ];
            }
            // 处理市
            if ($item['city_code'] && !isset($cityMap[$item['city_code']])) {
                $cityMap[$item['city_code']] = [
                    'code' => $item['city_code'],
                    'name' => $item['city_name'],
                    'level' => 2,
                    'list' => []
                ];
                // 使用引用，将市挂载到对应的省下面
                $provinceMap[$item['province_code']]['list'][$item['city_code']] = &$cityMap[$item['city_code']];
            }
            // 处理区
            if ($item['county_code'] && isset($cityMap[$item['city_code']])) {
                $cityMap[$item['city_code']]['list'][] = [
                    'code' => $item['county_code'],
                    'name' => $item['county_name'],
                    'level' => 3
                ];
            }
        }

        // 3. 整理最终数据结构，去除用于映射的key
        $tree = [];
        foreach ($provinceMap as $provinceCode => $province) {
            $cityList = [];
            foreach ($province['list'] as $cityCode => $city) {
                $cityList[] = $city;
            }
            $province['list'] = $cityList;
            $tree[] = $province;
        }

        CacheHelper::set(self::AREA_DATA, $tree, null);
        return $tree;
    }

    /**
     * [极致性能优化版] 获取省市区镇四级联动数据
     * @return mixed
     */
    public static function getAddressData()
    {
        $data = CacheHelper::get(self::ADDRESS_DATA);
        if ($data) {
            return $data;
        }

        // 1. [唯一查询] 一次性获取全国所有地址数据
        $allList = Address::find()
            ->select(['province_code', 'province_name', 'city_code', 'city_name', 'county_code', 'county_name', 'town_code', 'town_name'])
            ->where(['!=', 'province_code', '']) // 过滤无效数据
            ->orderBy(['province_code' => SORT_ASC, 'city_code' => SORT_ASC, 'county_code' => SORT_ASC, 'town_code' => SORT_ASC])
            ->asArray()
            ->all();

        // 2. 在内存中高效构建树
        $provinceMap = [];
        $cityMap = [];
        $countyMap = [];

        foreach ($allList as $item) {
            // 跳过没有省级信息的数据
            if (empty($item['province_code'])) continue;

            // 处理省
            if (!isset($provinceMap[$item['province_code']])) {
                $provinceMap[$item['province_code']] = [
                    'code' => $item['province_code'],
                    'name' => $item['province_name'],
                    'level' => 1,
                    'list' => []
                ];
            }
            // 处理市
            if ($item['city_code'] && !isset($cityMap[$item['city_code']])) {
                $cityMap[$item['city_code']] = [
                    'code' => $item['city_code'],
                    'name' => $item['city_name'],
                    'level' => 2,
                    'list' => []
                ];
                $provinceMap[$item['province_code']]['list'][$item['city_code']] = &$cityMap[$item['city_code']];
            }
            // 处理区
            if ($item['county_code'] && !isset($countyMap[$item['county_code']])) {
                $countyMap[$item['county_code']] = [
                    'code' => $item['county_code'],
                    'name' => $item['county_name'],
                    'level' => 3,
                    'list' => []
                ];
                if (isset($cityMap[$item['city_code']])) {
                    $cityMap[$item['city_code']]['list'][$item['county_code']] = &$countyMap[$item['county_code']];
                }
            }
            // 处理镇
            if ($item['town_code'] && isset($countyMap[$item['county_code']])) {
                // 确保同一个镇只添加一次
                $townExists = false;
                foreach ($countyMap[$item['county_code']]['list'] as $town) {
                    if ($town['code'] == $item['town_code']) {
                        $townExists = true;
                        break;
                    }
                }
                if (!$townExists) {
                    $countyMap[$item['county_code']]['list'][] = [
                        'code' => $item['town_code'],
                        'name' => $item['town_name'],
                        'level' => 4
                    ];
                }
            }
        }

        // 3. 整理最终数据结构，去除用于映射的key
        $tree = [];
        foreach ($provinceMap as $province) {
            $cityList = [];
            if (!empty($province['list'])) {
                foreach ($province['list'] as $city) {
                    $countyList = [];
                    if (!empty($city['list'])) {
                        foreach ($city['list'] as $county) {
                            $countyList[] = $county;
                        }
                    }
                    $city['list'] = $countyList;
                    $cityList[] = $city;
                }
            }
            $province['list'] = $cityList;
            $tree[] = $province;
        }

        CacheHelper::set(self::ADDRESS_DATA, $tree, null);
        return $tree;
    }
}
