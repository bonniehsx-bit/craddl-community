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
 * This is the model class for table "{{%user_address}}".
 *
 * @property int $id
 * @property int $mall_id
 * @property int $user_id
 * @property string $name
 * @property string $address
 * @property int $is_delete
 * @property int|null $created_at
 * @property int|null $deleted_at
 * @property int|null $updated_at
 * @property string $province_code
 * @property string $city_code
 * @property string $county_code
 * @property string $town_code
 * @property int $is_default
 * @property string|null $province_name
 * @property string|null $city_name
 * @property string|null $county_name
 * @property string|null $town_name
 * @property string|null $mobile
 * @property string|null $area
 * @property string|null $detail
 * @property float|null $longitude
 * @property float|null $latitude
 * @property string|null $location
 */
class UserAddress extends BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%user_address}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'user_id', 'address', 'name', 'mobile'], 'required'],
            [['mall_id', 'user_id', 'is_delete', 'created_at', 'deleted_at', 'updated_at', 'is_default'], 'integer'],
            [['address', 'location'], 'string', 'max' => 255],
            [['mobile'], 'string', 'max' => 11],
            [['longitude', 'latitude'], 'number'],
            [['province_code', 'city_code', 'county_code', 'town_code'], 'string', 'max' => 20],
            [['province_name', 'city_name', 'county_name', 'town_name'], 'string', 'max' => 45],
            [['area', 'address', 'detail'], 'string', 'max' => 64],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'mall_id' => 'Mall ID',
            'user_id' => 'User ID',
            'address' => 'Address',
            'is_delete' => 'Is Delete',
            'created_at' => 'Created At',
            'deleted_at' => 'Deleted At',
            'updated_at' => 'Updated At',
            'province_code' => 'Province Code',
            'city_code' => 'City Code',
            'county_code' => 'County Code',
            'town_code' => 'Town Code',
            'is_default' => 'Is Default',
            'province_name' => 'Province Name',
            'city_name' => 'City Name',
            'county_name' => 'County Name',
            'town_name' => 'Town Name',
            'mobile' => 'Mobile',
            'name' => 'Name',
            'detail' => '详细地址',
            'area' => '所属区域',
            'longitude' => '经度',
            'latitude' => '纬度',
            'location' => '地理位置',
        ];
    }

    /**
     * [新增] 批量获取地址编码与名称的映射关系
     *
     * @param array $codes 地址编码数组
     * @return array ['code1' => 'name1', 'code2' => 'name2']
     */
    public static function getCodeMap(array $codes): array
    {
        // 1. 如果传入的数组为空，直接返回空数组，避免无效查询
        if (empty($codes)) {
            return [];
        }

        // 2. 构建查询，一次性从数据库中找出所有相关的地址信息
        // 使用 OR 条件，查询任何一个 code 字段匹配的记录
        $results = self::find()
            ->where([
                'or',
                ['in', 'province_code', $codes],
                ['in', 'city_code', $codes],
                ['in', 'county_code', $codes],
                ['in', 'town_code', $codes],
            ])
            ->select(['province_code', 'province_name', 'city_code', 'city_name', 'county_code', 'county_name', 'town_code', 'town_name'])
            ->asArray()
            ->all();

        // 3. 遍历查询结果，组装成 code => name 的映射数组
        $map = [];
        foreach ($results as $row) {
            if (!empty($row['province_code']) && !empty($row['province_name'])) {
                $map[$row['province_code']] = $row['province_name'];
            }
            if (!empty($row['city_code']) && !empty($row['city_name'])) {
                $map[$row['city_code']] = $row['city_name'];
            }
            if (!empty($row['county_code']) && !empty($row['county_name'])) {
                $map[$row['county_code']] = $row['county_name'];
            }
            if (!empty($row['town_code']) && !empty($row['town_name'])) {
                $map[$row['town_code']] = $row['town_name'];
            }
        }

        return $map;
    }
}