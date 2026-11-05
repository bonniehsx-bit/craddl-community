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
 * 营销优惠券插件-核心表(一期+二期)
 *  - plugin_marketing_campaign        : 营销活动主体(触发型/群发型 + 预算 + 时段 + 人群规则快照)
 *  - plugin_marketing_campaign_coupon : 活动↔平台券模板关联(一场活动可发多种券,每种N张)
 *  - plugin_marketing_trigger         : 触发规则(触发型活动:事件 + 条件JSON)
 *  - plugin_marketing_grant_log       : 发放流水(幂等 + 统计核心,回写核心 user_coupon_id)
 *  - plugin_marketing_batch_task      : 定时群发任务(二期:计划时刻到点由 cron 分批发放)
 *
 * 设计:券本身复用平台 coupon/user_coupon,本插件只做营销编排与发放回收,不碰核心券表结构。
 */
class m260703_110000_create_marketing_tables extends Migration
{
    public function safeUp()
    {
        // 1. 活动主体
        $t = '{{%plugin_marketing_campaign}}';
        if ($this->db->getTableSchema($t, true) === null) {
            $this->createTable($t, [
                'id' => $this->primaryKey(),
                'mall_id' => $this->integer()->notNull(),
                'name' => $this->string(60)->notNull()->defaultValue('')->comment('活动名称'),
                'type' => $this->tinyInteger(1)->notNull()->defaultValue(1)->comment('活动类型: 1=触发型, 2=群发型'),
                'status' => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('状态: 0=草稿, 1=进行中, 2=暂停, 3=已结束'),
                'start_at' => $this->integer()->notNull()->defaultValue(0)->comment('活动开始时间(0=不限)'),
                'end_at' => $this->integer()->notNull()->defaultValue(0)->comment('活动结束时间(0=不限)'),
                'budget_type' => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('预算类型: 0=不限, 1=按发放张数, 2=按发放金额(∑面额)'),
                'budget_total' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('预算上限(张数或金额)'),
                'grant_count' => $this->integer()->notNull()->defaultValue(0)->comment('已发放张数(统计,预算封顶用)'),
                'grant_amount' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('已发放金额∑面额(统计,预算封顶用)'),
                'audience_json' => $this->text()->comment('人群定向规则JSON(等级/消费/首单/注册时段/地域/末单距今)'),
                'remark' => $this->string(255)->notNull()->defaultValue('')->comment('备注'),
            ], null, '营销活动主体');
            $this->createIndex('idx_mall_type_status', $t, ['mall_id', 'type', 'status']);
        }

        // 2. 活动券模板关联
        $t = '{{%plugin_marketing_campaign_coupon}}';
        if ($this->db->getTableSchema($t, true) === null) {
            $this->createTable($t, [
                'id' => $this->primaryKey(),
                'mall_id' => $this->integer()->notNull(),
                'campaign_id' => $this->integer()->notNull()->comment('活动ID'),
                'coupon_id' => $this->integer()->notNull()->comment('平台券模板ID(核心 coupon.id)'),
                'num' => $this->integer()->notNull()->defaultValue(1)->comment('每次发放张数'),
            ], null, '营销活动-券模板关联');
            $this->createIndex('idx_campaign', $t, ['campaign_id']);
        }

        // 3. 触发规则
        $t = '{{%plugin_marketing_trigger}}';
        if ($this->db->getTableSchema($t, true) === null) {
            $this->createTable($t, [
                'id' => $this->primaryKey(),
                'mall_id' => $this->integer()->notNull(),
                'campaign_id' => $this->integer()->notNull()->comment('活动ID'),
                'event' => $this->string(30)->notNull()->defaultValue('')->comment('触发事件: register/order_paid/level_up/birthday/inactive'),
                'condition_json' => $this->text()->comment('触发条件JSON(满额阈值/首单/目标等级/生日口径/未活跃天数+冷却)'),
            ], null, '营销活动-触发规则');
            $this->createIndex('idx_mall_event', $t, ['mall_id', 'event']);
            $this->createIndex('idx_campaign', $t, ['campaign_id']);
        }

        // 4. 发放流水(幂等+统计核心)
        $t = '{{%plugin_marketing_grant_log}}';
        if ($this->db->getTableSchema($t, true) === null) {
            $this->createTable($t, [
                'id' => $this->primaryKey(),
                'mall_id' => $this->integer()->notNull(),
                'campaign_id' => $this->integer()->notNull()->comment('来源活动ID'),
                'coupon_id' => $this->integer()->notNull()->comment('券模板ID'),
                'user_id' => $this->integer()->notNull()->comment('受益用户ID'),
                'user_coupon_id' => $this->integer()->notNull()->defaultValue(0)->comment('回写核心用户券ID(用于核销/GMV统计)'),
                'source_event' => $this->string(30)->notNull()->defaultValue('')->comment('触发来源: register/order_paid/.../batch/manual'),
                'dedup_key' => $this->string(80)->notNull()->defaultValue('')->comment('幂等键(活动+用户+周期,防重复发放)'),
                'face_value' => $this->decimal(10, 2)->notNull()->defaultValue(0)->comment('面额快照(满减=sub_price,折扣=0),供预算/成本统计'),
                'granted_at' => $this->integer()->notNull()->defaultValue(0)->comment('发放时间戳'),
            ], null, '营销活动-发放流水');
            $this->createIndex('idx_campaign_user_dedup', $t, ['campaign_id', 'user_id', 'dedup_key']);
            $this->createIndex('idx_mall_campaign', $t, ['mall_id', 'campaign_id']);
            $this->createIndex('idx_user_coupon', $t, ['user_coupon_id']);
        }

        // 5. 定时群发任务(二期)
        $t = '{{%plugin_marketing_batch_task}}';
        if ($this->db->getTableSchema($t, true) === null) {
            $this->createTable($t, [
                'id' => $this->primaryKey(),
                'mall_id' => $this->integer()->notNull(),
                'campaign_id' => $this->integer()->notNull()->comment('活动ID'),
                'plan_at' => $this->integer()->notNull()->defaultValue(0)->comment('计划发放时刻(0=立即)'),
                'audience_json' => $this->text()->comment('人群规则快照(下发时定格)'),
                'status' => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('状态: 0=待发, 1=发放中, 2=完成, 3=失败'),
                'total_target' => $this->integer()->notNull()->defaultValue(0)->comment('目标人数'),
                'total_sent' => $this->integer()->notNull()->defaultValue(0)->comment('已发人数'),
                'last_user_id' => $this->integer()->notNull()->defaultValue(0)->comment('断点续发游标(已处理到的最大user_id)'),
                'fail_msg' => $this->string(255)->notNull()->defaultValue('')->comment('失败原因'),
            ], null, '营销活动-定时群发任务');
            $this->createIndex('idx_status_plan', $t, ['status', 'plan_at']);
            $this->createIndex('idx_campaign', $t, ['campaign_id']);
        }
    }

    public function safeDown()
    {
        $this->dropTable('{{%plugin_marketing_batch_task}}');
        $this->dropTable('{{%plugin_marketing_grant_log}}');
        $this->dropTable('{{%plugin_marketing_trigger}}');
        $this->dropTable('{{%plugin_marketing_campaign_coupon}}');
        $this->dropTable('{{%plugin_marketing_campaign}}');
    }
}
