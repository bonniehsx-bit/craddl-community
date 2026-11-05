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
 * 提现插件 —— 后台菜单（自包含、可重复执行）
 *
 * 挂载在「插件中心」(plugins) 下：
 *   提现中心 (withdraw)
 *     ├─ 提现渠道 (withdraw_channel)  → plugin/withdraw/admin/channel/index
 *     └─ 提现记录 (withdraw_order)    → plugin/withdraw/admin/order/index
 */
class m260530_100100_plugin_withdraw_menus extends Migration
{
    public function safeUp()
    {
        // 自包含：先清理本插件旧菜单，防重复安装主键冲突
        \app\models\Menu::deleteAll(['plugin' => 'withdraw']);

        $this->batchInsert('{{%menu}}',
            [
                'menu_id', 'menu_name', 'icon', 'menu_type', 'order_num', 'parent_id',
                'is_cache', 'hidden', 'is_frame', 'status', 'path', 'component', 'query',
                'perms', 'deleted_at', 'link', 'plugin', 'active_menu', 'breadcrumb', 'name', 'show_tag',
            ],
            [
                // ── 目录：提现中心 ──
                [
                    'menu_id' => 'withdraw', 'menu_name' => '提现中心', 'icon' => 'money',
                    'menu_type' => 'M', 'order_num' => '1', 'parent_id' => 'plugins',
                    'is_cache' => '0', 'hidden' => '0', 'is_frame' => '1', 'status' => '0',
                    'path' => '/plugins/withdraw', 'component' => null, 'query' => null,
                    'perms' => null, 'deleted_at' => '0', 'link' => '', 'plugin' => 'withdraw',
                    'active_menu' => '', 'breadcrumb' => '1', 'name' => 'withdraw', 'show_tag' => '0',
                ],
                // ── 提现渠道 ──
                [
                    'menu_id' => 'withdraw_channel', 'menu_name' => '提现渠道', 'icon' => 'tool',
                    'menu_type' => 'C', 'order_num' => '1', 'parent_id' => 'withdraw',
                    'is_cache' => '0', 'hidden' => '0', 'is_frame' => '1', 'status' => '0',
                    'path' => '/plugins/withdraw/channel/index', 'component' => 'plugins/withdraw/channel/index', 'query' => null,
                    'perms' => 'plugin/withdraw/admin/channel/index', 'deleted_at' => '0', 'link' => '', 'plugin' => 'withdraw',
                    'active_menu' => '', 'breadcrumb' => '1', 'name' => 'withdraw_channel', 'show_tag' => '1',
                ],
                // ── 提现记录 ──
                [
                    'menu_id' => 'withdraw_order', 'menu_name' => '提现记录', 'icon' => 'list',
                    'menu_type' => 'C', 'order_num' => '2', 'parent_id' => 'withdraw',
                    'is_cache' => '0', 'hidden' => '0', 'is_frame' => '1', 'status' => '0',
                    'path' => '/plugins/withdraw/order/index', 'component' => 'plugins/withdraw/order/index', 'query' => null,
                    'perms' => 'plugin/withdraw/admin/order/index', 'deleted_at' => '0', 'link' => '', 'plugin' => 'withdraw',
                    'active_menu' => '', 'breadcrumb' => '1', 'name' => 'withdraw_order', 'show_tag' => '1',
                ],
            ]
        );
    }

    public function safeDown()
    {
        \app\models\Menu::deleteAll(['plugin' => 'withdraw']);
    }
}
