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
 * This is the model class for table "{{%mall_menu_option}}".
 *
 * @property int $id
 * @property int $mall_id 商城ID
 * @property string $menu_id 系统菜单ID
 * @property string|null $custom_name 商户自定义名称
 * @property int|null $is_hide 是否隐藏: 0否 1是
 * @property int|null $custom_sort 自定义排序
 * @property string|null $custom_parent_id 自定义父级ID
 * @property string|null $custom_path 自定义路由地址
 * @property int $is_home 是否为主页着陆点
 * @property int|null $created_at 创建时间
 * @property int|null $updated_at 更新时间
 */
class MallMenuOption extends BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%mall_menu_option}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'menu_id'], 'required'],
            [['mall_id', 'is_hide', 'custom_sort', 'is_home', 'created_at', 'updated_at'], 'integer'],
            [['menu_id', 'custom_name', 'custom_parent_id'], 'string', 'max' => 64],
            [['custom_path'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'mall_id' => '商城ID',
            'menu_id' => '系统菜单ID',
            'custom_name' => '自定义名称',
            'is_hide' => '是否隐藏',
            'custom_sort' => '自定义排序',
            'custom_parent_id' => '自定义父级ID',
            'custom_path' => '自定义路由地址',
            'is_home' => '是否为主页着陆点',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
        ];
    }


}