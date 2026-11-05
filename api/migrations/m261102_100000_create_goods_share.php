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

/** 好物分享及加购幂等凭证，不修改现有购物车、订单或资金表。 */
class m261102_100000_create_goods_share extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%goods_share}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'token' => $this->string(32)->notNull(),
            'title' => $this->string(60)->notNull()->defaultValue('分享给你的好物'),
            'note' => $this->string(200)->notNull()->defaultValue(''),
            'items_json' => $this->text()->notNull(),
            'created_at' => $this->integer()->notNull()->defaultValue(0),
            'updated_at' => $this->integer()->notNull()->defaultValue(0),
            'deleted_at' => $this->integer()->notNull()->defaultValue(0),
            'is_delete' => $this->smallInteger()->notNull()->defaultValue(0),
        ]);
        $this->createIndex('uk_goods_share_token', '{{%goods_share}}', 'token', true);
        $this->createIndex('idx_goods_share_owner', '{{%goods_share}}', ['mall_id', 'user_id', 'is_delete', 'id']);
        $this->createTable('{{%goods_share_import}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'share_id' => $this->integer()->notNull(),
            'request_key' => $this->string(32)->notNull(),
            'request_hash' => $this->string(64)->notNull(),
            'result_json' => $this->text()->notNull(),
            'created_at' => $this->integer()->notNull()->defaultValue(0),
        ]);
        $this->createIndex('uk_goods_share_import_request', '{{%goods_share_import}}', ['mall_id', 'user_id', 'request_key'], true);
    }

    public function safeDown()
    {
        $this->dropTable('{{%goods_share_import}}');
        $this->dropTable('{{%goods_share}}');
    }
}
