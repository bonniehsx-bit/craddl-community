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
 * 分销佣金 T+N 延迟结算计划表。
 *
 * 与 dealer 的 plugin_dealer_settle_plan 同构：分润统一落核心 CommissionLog，
 * 不给核心表加列（核心不该为某个插件的结算节奏买单），
 * 用这张插件自有的小表记「哪条流水该在什么时候发」：
 *   即时/订单完成档：不写本表，沿用原有 settlement_timing 链路
 *   T+N 档：计提时写一条 due_at=计提时间+N天，DistributionSettleDueTask 到期发放
 * 退款回滚会把对应 CommissionLog 置 INVALID，发放时跳过并把计划标记作废。
 */
class m260819_170000_create_distribution_settle_plan_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%plugin_distribution_settle_plan}}', [
            'id'                => $this->primaryKey(),
            'mall_id'           => $this->integer()->notNull(),
            'commission_log_id' => $this->integer()->notNull()->comment('对应核心分润流水ID'),
            'order_id'          => $this->integer()->notNull()->defaultValue(0),
            'user_id'           => $this->integer()->notNull()->defaultValue(0)->comment('获佣用户'),
            'due_at'            => $this->integer()->notNull()->defaultValue(0)->comment('应发放时间=计提时间+N天'),
            'status'            => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('0=待发放 1=已发放 2=已作废'),
            'settled_at'        => $this->integer()->notNull()->defaultValue(0)->comment('实际发放时间'),
            'remark'            => $this->string(255)->notNull()->defaultValue(''),
        ]);

        // cron 取数主索引：按商城扫「待发放且已到期」
        $this->createIndex('idx_due', '{{%plugin_distribution_settle_plan}}', ['mall_id', 'status', 'due_at']);
        // 幂等：一条分润流水只应有一条计划
        $this->createIndex('uk_commission_log', '{{%plugin_distribution_settle_plan}}', ['commission_log_id'], true);
    }

    public function safeDown()
    {
        $this->dropTable('{{%plugin_distribution_settle_plan}}');
    }
}
