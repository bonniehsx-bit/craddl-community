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

class m260329_160000_create_admin_plugin_shortcut_table extends Migration
{
    public function up()
    {
        $this->createTable('{{%admin_plugin_shortcut}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull(),
            'admin_id' => $this->integer()->notNull(),
            'plugin_name' => $this->string(64)->notNull(),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
            'is_delete' => $this->tinyInteger(1)->notNull()->defaultValue(0),
        ]);

        $this->createIndex('idx-admin_plugin_shortcut-mall_id', '{{%admin_plugin_shortcut}}', 'mall_id');
        $this->createIndex('idx-admin_plugin_shortcut-admin_id', '{{%admin_plugin_shortcut}}', 'admin_id');
        $this->createIndex('idx-admin_plugin_shortcut-plugin_name', '{{%admin_plugin_shortcut}}', 'plugin_name');
    }

    public function down()
    {
        $this->dropTable('{{%admin_plugin_shortcut}}');
    }
}
