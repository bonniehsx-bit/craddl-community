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
 * 为 common_pay_refund 表增加 business_synced_at 字段，作为"业务闭环完成"幂等锁。
 *
 * 背景：退款网关成功时 cron/notify 会把 status 改成 SUCCESS，并调用
 *       RefundSuccessDispatcher::dispatch() 同步业务状态（关单/售后完成/佣金扣回）。
 *       如果 dispatch 抛异常，catch 只打日志不重试，导致订单状态永远无法闭环。
 *
 *       引入 business_synced_at 字段：
 *         - dispatch 成功后才设置
 *         - cron 扫描条件扩展"status=SUCCESS AND business_synced_at=0"的记录用于补做
 *         - notify 也可据此幂等判断
 */
class m260420_090000_common_pay_refund_business_synced extends Migration
{
    public function safeUp()
    {
        $table = \Yii::$app->db->schema->getTableSchema('{{%common_pay_refund}}');
        if ($table && !isset($table->columns['business_synced_at'])) {
            $this->addColumn(
                '{{%common_pay_refund}}',
                'business_synced_at',
                $this->integer()->null()->defaultValue(0)->comment('业务闭环完成时间戳；非 0 表示 dispatch 已执行')
            );

            // 历史数据：已 SUCCESS 的流水视为业务已闭环（避免本次 migration 后扫描到大量历史记录尝试补做）
            $this->execute("UPDATE {{%common_pay_refund}} SET business_synced_at = COALESCE(success_at, UNIX_TIMESTAMP()) WHERE status = 2 AND is_delete = 0");
        }
    }

    public function safeDown()
    {
        $table = \Yii::$app->db->schema->getTableSchema('{{%common_pay_refund}}');
        if ($table && isset($table->columns['business_synced_at'])) {
            $this->dropColumn('{{%common_pay_refund}}', 'business_synced_at');
        }
    }
}
