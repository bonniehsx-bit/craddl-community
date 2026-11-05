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
 * 为订单详情表增加【支付随机减】字段
 *
 * 配合优惠链路重构（platform_subsidy_cut_price 字段废弃，
 * 平台补贴的支付随机减改为独立字段 platform_subsidy_random_minus）。
 * Order 表已存在该字段，本迁移补齐 OrderDetail 表的对称列。
 */
class m260414_100000_add_random_minus_to_order_detail extends Migration
{
    public function safeUp()
    {
        $tableName = '{{%order_detail}}';

        if ($this->db->getTableSchema($tableName)->getColumn('platform_subsidy_random_minus') === null) {
            $this->addColumn(
                $tableName,
                'platform_subsidy_random_minus',
                $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('分摊到此商品的支付随机减(平台补贴)')
            );
        }

        echo " > order_detail.platform_subsidy_random_minus added.\n";
    }

    public function safeDown()
    {
        $tableName = '{{%order_detail}}';

        if ($this->db->getTableSchema($tableName)->getColumn('platform_subsidy_random_minus') !== null) {
            $this->dropColumn($tableName, 'platform_subsidy_random_minus');
        }
    }
}
