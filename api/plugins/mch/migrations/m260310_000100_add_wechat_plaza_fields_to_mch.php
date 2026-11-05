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

class m260310_000100_add_wechat_plaza_fields_to_mch extends Migration
{
    public function safeUp()
    {
        $table = \Yii::$app->db->schema->getTableSchema('{{%plugin_mch}}');
        if ($table !== null && !isset($table->columns['wechat_mchid'])) {
            $this->addColumn('{{%plugin_mch}}', 'wechat_mchid', $this->string(64)->defaultValue('')->comment('微信支付商户号(商圈用)')->after('is_first_order_discount_enabled'));
        }
        if ($table !== null && !isset($table->columns['wechat_shop_number'])) {
            $this->addColumn('{{%plugin_mch}}', 'wechat_shop_number', $this->string(64)->defaultValue('')->comment('微信商圈门店编号')->after('wechat_mchid'));
            // 建立索引以支持商圈推送时的快速撞库
            $this->createIndex('idx_plugin_mch_wechat_shop', '{{%plugin_mch}}', ['mall_id', 'wechat_shop_number', 'is_delete']);
        }
    }

    public function safeDown()
    {
        $this->dropColumn('{{%plugin_mch}}', 'wechat_mchid');
        $this->dropColumn('{{%plugin_mch}}', 'wechat_shop_number');
    }
}
