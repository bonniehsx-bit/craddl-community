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
 * Auto-generated Migration via Console Command
 */
class m260329_032700_menu_add_dashboard extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // 插入数据概览菜单到 jxmall_menu
        $this->batchInsert('{{%menu}}', ['menu_id', 'menu_name', 'icon', 'menu_type', 'order_num', 'parent_id', 'is_cache', 'hidden', 'is_frame', 'status', 'path', 'component', 'query', 'perms', 'is_delete', 'created_at', 'updated_at', 'deleted_at', 'created_time', 'updated_time', 'deleted_time', 'link', 'plugin', 'active_menu', 'breadcrumb', 'name', 'show_tag'], [
            // 把数据概览收编进数据库，作为一个顶级外链页面（Layout包裹包裹的Component）
            ['dashboard', '数据概览', 'dashboard', 'C', -1, '0', 0, 1, 1, 0, 'index', 'index', NULL, NULL, 0, time(), time(), 0, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), NULL, '', 'mall', '', 0, 'Index', 1],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->delete('{{%menu}}', ['menu_id' => 'dashboard']);
    }
}
