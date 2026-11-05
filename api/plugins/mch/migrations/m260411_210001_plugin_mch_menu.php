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

use app\core\Migration;

/**
 * Handles the creation of menus for the mch plugin using batchInsert.
 */
class m260411_210001_plugin_mch_menu extends Migration
{
    public function init()
    {
        $this->db = 'db';
        parent::init();
    }

    public function safeUp()
    {
        \app\models\Menu::deleteAll(['plugin' => 'mch']);
        $this->batchInsert(
            '{{%menu}}',
            [
                "menu_id",
                "menu_name",
                "icon",
                "menu_type",
                "order_num",
                "parent_id",
                "is_cache",
                "hidden",
                "is_frame",
                "status",
                "path",
                "component",
                "name",
                "plugin",
                "perms",
                "breadcrumb",
                "show_tag",
                "active_menu",
                "query"
            ],
            [
                // --- 1. "多商户" 顶级目录 ---
                [
                    'menu_id' => 'mch',
                    'menu_name' => '多商户',
                    'icon' => 'el-icon-s-shop',
                    'menu_type' => 'M',
                    'order_num' => '18',
                    'parent_id' => 'plugins',
                    'is_cache' => '1',
                    'hidden' => '0',
                    'is_frame' => '1',
                    'status' => '0',
                    'path' => '/plugins/mch',
                    'component' => 'Layout',
                    'name' => 'Mch',
                    'plugin' => 'mch',
                    'perms' => '',
                    'breadcrumb' => '1',
                    'show_tag' => '1',
                    'active_menu' => '',
                    'query' => '',
                ],

                // --- 2. "数据看板" 菜单项 ---
                [
                    'menu_id' => 'mch-dashboard',
                    'menu_name' => '数据看板',
                    'icon' => 'el-icon-data-board',
                    'menu_type' => 'C',
                    'order_num' => '1',
                    'parent_id' => 'mch',
                    'is_cache' => '1',
                    'hidden' => '0',
                    'is_frame' => '1',
                    'status' => '0',
                    'path' => '/plugins/mch/index',
                    'component' => 'plugins/mch/index',
                    'name' => 'plugins-mch-index',
                    'plugin' => 'mch',
                    'perms' => '',
                    'breadcrumb' => '1',
                    'show_tag' => '1',
                    'active_menu' => '',
                    'query' => '',
                ],

                [
                    'menu_id' => 'mch-goods-cat',
                    'menu_name' => '商品分类',
                    'icon' => 'el-icon-menu',
                    'menu_type' => 'C',
                    'order_num' => '1',
                    'parent_id' => 'mch',
                    'is_cache' => '1',
                    'hidden' => '0',
                    'is_frame' => '1',
                    'status' => '0',
                    'path' => '/plugins/mch/goods-cat',
                    'component' => 'plugins/mch/goods-cat',
                    'name' => 'plugins-mch-goods-cat',
                    'plugin' => 'mch',
                    'perms' => '',
                    'breadcrumb' => '1',
                    'show_tag' => '1',
                    'active_menu' => '/plugins/mch/goods-cat',
                    'query' => '',
                ],
                [
                    'menu_id' => 'mch-delivery-task',
                    'menu_name' => '配送任务',
                    'icon' => 'el-icon-truck',
                    'menu_type' => 'C',
                    'order_num' => '1',
                    'parent_id' => 'mch',
                    'is_cache' => '1',
                    'hidden' => '0',
                    'is_frame' => '1',
                    'status' => '0',
                    'path' => '/plugins/mch/delivery-task',
                    'component' => 'plugins/mch/delivery-task',
                    'name' => 'plugins-mch-delivery-task',
                    'plugin' => 'mch',
                    'perms' => '',
                    'breadcrumb' => '1',
                    'show_tag' => '1',
                    'active_menu' => '/plugins/mch/delivery-task',
                    'query' => '',
                ],
                [
                    'menu_id' => 'mch-delivery-log',
                    'menu_name' => '配送记录',
                    'icon' => 'el-icon-document-checked',
                    'menu_type' => 'C',
                    'order_num' => '2',
                    'parent_id' => 'mch',
                    'is_cache' => '1',
                    'hidden' => '0',
                    'is_frame' => '1',
                    'status' => '0',
                    'path' => '/plugins/mch/delivery-log',
                    'component' => 'plugins/mch/delivery-log',
                    'name' => 'plugins-mch-delivery-log',
                    'plugin' => 'mch',
                    'perms' => '',
                    'breadcrumb' => '1',
                    'show_tag' => '1',
                    'active_menu' => '/plugins/mch/delivery-log',
                    'query' => '',
                ],
                // --- 3. "商户管理" 菜单项 ---
                [
                    'menu_id' => 'mch-manage',
                    'menu_name' => '商户管理',
                    'icon' => 'el-icon-user-solid',
                    'menu_type' => 'C',
                    'order_num' => '5',
                    'parent_id' => 'mch',
                    'is_cache' => '1',
                    'hidden' => '0',
                    'is_frame' => '1',
                    'status' => '0',
                    'path' => '/plugins/mch/mch-list',
                    'component' => 'plugins/mch/mch-list',
                    'name' => 'plugins-mch-mch-list',
                    'plugin' => 'mch',
                    'perms' => '',
                    'breadcrumb' => '1',
                    'show_tag' => '1',
                    'active_menu' => '',
                    'query' => '',
                ],
                [
                    'menu_id' => 'mch-cat',
                    'menu_name' => '商家分类',
                    'icon' => 'el-icon-s-grid', // 使用树形图标
                    'menu_type' => 'C',
                    'order_num' => '10', // 排序在商户管理之后
                    'parent_id' => 'mch',
                    'is_cache' => '1',
                    'hidden' => '0',
                    'is_frame' => '1',
                    'status' => '0',
                    'path' => '/plugins/mch/mch-cat',
                    'component' => 'plugins/mch/mch-cat',
                    'name' => 'plugins-mch-mch-cat',
                    'plugin' => 'mch',
                    'perms' => '',
                    'breadcrumb' => '1',
                    'show_tag' => '1',
                    'active_menu' => '',
                    'query' => '',
                ],
                [
                    'menu_id' => 'mch-purchase',
                    'menu_name' => '商家采购',
                    'icon' => 'el-icon-shopping-cart-full', // 使用树形图标
                    'menu_type' => 'C',
                    'order_num' => '10', // 排序在商户管理之后
                    'parent_id' => 'mch',
                    'is_cache' => '1',
                    'hidden' => '0',
                    'is_frame' => '1',
                    'status' => '0',
                    'path' => '/plugins/mch/mch-purchase',
                    'component' => 'plugins/mch/purchase',
                    'name' => 'plugins-mch-purchase',
                    'plugin' => 'mch',
                    'perms' => '',
                    'breadcrumb' => '1',
                    'show_tag' => '1',
                    'active_menu' => '',
                    'query' => '',
                ],
                [
                    'menu_id' => 'mch-workspace',
                    'menu_name' => '商家中心',
                    'icon' => 'el-icon-mobile',
                    'menu_type' => 'C',
                    'order_num' => '20',
                    'parent_id' => 'mch',
                    'is_cache' => '1',
                    'hidden' => '0',
                    'is_frame' => '1',
                    'status' => '0',
                    'path' => '/plugins/mch/workspace',
                    'component' => 'plugins/mch/workspace',
                    'name' => 'plugins-mch-workspace',
                    'plugin' => 'mch',
                    'perms' => '',
                    'breadcrumb' => '1',
                    'show_tag' => '1',
                    'active_menu' => '',
                    'query' => '',
                ],
                [
                    'menu_id' => 'mch-setting',
                    'menu_name' => '插件配置',
                    'icon' => 'el-icon-setting',
                    'menu_type' => 'C',
                    'order_num' => '25',
                    'parent_id' => 'mch',
                    'is_cache' => '1',
                    'hidden' => '0',
                    'is_frame' => '1',
                    'status' => '0',
                    'path' => '/plugins/mch/setting',
                    'component' => 'plugins/mch/setting',
                    'name' => 'plugins-mch-setting',
                    'plugin' => 'mch',
                    'perms' => '',
                    'breadcrumb' => '1',
                    'show_tag' => '1',
                    'active_menu' => '',
                    'query' => '',
                ],
            ]
        );
    }

    public function safeDown()
    {
        // 安全地删除本插件创建的所有菜单
        $this->delete('{{%menu}}', ['plugin' => 'mch']);
    }
}
