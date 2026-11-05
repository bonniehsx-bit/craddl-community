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
 * 用户等级授予台账(带有效期的升级才写)
 * 任何插件给用户升级且需要"到期降级"时,写一条记录;核心 cron 统一扫描降级。
 */
class m260606_100000_create_user_level_grant extends Migration
{
    public function safeUp()
    {
        if (\Yii::$app->db->schema->getTableSchema('{{%user_level_grant}}') !== null) {
            return;
        }
        $this->createTable('{{%user_level_grant}}', [
            'id'                  => $this->primaryKey(),
            'mall_id'             => $this->integer()->notNull()->comment('商城ID'),
            'user_id'             => $this->integer()->notNull()->comment('用户ID'),
            'level_id'            => $this->integer()->notNull()->comment('本次授予的等级ID'),
            'level_weight'        => $this->integer()->notNull()->defaultValue(0)->comment('授予等级权重(冗余,降级比较用)'),
            'before_level_id'     => $this->integer()->notNull()->defaultValue(0)->comment('授予前等级ID(降级回退目标)'),
            'before_level_weight' => $this->integer()->notNull()->defaultValue(0)->comment('授予前等级权重'),
            'grant_at'            => $this->integer()->notNull()->defaultValue(0)->comment('授予时间'),
            'expire_at'           => $this->integer()->notNull()->defaultValue(0)->comment('降级时间(到点降级)'),
            'status'              => $this->tinyInteger(1)->notNull()->defaultValue(1)->comment('1有效 0已降级 2已撤销'),
            'source'              => $this->string(40)->notNull()->defaultValue('')->comment('来源:emotion_code/emotion_buy 等'),
            'biz_no'              => $this->string(64)->notNull()->defaultValue('')->comment('来源单号/码,退款撤销用'),
            'remark'              => $this->string(255)->notNull()->defaultValue('')->comment('备注'),
        ], null, '用户等级授予台账(带有效期)');

        $this->createIndex('idx_grant_due', '{{%user_level_grant}}', ['mall_id', 'status', 'expire_at']);
        $this->createIndex('idx_grant_user', '{{%user_level_grant}}', ['mall_id', 'user_id', 'status']);
        $this->createIndex('idx_grant_biz', '{{%user_level_grant}}', ['mall_id', 'source', 'biz_no']);
    }

    public function safeDown()
    {
        $this->dropTable('{{%user_level_grant}}');
    }
}
