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

class m260417_000001_distribution_level_system extends Migration
{
    public function up()
    {
        // 分销商等级定义表
        $this->createTable('{{%plugin_distribution_level}}', [
            'id'                 => $this->primaryKey(),
            'mall_id'            => $this->integer()->notNull()->defaultValue(0),
            'name'               => $this->string(50)->notNull()->comment('等级名称'),
            'level'              => $this->integer()->notNull()->defaultValue(1)->comment('等级权重，越大越高'),
            'upgrade_type'       => $this->string(20)->notNull()->defaultValue('sales')->comment('升级条件类型: sales=累计销售额 referrals=推荐人数 manual=手动'),
            'upgrade_threshold'  => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('升级阈值'),
            'commission_rates'   => $this->text()->comment('佣金规则JSON，覆盖全局配置'),
            'sort'               => $this->integer()->defaultValue(100),
            'is_delete'          => $this->tinyInteger()->defaultValue(0),
            'created_at'         => $this->integer()->defaultValue(0),
            'updated_at'         => $this->integer()->defaultValue(0),
        ]);
        $this->createIndex('idx_dist_level_mall', '{{%plugin_distribution_level}}', ['mall_id', 'is_delete']);

        // 分销商个人档案表（记录当前等级+累计数据）
        $this->createTable('{{%plugin_distribution_user}}', [
            'id'               => $this->primaryKey(),
            'mall_id'          => $this->integer()->notNull()->defaultValue(0),
            'user_id'          => $this->integer()->notNull()->defaultValue(0),
            'level_id'         => $this->integer()->defaultValue(0)->comment('当前等级ID，0=未分级'),
            'total_sales'      => $this->decimal(12, 2)->defaultValue(0)->comment('累计产生销售额'),
            'total_referrals'  => $this->integer()->defaultValue(0)->comment('累计推荐人数'),
            'created_at'       => $this->integer()->defaultValue(0),
            'updated_at'       => $this->integer()->defaultValue(0),
        ]);
        $this->createIndex('idx_dist_user_mall_user', '{{%plugin_distribution_user}}', ['mall_id', 'user_id'], true);
    }

    public function down()
    {
        $this->dropTable('{{%plugin_distribution_level}}');
        $this->dropTable('{{%plugin_distribution_user}}');
    }
}
