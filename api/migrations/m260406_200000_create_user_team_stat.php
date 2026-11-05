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

class m260406_200000_create_user_team_stat extends Migration
{
    public function safeUp()
    {
        if (\Yii::$app->db->schema->getTableSchema('{{%user_team_stat}}') !== null) {
            return;
        }

        $this->createTable('{{%user_team_stat}}', [
            'user_id'                => $this->integer()->notNull()->comment('用户ID'),
            'mall_id'                => $this->integer()->notNull()->comment('商城ID'),

            // 直推维度
            'direct_count'           => $this->integer()->notNull()->defaultValue(0)->comment('直推总人数'),
            'direct_level_map'       => $this->text()->null()->comment('直推各等级人数 JSON {level_id:count}'),

            // 团队维度
            'team_count'             => $this->integer()->notNull()->defaultValue(0)->comment('团队总人数'),
            'team_level_map'         => $this->text()->null()->comment('团队各等级人数 JSON {level_id:count}'),
            'team_legs_map'          => $this->text()->null()->comment('各直推线情况 JSON {uid:{count,max_level}}'),

            // 个人消费维度
            'personal_spend_paid'    => $this->decimal(12, 2)->notNull()->defaultValue('0.00')->comment('个人累计消费(支付后)'),
            'personal_spend_settled' => $this->decimal(12, 2)->notNull()->defaultValue('0.00')->comment('个人累计消费(结算后)'),
            'personal_order_count'   => $this->integer()->notNull()->defaultValue(0)->comment('个人订单数'),
            'purchased_goods_map'    => $this->text()->null()->comment('购买商品数量 JSON {goods_id:qty}'),

            // 团队消费维度
            'team_spend_paid'        => $this->decimal(12, 2)->notNull()->defaultValue('0.00')->comment('团队累计消费(支付后)'),
            'team_spend_settled'     => $this->decimal(12, 2)->notNull()->defaultValue('0.00')->comment('团队累计消费(结算后)'),

            // 月度维度
            'month_personal_spend'   => $this->decimal(12, 2)->notNull()->defaultValue('0.00')->comment('本月个人消费'),
            'month_team_spend'       => $this->decimal(12, 2)->notNull()->defaultValue('0.00')->comment('本月团队消费'),
            'month_active_count'     => $this->integer()->notNull()->defaultValue(0)->comment('本月团队活跃人数'),
            'month_period'           => $this->string(6)->notNull()->defaultValue('')->comment('月度标记 YYYYMM'),

            // 调度标记
            'dirty'                  => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('是否需要重检升级'),
            'last_check_at'         => $this->integer()->notNull()->defaultValue(0)->comment('上次升级检查时间'),
        ], null, '用户团队统计快照');

        // 主键用 user_id
        $this->addPrimaryKey('pk_user_id', '{{%user_team_stat}}', 'user_id');
        $this->createIndex('idx_mall_dirty', '{{%user_team_stat}}', ['mall_id', 'dirty']);
        $this->createIndex('idx_mall_id', '{{%user_team_stat}}', ['mall_id']);
    }

    public function safeDown()
    {
        $this->dropTable('{{%user_team_stat}}');
    }
}
