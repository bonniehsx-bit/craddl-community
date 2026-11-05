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
 * 营销优惠券插件菜单(平台后台)
 */
class m260703_110100_marketing_menus extends Migration
{
    public function safeUp()
    {
        \app\models\Menu::deleteAll(['plugin' => 'marketing']);

        $columns = [
            "menu_id", "menu_name", "icon", "menu_type", "order_num", "parent_id",
            "is_cache", "hidden", "is_frame", "status", "path", "component", "query",
            "perms", "deleted_at", "link", "plugin", "active_menu", "breadcrumb",
            "name", "show_tag",
        ];

        $this->batchInsert('{{%menu}}', $columns, [
            [
                'menu_id' => 'marketing', 'menu_name' => '营销优惠券', 'icon' => 'el-icon-present',
                'menu_type' => 'M', 'order_num' => '17', 'parent_id' => 'plugins',
                'is_cache' => '0', 'hidden' => '0', 'is_frame' => '1', 'status' => '0',
                'path' => '/plugins/marketing', 'component' => null, 'query' => null, 'perms' => null,
                'deleted_at' => '0', 'link' => '', 'plugin' => 'marketing', 'active_menu' => '',
                'breadcrumb' => '1', 'name' => 'marketing', 'show_tag' => '0',
            ],
            [
                'menu_id' => 'marketing_campaign', 'menu_name' => '营销活动', 'icon' => 'el-icon-set-up',
                'menu_type' => 'C', 'order_num' => '0', 'parent_id' => 'marketing',
                'is_cache' => '0', 'hidden' => '0', 'is_frame' => '1', 'status' => '0',
                'path' => '/plugins/marketing/campaign/index', 'component' => 'plugins/marketing/campaign/index',
                'query' => null, 'perms' => 'plugin/marketing/mall/campaign/index',
                'deleted_at' => '0', 'link' => '', 'plugin' => 'marketing', 'active_menu' => '',
                'breadcrumb' => '1', 'name' => 'marketing_campaign', 'show_tag' => '1',
            ],
            [
                'menu_id' => 'marketing_grant', 'menu_name' => '发放流水', 'icon' => 'el-icon-tickets',
                'menu_type' => 'C', 'order_num' => '1', 'parent_id' => 'marketing',
                'is_cache' => '0', 'hidden' => '0', 'is_frame' => '1', 'status' => '0',
                'path' => '/plugins/marketing/grant/index', 'component' => 'plugins/marketing/grant/index',
                'query' => null, 'perms' => 'plugin/marketing/mall/grant/index',
                'deleted_at' => '0', 'link' => '', 'plugin' => 'marketing', 'active_menu' => '',
                'breadcrumb' => '1', 'name' => 'marketing_grant', 'show_tag' => '1',
            ],
            [
                'menu_id' => 'marketing_dashboard', 'menu_name' => '效果看板', 'icon' => 'el-icon-data-line',
                'menu_type' => 'C', 'order_num' => '2', 'parent_id' => 'marketing',
                'is_cache' => '0', 'hidden' => '0', 'is_frame' => '1', 'status' => '0',
                'path' => '/plugins/marketing/dashboard/index', 'component' => 'plugins/marketing/dashboard/index',
                'query' => null, 'perms' => 'plugin/marketing/mall/stat/overview',
                'deleted_at' => '0', 'link' => '', 'plugin' => 'marketing', 'active_menu' => '',
                'breadcrumb' => '1', 'name' => 'marketing_dashboard', 'show_tag' => '1',
            ],
        ]);
    }

    public function safeDown()
    {
        \app\models\Menu::deleteAll(['plugin' => 'marketing']);
    }
}
