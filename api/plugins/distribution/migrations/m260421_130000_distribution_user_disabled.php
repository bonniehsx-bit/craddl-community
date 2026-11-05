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
 * 分销档案增加 "已取消资格" 开关
 *
 * 语义层次：
 *   · User.is_inviter        —— 全局"推广员"身份（影响邀请绑定等），属于主商城概念
 *   · DistributionUser.block_upgrade  —— 仅禁止自动升级（佣金、身份均保留）
 *   · DistributionUser.is_disabled    —— 取消分销资格（停止升级 + 停止计佣，档案保留便于回溯）
 *
 * 管理员在"分销人员管理"页点"取消资格"时，只修改本字段，不动 User.is_inviter，
 * 这样不会误伤全局邀请关系，也不会被登录流程自动"再授"恢复。
 */
class m260421_130000_distribution_user_disabled extends Migration
{
    public function safeUp()
    {
        $table = \Yii::$app->db->schema->getTableSchema('{{%plugin_distribution_user}}');
        if (!$table) return;

        if (!isset($table->columns['is_disabled'])) {
            $this->addColumn('{{%plugin_distribution_user}}', 'is_disabled',
                $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否已被管理员取消分销资格:0=正常,1=已取消（停升级+停计佣）'));
        }
    }

    public function safeDown()
    {
        $table = \Yii::$app->db->schema->getTableSchema('{{%plugin_distribution_user}}');
        if ($table && isset($table->columns['is_disabled'])) {
            $this->dropColumn('{{%plugin_distribution_user}}', 'is_disabled');
        }
    }
}
