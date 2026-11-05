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
 * Handles the creation of table `{{%common_pay_refund}}`.
 * 用于处理退款流水记录，支持多次部分退款
 */
class m260130_235500_create_common_pay_refund_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%common_pay_refund}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'user_id' => $this->integer()->notNull()->comment('用户ID'),

            // 核心关联
            'common_pay_order_id' => $this->integer()->notNull()->comment('关联的支付主单ID'),
            'order_no' => $this->string(64)->notNull()->comment('商户订单号(冗余字段)'),

            // 支付相关
            'out_refund_no' => $this->string(64)->notNull()->comment('商户退款单号(每次退款唯一)'),
            'refund_price' => $this->decimal(10, 2)->notNull()->comment('本次退款金额'),

            // 业务来源
            'source_type' => $this->string(32)->notNull()->defaultValue('')->comment('来源: cancel_order=取消订单, after_sales=售后'),
            'source_id' => $this->integer()->notNull()->defaultValue(0)->comment('来源ID (如order_refund_id)'),

            // 状态与结果
            'status' => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('0=待处理, 1=处理中, 2=退款成功, 3=退款失败'),
            'error_msg' => $this->string(512)->defaultValue('')->comment('失败原因'),

            // 基础字段
        ], $tableOptions);

        // --- 索引优化 ---

        // 1. 确保退款单号唯一 (防止重复退款)
        $this->createIndex(
            'idx_out_refund_no',
            '{{%common_pay_refund}}',
            'out_refund_no',
            true
        );

        // 2. 加速查询某笔订单的所有退款记录
        $this->createIndex(
            'idx_common_pay_order_id',
            '{{%common_pay_refund}}',
            'common_pay_order_id'
        );

        // 3. 加速查询某个业务来源的退款 (例如查询某个售后单是否已生成退款任务)
        $this->createIndex(
            'idx_source',
            '{{%common_pay_refund}}',
            ['source_type', 'source_id']
        );

        // 4. 加速定时任务查询 (只查未删除且状态为0或1的)
        $this->createIndex(
            'idx_status_delete',
            '{{%common_pay_refund}}',
            ['status', 'is_delete']
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%common_pay_refund}}');
    }
}