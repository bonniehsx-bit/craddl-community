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
 * 会员等级增加「升级条件关系」开关。
 *
 * upgrade_logic: 0=全部满足(AND，默认/旧行为)  1=满足任一(OR)
 * 决定该等级勾选的多个升级条件是"且"还是"或"。
 */
class m260601_210000_add_upgrade_logic_to_level extends Migration
{
    public function safeUp()
    {
        $table = \Yii::$app->db->schema->getTableSchema('{{%level}}');
        if ($table && !isset($table->columns['upgrade_logic'])) {
            $this->addColumn(
                '{{%level}}',
                'upgrade_logic',
                $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('升级条件关系:0=全部满足(AND) 1=满足任一(OR)')->after('is_auto')
            );
        }
    }

    public function safeDown()
    {
        $table = \Yii::$app->db->schema->getTableSchema('{{%level}}');
        if ($table && isset($table->columns['upgrade_logic'])) {
            $this->dropColumn('{{%level}}', 'upgrade_logic');
        }
    }
}
