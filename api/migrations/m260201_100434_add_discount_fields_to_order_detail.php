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
 * 为订单详情表增加【首单优惠】和【平台补贴】字段
 * Handles adding columns to table `{{%order_detail}}`.
 */
class m260201_100434_add_discount_fields_to_order_detail extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $tableName = '{{%order_detail}}';

        // 1. 添加 [首单优惠金额]
        // 既然我们的计算逻辑依赖它，必须给个默认值 0.00，防止旧数据报错
        if ($this->db->getTableSchema($tableName)->getColumn('first_order_discount_price') === null) {
            $this->addColumn(
                $tableName,
                'first_order_discount_price',
                $this->decimal(10, 2)->notNull()->defaultValue(0.00)->comment('首单优惠分摊金额')
            );
        }

        // 2. 添加 [平台补贴/随机立减金额]
        // 刚才的 calculateMchIncome 代码里也用到了 platform_subsidy_cut_price
        // 如果表里已经有了，这里会自动跳过；如果没有，顺便加上
        if ($this->db->getTableSchema($tableName)->getColumn('platform_subsidy_cut_price') === null) {
            $this->addColumn(
                $tableName,
                'platform_subsidy_cut_price',
                $this->decimal(10, 2)->notNull()->defaultValue(0.00)->comment('平台补贴/随机立减分摊金额')
            );
        }

        echo " > order_detail table updated successfully.\n";
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $tableName = '{{%order_detail}}';

        if ($this->db->getTableSchema($tableName)->getColumn('first_order_discount_price') !== null) {
            $this->dropColumn($tableName, 'first_order_discount_price');
        }

        if ($this->db->getTableSchema($tableName)->getColumn('platform_subsidy_cut_price') !== null) {
            $this->dropColumn($tableName, 'platform_subsidy_cut_price');
        }
    }
}