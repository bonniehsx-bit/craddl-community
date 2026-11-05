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
 * 剔除会员等级的「升级前需签电子合同」配置(全局不需要,且无任何运行时签约校验逻辑)。
 * 删除 jxmall_level.is_contract_update / contract_tpl_id 两列。
 *
 * 列存在性走 information_schema 直查(getTableSchema 在本环境带缓存,refresh 不稳),确保幂等。
 */
class m260616_120000_drop_contract_fields_from_level extends Migration
{
    public function safeUp()
    {
        if ($this->columnExists('is_contract_update')) {
            $this->dropColumn('{{%level}}', 'is_contract_update');
        }
        if ($this->columnExists('contract_tpl_id')) {
            $this->dropColumn('{{%level}}', 'contract_tpl_id');
        }
    }

    public function safeDown()
    {
        if (!$this->columnExists('is_contract_update')) {
            $this->addColumn('{{%level}}', 'is_contract_update', $this->tinyInteger()->null()->defaultValue(0)->comment('升级之前是否需要签订合同'));
        }
        if (!$this->columnExists('contract_tpl_id')) {
            $this->addColumn('{{%level}}', 'contract_tpl_id', $this->string(64)->null()->defaultValue('')->comment('合同模板ID'));
        }
    }

    private function columnExists(string $col): bool
    {
        $db = \Yii::$app->db;
        $table = $db->tablePrefix . 'level';
        return (bool)$db->createCommand(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c",
            [':t' => $table, ':c' => $col]
        )->queryScalar();
    }
}
