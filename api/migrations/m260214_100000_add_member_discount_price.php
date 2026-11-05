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
 * Class m260214_100000_add_member_discount_price
 * @description 完善订单财务体系：增加会员价优惠差额记录
 */
class m260214_100000_add_member_discount_price extends Migration
{
    public function safeUp()
    {
        // 1. 给主订单表增加“会员总优惠”字段 (用于用户端的订单结算页展示和总账核对)
        $this->addColumn('{{%order}}', 'member_discount_price',
            $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('会员折扣优惠金额')
        );

        // 2. 给订单详情表增加“单件会员优惠”字段 (用于售后部分退款时的精准分摊计算)
        $this->addColumn('{{%order_detail}}', 'member_discount_price',
            $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('单件商品的会员优惠金额')
        );
    }

    public function safeDown()
    {
        // 支持回滚操作
        $this->dropColumn('{{%order}}', 'member_discount_price');
        $this->dropColumn('{{%order_detail}}', 'member_discount_price');
    }
}