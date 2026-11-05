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

namespace app\models;

use Yii;

/**
 * This is the model class for table "{{%address}}".
 *
 * @property int $id
 * @property string|null $province_code
 * @property string|null $province_name
 * @property string|null $city_code
 * @property string|null $city_name
 * @property string|null $county_code
 * @property string|null $county_name
 * @property string|null $town_code
 * @property string|null $town_name
 */
class Address extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%address}}';
    }

    // ======================= [核心新增方法] 开始 =======================

    /**
     * [高效] 根据一组行政区划代码，一次性查询并返回 Code -> Name 的映射数组
     *
     * @param array $codes 需要查询的行政区划代码数组
     * @return array [ '110000' => '北京市', '110101' => '东城区', ... ]
     */
    public static function getCodeMap(array $codes): array
    {
        // 如果传入的数组为空，直接返回空数组，避免无效查询
        if (empty($codes)) {
            return [];
        }

        // 去重，减少查询的数据量
        $uniqueCodes = array_unique($codes);

        // 使用 OR 条件，在一次查询中匹配所有级别的代码
        $query = self::find()->where([
            'or',
            ['province_code' => $uniqueCodes],
            ['city_code' => $uniqueCodes],
            ['county_code' => $uniqueCodes],
            ['town_code' => $uniqueCodes],
        ]);

        // 只选择需要的字段，提升查询效率
        $query->select(['province_code', 'province_name', 'city_code', 'city_name', 'county_code', 'county_name', 'town_code', 'town_name']);

        $addressData = $query->asArray()->all();

        $map = [];
        // 遍历查询结果，构建 Code -> Name 的映射
        foreach ($addressData as $addr) {
            // 从最具体的级别开始判断，确保下级名称能覆盖上级名称（例如一个code同时是市和区时）
            if (!empty($addr['town_code']) && !empty($addr['town_name'])) {
                $map[$addr['town_code']] = $addr['town_name'];
            }
            if (!empty($addr['county_code']) && !empty($addr['county_name'])) {
                $map[$addr['county_code']] = $addr['county_name'];
            }
            if (!empty($addr['city_code']) && !empty($addr['city_name'])) {
                $map[$addr['city_code']] = $addr['city_name'];
            }
            if (!empty($addr['province_code']) && !empty($addr['province_name'])) {
                $map[$addr['province_code']] = $addr['province_name'];
            }
        }

        return $map;
    }
    // ======================= [核心新增方法] 结束 =======================

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['province_code', 'city_code', 'county_code', 'town_code'], 'string', 'max' => 15],
            [['province_name', 'city_name', 'county_name', 'town_name'], 'string', 'max' => 45],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'province_code' => 'Province Code',
            'province_name' => 'Province Name',
            'city_code' => 'City Code',
            'city_name' => 'City Name',
            'county_code' => 'County Code',
            'county_name' => 'County Name',
            'town_code' => 'Town Code',
            'town_name' => 'Town Name',
        ];
    }
}
