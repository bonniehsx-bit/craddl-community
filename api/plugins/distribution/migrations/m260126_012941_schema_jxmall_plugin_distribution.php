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
 * Handles the creation of tables from existing database.
 */
class m260126_012941_schema_jxmall_plugin_distribution extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // 创建表: jxmall_plugin_distribution_goods
        $this->createTable('{{%plugin_distribution_goods}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull(),
            'goods_id' => $this->integer()->notNull(),
            'is_commission_open' => $this->tinyInteger()->null()->defaultValue(0)->comment('是否开启分销 0否 1是'),
            'commission_rule_type' => $this->string(32)->null()->defaultValue('default')->comment('规则类型: default/custom'),
            'commission_rules' => $this->text()->null()->comment('自定义规则JSON')
        ]);

        $this->createIndex('idx_goods_id', '{{%plugin_distribution_goods}}', ['goods_id'], false);


    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%plugin_distribution_goods}}');

    }
}