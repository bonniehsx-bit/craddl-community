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

use yii\db\Schema;
use app\core\Migration;

/**
 * Class m250925_190000_add_mch_balance_account
 *
 * 【极简核心版】
 * 为商家创建独立的货款账户体系。
 * - 为 plugin_mch 表添加 balance 字段。
 * - 创建 mch_balance_log 表用于记录货款流水。
 */
class m260126_190000_add_mch_others extends \app\core\Migration
{
    public function init()
    {
        $this->db = 'db';
        parent::init();
    }

    /**
     * 执行迁移
     */
    public function safeUp()
    {

        $tableSchema = \Yii::$app->db->schema->getTableSchema('{{%plugin_mch}}');
        if (!isset($tableSchema->columns['other_application_id'])) {
            $this->addColumn(
                '{{%plugin_mch}}',
                'other_application_id',
                $this->string(64)->null()->comment('第三方平台应用ID')->after('status')
            );
        }
        if (!isset($tableSchema->columns['other_user_id'])) {
            $this->addColumn(
                '{{%plugin_mch}}',
                'other_user_id',
                $this->string(64)->null()->comment('第三方平台用户ID')->after('status')
            );
        }
        $list = \app\plugins\mch\models\Mch::find()->all();
        foreach ($list as $mch) {
            $user = \app\models\User::findOne($mch->user_id);
            if ($user && $user->application_id) {
                \app\plugins\mch\models\Mch::updateAll(['other_application_id' => $user->application_id, 'other_user_id' => $user->other_platform_user_id], ['id' => $mch->id]);
            }
        }
    }

    /**
     * 回滚迁移
     */
    public function safeDown() {}
}
