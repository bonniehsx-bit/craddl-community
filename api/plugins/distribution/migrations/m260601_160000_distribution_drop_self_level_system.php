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
 * 废除分销插件自建等级体系，统一并入平台会员等级。
 *
 *   · 删除自建等级表 plugin_distribution_level
 *   · plugin_distribution_user 瘦身：删 level_id / total_sales / total_referrals / block_upgrade
 *     （仅保留 is_disabled —— 单独取消某用户分销资格）
 *
 * 分销商等级/升级此后全部走会员等级（User.level_id → Level）。
 * 历史 CommissionLog 不动；旧的「按分销商等级配比例」需在新「按会员等级分润」界面重配。
 */
class m260601_160000_distribution_drop_self_level_system extends Migration
{
    public function safeUp()
    {
        // 1. 删除自建等级表
        if (\Yii::$app->db->schema->getTableSchema('{{%plugin_distribution_level}}')) {
            $this->dropTable('{{%plugin_distribution_level}}');
        }

        // 2. 分销用户表瘦身
        $table = \Yii::$app->db->schema->getTableSchema('{{%plugin_distribution_user}}');
        if ($table) {
            foreach (['level_id', 'total_sales', 'total_referrals', 'block_upgrade'] as $col) {
                if (isset($table->columns[$col])) {
                    $this->dropColumn('{{%plugin_distribution_user}}', $col);
                }
            }
        }
    }

    public function safeDown()
    {
        // 不可逆：自建等级体系已废弃，不再恢复。
        echo "m260601_160000_distribution_drop_self_level_system 不支持回滚\n";
        return false;
    }
}
