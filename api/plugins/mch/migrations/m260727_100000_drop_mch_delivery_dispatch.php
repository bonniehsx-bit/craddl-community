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
 * 剔除 mch 插件的「服务商 + 骑手」同城配送派单层。
 *
 * 保留(不受本迁移影响):核心 deliver_type='city' 发货方式、商家自配送/确认送达、
 * 同城配送费计算(Mch.city_delivery_config / city_delivery_distance / delivery_options)。
 *
 * 删除:
 *   1. 两张派单表:plugin_mch_delivery_task、plugin_mch_service_provider_rider
 *   2. Mch 4 个服务商列(含 3 个索引):
 *      is_service_provider / service_provider_status / delivery_fee_share_ratio / service_provider_id
 *   3. 核心 order 表 3 个派单死列(含索引):rider_id / delivery_task_id / rider_confirmed_at
 *      (「简单配送」只需 deliver_type / delivery_status / expected_arrival_time / send_at,这三列纯派单遗留)
 *   4. 商家后台派单菜单:mch-delivery-task(配送任务)、mch-delivery-log(配送记录)
 *
 * ⚠️ 破坏性 DROP。部署顺序:先发代码(已不再引用这些表/列/菜单/事件监听),再跑本迁移。
 * 不可逆(safeDown 返回 false):派单层已整体剔除,历史数据无法恢复。
 */
class m260727_100000_drop_mch_delivery_dispatch extends Migration
{
    public function safeUp()
    {
        // 1. 删两张派单表
        foreach (['{{%plugin_mch_delivery_task}}', '{{%plugin_mch_service_provider_rider}}'] as $t) {
            if ($this->db->schema->getTableSchema($t, true) !== null) {
                $this->dropTable($t);
            }
        }

        // 2. 删 Mch 服务商列(先删索引再删列)
        $mch = '{{%plugin_mch}}';
        $schema = $this->db->schema->getTableSchema($mch, true);
        if ($schema !== null) {
            foreach ([
                'idx-mch-is_service_provider',
                'idx-mch-service_provider_id',
                'idx-mch-service_provider_status',
            ] as $idx) {
                try {
                    $this->dropIndex($idx, $mch);
                } catch (\Throwable $e) {
                    // 索引可能因历史环境差异已不存在,忽略
                }
            }
            foreach ([
                'is_service_provider',
                'service_provider_status',
                'delivery_fee_share_ratio',
                'service_provider_id',
            ] as $col) {
                if (isset($schema->columns[$col])) {
                    $this->dropColumn($mch, $col);
                }
            }
        }

        // 3. 删核心 order 表的派单死列(先删索引再删列;rider_id/delivery_task_id 各有两种命名的重复索引)
        $order = '{{%order}}';
        $orderSchema = $this->db->schema->getTableSchema($order, true);
        if ($orderSchema !== null) {
            foreach ([
                'idx_order_rider_id', 'idx-order-rider_id',
                'idx_order_delivery_task_id', 'idx-order-delivery_task_id',
            ] as $idx) {
                try {
                    $this->dropIndex($idx, $order);
                } catch (\Throwable $e) {
                    // 索引可能因历史环境差异已不存在,忽略
                }
            }
            foreach (['rider_id', 'delivery_task_id', 'rider_confirmed_at'] as $col) {
                if (isset($orderSchema->columns[$col])) {
                    $this->dropColumn($order, $col);
                }
            }
        }

        // 4. 删派单菜单
        $this->delete('{{%menu}}', ['menu_id' => ['mch-delivery-task', 'mch-delivery-log']]);
    }

    public function safeDown()
    {
        echo "m260727_100000_drop_mch_delivery_dispatch 不可逆(派单层已整体剔除)\n";
        return false;
    }
}
