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
 * SKU 规格表 jxmall_attr 新增「采购价」字段。
 *
 * 多规格商品的采购单元是 SKU —— 采购价需精确到每个规格，
 * 与 jxmall_goods.purchase_price 构成「商品级 / SKU级」双层结构（同 cost_price）。
 */
class m260521_170000_add_purchase_price_to_attr extends Migration
{
    public function safeUp()
    {
        $dbName = $this->db->createCommand('SELECT DATABASE()')->queryScalar();
        $real   = Yii::$app->db->tablePrefix . 'attr';
        $has    = (bool) $this->db->createCommand(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=:s AND TABLE_NAME=:t AND COLUMN_NAME=:c LIMIT 1'
        )->bindValues([':s' => $dbName, ':t' => $real, ':c' => 'purchase_price'])->queryScalar();

        if (!$has) {
            $this->addColumn(
                '{{%attr}}',
                'purchase_price',
                $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('采购价（商家拿货价，元）')
            );
        }
    }

    public function safeDown()
    {
        return false;
    }
}
