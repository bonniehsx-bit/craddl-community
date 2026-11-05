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
 * Class m260523_250000_fix_logs_menu_route
 *
 * 修正日志管理菜单 logs-user / logs-admin 的路由问题（历史遗留）：
 *   1. path 误写成 'logs/user' / 'logs/admin'，与父菜单 logs(path='logs') 拼接后
 *      变成 /logs/logs/user，应改为相对 'user' / 'admin' → /logs/user
 *   2. active_menu 误写成 'logs/user'（可见菜单本不需要 active_menu，且路径还缺前导/）
 *      → 清空，避免侧边栏高亮错乱
 *   3. 两条菜单 name 都是 '日志管理'，Vue 路由 name 重复会冲突 → 改为唯一 name
 *
 * 组件 component（logs/user、logs/admin）正确，不改。
 */
class m260523_250000_fix_logs_menu_route extends Migration
{
    public function safeUp()
    {
        $this->update('{{%menu}}', [
            'path'        => 'user',
            'active_menu' => '',
            'name'        => 'logs-user',
            'order_num'   => 1,
        ], ['menu_id' => 'logs-user']);

        $this->update('{{%menu}}', [
            'path'        => 'admin',
            'active_menu' => '',
            'name'        => 'logs-admin',
            'order_num'   => 2,
        ], ['menu_id' => 'logs-admin']);
    }

    public function safeDown()
    {
        $this->update('{{%menu}}', [
            'path'        => 'logs/user',
            'active_menu' => 'logs/user',
            'name'        => '日志管理',
            'order_num'   => 1,
        ], ['menu_id' => 'logs-user']);

        $this->update('{{%menu}}', [
            'path'        => 'logs/admin',
            'active_menu' => 'logs/admin',
            'name'        => '日志管理',
            'order_num'   => 1,
        ], ['menu_id' => 'logs-admin']);
    }
}
