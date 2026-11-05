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
 * Class m260131_103500_update_commission_log_table
 */
class m260131_103500_update_commission_log_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $table = '{{%commission_log}}';

        // 1. 新增：审计与备注字段
        // calculation_price: 计算基数 (允许为空)
        $this->addColumn($table, 'calculation_price', $this->decimal(10, 2)->null()->comment('计算基数金额'));

        // commission_rate: 比例/固定值 (允许为空)
        $this->addColumn($table, 'commission_rate', $this->decimal(10, 2)->null()->comment('分佣比例(%)或固定金额'));

        // remark: 备注 (允许为空)
        $this->addColumn($table, 'remark', $this->string(255)->null()->defaultValue('')->comment('备注说明'));

        // 2. 修改：允许关联ID为空 (默认值设为0，移除 NotNull 限制)
        // 这样就可以存储“团队分红”等没有具体订单ID的记录了
        $this->alterColumn($table, 'order_id', $this->integer()->defaultValue(0)->comment('关联订单ID'));
        $this->alterColumn($table, 'order_detail_id', $this->integer()->defaultValue(0)->comment('关联订单详情ID'));
        $this->alterColumn($table, 'buy_user_id', $this->integer()->defaultValue(0)->comment('购买者/触发者ID'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $table = '{{%commission_log}}';

        // 1. 回滚：删除新增字段
        $this->dropColumn($table, 'calculation_price');
        $this->dropColumn($table, 'commission_rate');
        $this->dropColumn($table, 'remark');

        // 2. 回滚：旧字段改回 NotNull (注意：如果表中已经有了 ID=0 的数据，这一步回滚会报错，但在开发阶段通常无所谓)
        // 如果你不确定回滚逻辑，safeDown 可以留空或者只删字段
        $this->alterColumn($table, 'order_id', $this->integer()->notNull()->comment('关联订单ID'));
        $this->alterColumn($table, 'order_detail_id', $this->integer()->notNull()->comment('关联订单详情ID'));
        $this->alterColumn($table, 'buy_user_id', $this->integer()->notNull()->comment('购买者用户ID'));
    }
}