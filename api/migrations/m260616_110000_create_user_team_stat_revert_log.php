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
 * 团队统计回滚幂等锚点表。
 *
 * 大一统回滚信号 order.commission.revert 可能因 Go 调度器重试 / 重复同意而对同一 (订单,明细) 多次触发。
 * 团队/个人业绩统计是数值增减(非状态机),无法靠状态过滤天然幂等,故用本表按 (order_id, order_detail_id)
 * 唯一锚点拦住重复回滚,避免业绩被双扣。
 *
 * 约定:一条售后单 = 该明细整体回滚(与佣金 revert 同口径),同一明细多次部分退按首次整体回滚处理。
 */
class m260616_110000_create_user_team_stat_revert_log extends Migration
{
    public function safeUp()
    {
        if (\Yii::$app->db->schema->getTableSchema('{{%user_team_stat_revert_log}}') !== null) {
            return;
        }
        $this->createTable('{{%user_team_stat_revert_log}}', [
            'id'              => $this->primaryKey(),
            'mall_id'         => $this->integer()->notNull()->defaultValue(0)->comment('商城ID'),
            'order_id'        => $this->integer()->notNull()->comment('订单ID'),
            'order_detail_id' => $this->integer()->notNull()->defaultValue(0)->comment('订单明细ID;0=整单回滚 >0=该明细回滚'),
            'source'          => $this->string(20)->notNull()->defaultValue('')->comment('回滚来源:cancel(取消)/refund(售后)'),
        ], null, '团队统计回滚幂等锚点');

        // 唯一锚点:同一 (订单,明细) 只回滚一次
        $this->createIndex('uniq_revert_order_detail', '{{%user_team_stat_revert_log}}', ['order_id', 'order_detail_id'], true);
    }

    public function safeDown()
    {
        $this->dropTable('{{%user_team_stat_revert_log}}');
    }
}
