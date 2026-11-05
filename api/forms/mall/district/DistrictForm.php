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
namespace app\forms\mall\district;

use app\core\ApiCode;
use app\models\Address;
use app\models\BaseModel;

class DistrictForm extends BaseModel
{
    public $mode = 'all';
    public $province_code;
    public $city_code;
    public $county_code;

    public function rules()
    {
        return [
            [['mode'], 'in', 'range' => ['all', 'province', 'city', 'district', 'town']],
            [['province_code', 'city_code', 'county_code'], 'string', 'max' => 15],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        switch ($this->mode) {
            case 'province':
                $list = $this->getProvinces();
                break;
            case 'city':
                $list = $this->getCities();
                break;
            case 'district':
                $list = $this->getDistricts();
                break;
            case 'town':
                $list = $this->getTowns();
                break;
            default: // all
                $list = $this->getFullTree();
                break;
        }

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => '获取成功',
            'data' => [ 'list' => $list ]
        ];
    }

    /**
     * [最终修正版] 高效获取完整的省市区镇树状结构
     * @return array
     */
    private function getFullTree(): array
    {
        // 1. 一次性从数据库加载所有地址数据
        $allDistricts = Address::find()->orderBy('id ASC')->asArray()->all();

        $tree = [];
        $map = []; // 使用一个映射来存储已创建节点的引用，[code => &node]

        // 2. 遍历每一条扁平数据，构建或复用层级节点
        foreach ($allDistricts as $item) {
            // --- 处理省 ---
            $provinceCode = $item['province_code'];
            if (empty($provinceCode)) continue; // 省代码是必须的

            if (!isset($map[$provinceCode])) {
                $tree[] = [
                    'value' => $provinceCode,
                    'label' => $item['province_name'],
                    'children' => [],
                ];
                // 将新创建的省的引用存入映射
                $map[$provinceCode] = &$tree[count($tree) - 1];
            }

            // --- 处理市 ---
            $cityCode = $item['city_code'];
            if (empty($cityCode)) continue;

            if (!isset($map[$cityCode])) {
                $map[$provinceCode]['children'][] = [
                    'value' => $cityCode,
                    'label' => $item['city_name'],
                    'children' => [],
                ];
                // 将新创建的市的引用存入映射
                $map[$cityCode] = &$map[$provinceCode]['children'][count($map[$provinceCode]['children']) - 1];
            }

            // --- 处理区县 ---
            $countyCode = $item['county_code'];
            if (empty($countyCode)) continue;

            if (!isset($map[$countyCode])) {
                $map[$cityCode]['children'][] = [
                    'value' => $countyCode,
                    'label' => $item['county_name'],
                    'children' => [],
                ];
                // 将新创建的区县的引用存入映射
                $map[$countyCode] = &$map[$cityCode]['children'][count($map[$cityCode]['children']) - 1];
            }

            // --- 处理镇 ---
            $townCode = $item['town_code'];
            if (empty($townCode)) continue;

            if (!isset($map[$townCode])) {
                $map[$countyCode]['children'][] = [
                    'value' => $townCode,
                    'label' => $item['town_name'],
                    // 镇是叶子节点，没有 children 属性
                ];
                // 镇是叶子节点，无需再将其引用存入 map
                $map[$townCode] = true; // 存入一个标记，避免重复添加
            }
        }

        return $tree;
    }

    // --- 其他模式的简单查询 (已完善) ---
    private function getProvinces(): array
    {
        $provinces = Address::find()
            ->select(['province_code as value', 'province_name as label'])
            ->where(['is not', 'province_code', null])
            ->andWhere(['city_code' => null, 'county_code' => null, 'town_code' => null])
            ->distinct()
            ->asArray()
            ->all();
        return $provinces;
    }

    private function getCities(): array
    {
        if (empty($this->province_code)) return [];
        $cities = Address::find()
            ->select(['city_code as value', 'city_name as label'])
            ->where(['province_code' => $this->province_code])
            ->andWhere(['is not', 'city_code', null])
            ->andWhere(['county_code' => null, 'town_code' => null])
            ->distinct()
            ->asArray()
            ->all();
        return $cities;
    }

    private function getDistricts(): array
    {
        if (empty($this->city_code)) return [];
        $districts = Address::find()
            ->select(['county_code as value', 'county_name as label'])
            ->where(['city_code' => $this->city_code])
            ->andWhere(['is not', 'county_code', null])
            ->andWhere(['town_code' => null])
            ->distinct()
            ->asArray()
            ->all();
        return $districts;
    }

    private function getTowns(): array
    {
        if (empty($this->county_code)) return [];
        $towns = Address::find()
            ->select(['town_code as value', 'town_name as label'])
            ->where(['county_code' => $this->county_code])
            ->andWhere(['is not', 'town_code', null])
            ->distinct()
            ->asArray()
            ->all();
        return $towns;
    }
}