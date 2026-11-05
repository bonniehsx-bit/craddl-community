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

class m260327_123000_plugin_mch_verify_log extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%plugin_mch_verify_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull(),
            'mch_id' => $this->integer()->notNull()->comment('多商户ID'),
            'clerk_id' => $this->integer()->notNull()->comment('核销员用户ID'),
            'clerk_name' => $this->string(255)->notNull()->defaultValue('')->comment('核销员昵称'),
            'verify_type' => $this->string(64)->notNull()->defaultValue('')->comment('核销业务类型: normal/voucher/yuda...'),
            'order_no' => $this->string(255)->notNull()->defaultValue('')->comment('关联订单号/核销码'),
            'products_snapshot' => $this->text()->comment('商品快照数据 JSON [{name, spec, num, image}]'),
            'remark' => $this->string(255)->notNull()->defaultValue('')->comment('核销备注'),
            'verify_at' => $this->integer()->notNull()->defaultValue(0)->comment('核销时间时间戳'),
            'is_delete' => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('删除标识'),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP')->comment('创建时间'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP')->comment('修改时间'),
        ]);

        $this->createIndex('idx_mch_id', '{{%plugin_mch_verify_log}}', 'mch_id');
        $this->createIndex('idx_clerk_id', '{{%plugin_mch_verify_log}}', 'clerk_id');
        $this->createIndex('idx_verify_type', '{{%plugin_mch_verify_log}}', 'verify_type');
    }

    public function safeDown()
    {
        $this->dropTable('{{%plugin_mch_verify_log}}');
    }
}
