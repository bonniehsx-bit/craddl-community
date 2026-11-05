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
 * Handles adding columns to table `{{%common_pay_order}}`.
 */
class m260130_161000_add_is_cancel_to_common_pay_order_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // 1. 是否取消
        $this->addColumn(
            '{{%common_pay_order}}',
            'is_cancel',
            $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('是否取消 0:否 1:是')
        );

        // 2. 取消时间
        $this->addColumn(
            '{{%common_pay_order}}',
            'cancel_at',
            $this->integer()->null()->comment('取消时间')
        );

        // 3. 过期时间 (预计什么时候自动关闭)
        $this->addColumn(
            '{{%common_pay_order}}',
            'expire_at',
            $this->integer()->null()->comment('订单过期/自动关闭时间')
        );

        // 4. 【关键】给过期时间加索引
        // 定时任务会高频执行 range 查询 (expire_at <= time())，必须加索引
        $this->createIndex(
            'idx_common_pay_order_expire_at',
            '{{%common_pay_order}}',
            'expire_at'
        );

        // 可选：如果你数据量非常大，建议加复合索引 (is_pay + is_cancel + expire_at)
        // 但通常仅 expire_at 索引就足够快了
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // 删除索引
        $this->dropIndex('idx_common_pay_order_expire_at', '{{%common_pay_order}}');

        // 删除字段
        $this->dropColumn('{{%common_pay_order}}', 'expire_at');
        $this->dropColumn('{{%common_pay_order}}', 'cancel_at');
        $this->dropColumn('{{%common_pay_order}}', 'is_cancel');
    }
}