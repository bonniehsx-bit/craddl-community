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
 * 创建表 jxmall_plugin_mch_member_remark —— mch 插件商家对会员的私有备注
 *
 * 业务定位：
 *   每个商家可对自己的会员（user）打一段备注，仅商家可见；
 *   唯一索引 (mch_id, user_id) 保证每个商家对每个会员只有一条备注。
 *
 * 历史：
 *   该表此前位于 api/migrations/m260101_000000_exclude_jxmall_plugin_.php，
 *   表名为 jxmall_mch_member_remark，不符合 plugin_mch_ 前缀规范。
 *   本次将建表逻辑下沉到 mch 插件自身的 migrations，并对齐命名规范。
 */
class m260523_170000_create_member_remark extends Migration
{
    public function safeUp()
    {
        $exists = $this->db->schema->getTableSchema('{{%plugin_mch_member_remark}}', true);
        if ($exists !== null) {
            return;
        }

        $this->createTable('{{%plugin_mch_member_remark}}', [
            'id'      => $this->primaryKey(),
            'mall_id' => $this->integer()->unsigned()->notNull(),
            'mch_id'  => $this->integer()->unsigned()->notNull()->comment('商家ID'),
            'user_id' => $this->integer()->unsigned()->notNull()->comment('会员用户ID'),
            'remark'  => $this->string(500)->notNull()->defaultValue('')->comment('备注内容'),
        ]);

        $this->createIndex(
            'uidx-mch_member_remark-mch_user',
            '{{%plugin_mch_member_remark}}',
            ['mch_id', 'user_id'],
            true
        );
    }

    public function safeDown()
    {
        $exists = $this->db->schema->getTableSchema('{{%plugin_mch_member_remark}}', true);
        if ($exists !== null) {
            $this->dropTable('{{%plugin_mch_member_remark}}');
        }
    }
}
