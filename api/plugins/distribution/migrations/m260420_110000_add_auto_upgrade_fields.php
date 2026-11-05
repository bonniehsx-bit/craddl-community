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
 * 分销等级增加自动升级相关字段，对齐系统会员等级的升级能力。
 *
 * 升级条件 JSON 存储方案保持与系统会员一致：jxmall_option key=dist_level_upgrade_{id}，
 * 避免分销等级表字段膨胀；具体判定逻辑在 DistributionLevelUpgradeJob 内独立实现（基线 copy 自系统会员）。
 */
class m260420_110000_add_auto_upgrade_fields extends Migration
{
    public function safeUp()
    {
        $table = \Yii::$app->db->schema->getTableSchema('{{%plugin_distribution_level}}');
        if (!$table) return;

        if (!isset($table->columns['is_auto'])) {
            $this->addColumn('{{%plugin_distribution_level}}', 'is_auto',
                $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否自动升级:0=否,1=是'));
        }
        if (!isset($table->columns['buy_type'])) {
            $this->addColumn('{{%plugin_distribution_level}}', 'buy_type',
                $this->tinyInteger()->notNull()->defaultValue(0)->comment('升级时机:0=付款后,1=订单完成后'));
        }
    }

    public function safeDown()
    {
        $table = \Yii::$app->db->schema->getTableSchema('{{%plugin_distribution_level}}');
        if (!$table) return;
        if (isset($table->columns['is_auto'])) {
            $this->dropColumn('{{%plugin_distribution_level}}', 'is_auto');
        }
        if (isset($table->columns['buy_type'])) {
            $this->dropColumn('{{%plugin_distribution_level}}', 'buy_type');
        }
    }
}
