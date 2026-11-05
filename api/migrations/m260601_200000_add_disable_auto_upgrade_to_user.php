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
 * 用户表增加「禁止自动升级」开关。
 *
 * 某些用户不允许参与会员等级的自动升级（UserLevelUpgradeJob）。
 * disable_auto_upgrade=1 时，该用户不会被自动升级；后台仍可手动改其等级。
 */
class m260601_200000_add_disable_auto_upgrade_to_user extends Migration
{
    public function safeUp()
    {
        $table = \Yii::$app->db->schema->getTableSchema('{{%user}}');
        if ($table && !isset($table->columns['disable_auto_upgrade'])) {
            $this->addColumn(
                '{{%user}}',
                'disable_auto_upgrade',
                $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('禁止自动升级:0=允许 1=禁止')->after('is_manual')
            );
        }
    }

    public function safeDown()
    {
        $table = \Yii::$app->db->schema->getTableSchema('{{%user}}');
        if ($table && isset($table->columns['disable_auto_upgrade'])) {
            $this->dropColumn('{{%user}}', 'disable_auto_upgrade');
        }
    }
}
