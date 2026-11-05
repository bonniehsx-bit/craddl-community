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
 * 多商户「招商分红」—— 商品级配置表。
 *
 * 商家推荐商家：被推荐商家卖出开启了招商分红的商品，订单完成后
 * 推荐商家按比例获得分红（单层、按百分比）。
 */
class m260521_160000_mch_recruit_goods extends Migration
{
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%plugin_mch_recruit_goods}}', [
            'id'        => $this->primaryKey(),
            'mall_id'   => $this->integer()->notNull(),
            'goods_id'  => $this->integer()->notNull()->comment('商品ID jxmall_goods.id'),
            'is_enable' => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('是否开启招商分红 0否 1是'),
            'rate'      => $this->decimal(5, 2)->notNull()->defaultValue('0.00')->comment('分红比例(%)'),
        ], $tableOptions);

        $this->createIndex('idx_mch_recruit_goods', '{{%plugin_mch_recruit_goods}}', ['mall_id', 'goods_id']);
    }

    public function safeDown()
    {
        return false;
    }
}
