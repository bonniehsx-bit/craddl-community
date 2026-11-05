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
 * This is the model class for table "{{%menu}}".
 *
 * @property int $id
 * @property string $menu_id
 * @property string|null $menu_name role_name
 * @property string|null $icon role_name
 * @property string|null $menu_type role_key
 * @property int|null $order_num order_num
 * @property string $parent_id
 * @property int|null $is_cache is_cache
 * @property int|null $hidden hidden
 * @property int|null $is_frame is_frame
 * @property int|null $status path
 * @property string|null $path 状态
 * @property string|null $component component
 * @property string|null $query query
 * @property string|null $perms perms
 * @property int|null $updated_at 更新时间
 * @property int $deleted_at 删除时间
 * @property int|null $created_at 创建时间
 * @property int|null $is_delete 是否删除
 * @property string $created_time 创建时间
 * @property string|null $link
 * @property integer $breadcrumb
 * @property integer $show_tag
 * @property string|null $active_menu
 * @property string $name 组件名称
 * @property string $plugin 插件
 */
class Menu extends BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%menu}}';
    }

    /**
     * 指定插件集合下的全部菜单 menu_id（未删）。统一"插件→菜单"查询。
     * @param string[] $plugins
     * @return string[]
     */
    public static function idsByPlugins(array $plugins): array
    {
        if (empty($plugins)) {
            return [];
        }
        return self::find()->where(['plugin' => $plugins, 'is_delete' => 0])->select('menu_id')->column();
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['order_num', 'show_tag', 'is_cache', 'hidden', 'is_frame', 'status', 'updated_at', 'deleted_at', 'created_at', 'is_delete', 'breadcrumb'], 'integer'],
            [['created_time'], 'safe'],
            [['menu_name', 'icon', 'menu_type', 'menu_id', 'parent_id','plugin'], 'string', 'max' => 45],
            [['active_menu', 'name'], 'string', 'max' => 64],
            [['path', 'component', 'query', 'perms', 'link'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'menu_name' => 'role_name',
            'icon' => 'role_name',
            'menu_type' => 'role_key',
            'order_num' => 'order_num',
            'is_cache' => 'is_cache',
            'hidden' => 'hidden',
            'is_frame' => 'is_frame',
            'status' => 'path',
            'path' => '状态',
            'component' => 'component',
            'query' => 'query',
            'perms' => 'perms',
            'updated_at' => '更新时间',
            'deleted_at' => '删除时间',
            'created_at' => '创建时间',
            'is_delete' => '是否删除',
            'created_time' => '创建时间',
            'link' => 'link',
            'breadcrumb' => '是否再面包屑中显示',
            'show_tag' => '显示标签',
            'active_menu' => '高亮菜单',
            'name' => '组件名称',
            'plugin'=>'插件'
        ];
    }
}
