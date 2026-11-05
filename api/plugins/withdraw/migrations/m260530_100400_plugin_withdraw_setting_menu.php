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
 * 新增「提现设置」后台菜单（含操作说明 + 全局开关/须知配置）。
 * 前向迁移：先按 menu_id 清理再插入，可重复执行。
 */
class m260530_100400_plugin_withdraw_setting_menu extends Migration
{
    public function safeUp()
    {
        \app\models\Menu::deleteAll(['menu_id' => 'withdraw_setting']);

        $this->batchInsert('{{%menu}}',
            [
                'menu_id', 'menu_name', 'icon', 'menu_type', 'order_num', 'parent_id',
                'is_cache', 'hidden', 'is_frame', 'status', 'path', 'component', 'query',
                'perms', 'deleted_at', 'link', 'plugin', 'active_menu', 'breadcrumb', 'name', 'show_tag',
            ],
            [
                [
                    'menu_id' => 'withdraw_setting', 'menu_name' => '提现设置', 'icon' => 'edit',
                    'menu_type' => 'C', 'order_num' => '3', 'parent_id' => 'withdraw',
                    'is_cache' => '0', 'hidden' => '0', 'is_frame' => '1', 'status' => '0',
                    'path' => '/plugins/withdraw/setting/index', 'component' => 'plugins/withdraw/setting/index', 'query' => null,
                    'perms' => 'plugin/withdraw/admin/setting/index', 'deleted_at' => '0', 'link' => '', 'plugin' => 'withdraw',
                    'active_menu' => '', 'breadcrumb' => '1', 'name' => 'withdraw_setting', 'show_tag' => '1',
                ],
            ]
        );
    }

    public function safeDown()
    {
        \app\models\Menu::deleteAll(['menu_id' => 'withdraw_setting']);
    }
}
