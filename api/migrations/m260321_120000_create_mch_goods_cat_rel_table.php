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

class m260321_120000_create_mch_goods_cat_rel_table extends Migration
{
    public function up()
    {
        $this->createTable('{{%mch_goods_cat_rel}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->defaultValue(0)->comment('商城ID'),
            'mch_id' => $this->integer()->notNull()->defaultValue(0)->comment('商户ID'),
            'goods_id' => $this->integer()->notNull()->defaultValue(0)->comment('商品ID'),
            'cat_id' => $this->integer()->notNull()->defaultValue(0)->comment('分类ID'),
        ]);
        
        // createTable in app\core\Migration will automatically add:
        // is_delete, created_at, updated_at, deleted_at, created_time, updated_time, deleted_time
        // and compound indexes for mall_id + is_delete and user_id(if present).
    }

    public function down()
    {
        $this->dropTable('{{%mch_goods_cat_rel}}');
    }
}
