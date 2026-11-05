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
 * 清理「货款」遗留 —— 货款已迁为通用钱包(user_wallet,wallet_key='mch_funds')。
 *
 * 删除不再使用的:
 *   - User.mch_funds / total_mch_funds 列
 *   - mch_funds_log 表
 *   - finance「货款记录」菜单(menu_id=finance-mch-funds)
 *
 * 幂等:存在才删,可重复执行。未投产,无需保留旧数据。
 */
class m260629_130000_drop_legacy_mch_funds extends Migration
{
    public function safeUp()
    {
        $table = '{{%user}}';
        if ($this->db->getTableSchema($table, true)->getColumn('mch_funds') !== null) {
            $this->dropColumn($table, 'mch_funds');
        }
        if ($this->db->getTableSchema($table, true)->getColumn('total_mch_funds') !== null) {
            $this->dropColumn($table, 'total_mch_funds');
        }

        $this->db->createCommand('DROP TABLE IF EXISTS {{%mch_funds_log}}')->execute();

        \app\models\Menu::deleteAll(['menu_id' => 'finance-mch-funds']);
    }

    public function safeDown()
    {
        // 不回滚:货款已迁通用钱包,旧结构不再恢复
    }
}
