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
 * 创建表 jxmall_plugin_mch_delivery_task —— mch 插件配送任务表
 *
 * 业务定位：
 *   订单 → 派单 → 抢/指派 → 取货 → 配送 → 完成的状态流转载体。
 *   外键关联：mch_id (Mch), rider_id (plugin_mch_service_provider_rider),
 *           order_id (Order, 唯一)，rider_user_id (User)。
 *
 * 历史：
 *   该表此前位于 api/migrations/m260101_000000_exclude_jxmall_plugin_.php，
 *   表名为 jxmall_delivery_task，不符合 plugin_mch_ 前缀规范。
 *   本次将建表逻辑下沉到 mch 插件自身的 migrations，并对齐命名规范。
 */
class m260523_150000_create_delivery_task extends Migration
{
    public function safeUp()
    {
        $exists = $this->db->schema->getTableSchema('{{%plugin_mch_delivery_task}}', true);
        if ($exists !== null) {
            return;
        }

        $this->createTable('{{%plugin_mch_delivery_task}}', [
            'id'                    => $this->primaryKey(),
            'mall_id'               => $this->integer()->notNull(),
            'order_id'              => $this->integer()->notNull()->comment('关联的商城订单ID'),
            'mch_id'                => $this->integer()->notNull()->comment('需要发货的商家ID'),
            'service_provider_id'   => $this->integer()->notNull()->comment('承接任务的服务商ID'),
            'status'                => $this->tinyInteger()->notNull()->defaultValue(0)->comment('任务状态: 0=待抢单/待指派, 5=已超时, 10=待取货, 20=配送中, 30=已完成, 40=已取消'),
            'is_settled'            => $this->tinyInteger()->notNull()->defaultValue(0)->comment('骑手分成是否已结算:0=否,1=是'),
            'is_manual_assigned'    => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否为手动指派: 0=否(抢单), 1=是'),
            'expire_at'             => $this->integer()->unsigned()->null()->defaultValue(0)->comment('抢单超时时间戳'),
            'rider_id'              => $this->integer()->unsigned()->null()->defaultValue(0)->comment('接单的骑手ID (plugin_mch_service_provider_rider.id)'),
            'rider_user_id'         => $this->integer()->unsigned()->null()->defaultValue(0)->comment('接单的骑手用户ID'),
            'delivery_fee'          => $this->decimal(10, 2)->notNull()->comment('总配送费'),
            'rider_fee'             => $this->decimal(10, 2)->notNull()->comment('骑手应得的分成'),
            'assigned_at'           => $this->timestamp()->null()->comment('指派/接单时间'),
            'picked_up_at'          => $this->timestamp()->null()->comment('取货时间'),
            'completed_at'          => $this->timestamp()->null()->comment('送达时间'),
            'expected_arrival_time' => $this->string(100)->notNull()->defaultValue('')->comment('骑手更新的预计送达时间'),
        ]);

        $this->createIndex(
            'idx-delivery_task-rider_id',
            '{{%plugin_mch_delivery_task}}',
            ['rider_id'],
            false
        );
        $this->createIndex(
            'idx-delivery_task-service_provider_id-status',
            '{{%plugin_mch_delivery_task}}',
            ['service_provider_id', 'status'],
            false
        );
        $this->createIndex(
            'idx_is_settled',
            '{{%plugin_mch_delivery_task}}',
            ['is_settled'],
            false
        );
        $this->createIndex(
            'uk-delivery_task-order_id',
            '{{%plugin_mch_delivery_task}}',
            ['order_id'],
            true
        );
    }

    public function safeDown()
    {
        $exists = $this->db->schema->getTableSchema('{{%plugin_mch_delivery_task}}', true);
        if ($exists !== null) {
            $this->dropTable('{{%plugin_mch_delivery_task}}');
        }
    }
}
