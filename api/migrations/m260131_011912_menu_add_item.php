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
class m260131_011912_menu_add_item extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // 2. 插入数据: jxmall_menu
        // 注意：parent_id 已经同步修改为对应的英文 menu_id
        $this->batchInsert('{{%menu}}', ['menu_id', 'menu_name', 'icon', 'menu_type', 'order_num', 'parent_id', 'is_cache', 'hidden', 'is_frame', 'status', 'path', 'component', 'query', 'perms', 'is_delete', 'created_at', 'updated_at', 'deleted_at', 'created_time', 'updated_time', 'deleted_time', 'link', 'plugin', 'active_menu', 'breadcrumb', 'name', 'show_tag'], [
            // --- 系统管理 (System) ---
            ['finance-refund-log', '退款记录', 'excel', 'C', 0, 'finance', 1, 0, 1, 0, 'refund-log', 'finance/refund-log', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'finance-refund-log', 1],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%menu}}');
    }
}