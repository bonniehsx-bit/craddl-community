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
 * 提现渠道 —— 新增「提现规则」自定义文案字段。
 *
 * 背景:C 端提现页底部的「提现规则」原本 100% 前端硬编码(withdraw.vue 的 withdrawRules
 * 计算属性),运营改不了一个字。现改为后台可配:
 *   · 留空(默认)→ 前端沿用原有那套随渠道参数自适应的默认文案,行为完全不变;
 *   · 填了      → 全量覆盖,一行一条,完全按运营写的展示。
 *
 * 放在渠道级(而非插件全局):不同渠道(银行卡/微信/内部转余额)的条款差异大,
 * 由客户确认按渠道各配一份。
 */
class m260802_210000_add_rules_to_withdraw_channel extends Migration
{
    private $table = '{{%plugin_withdraw_channel}}';

    public function safeUp()
    {
        $schema = $this->db->getTableSchema($this->table, true);
        if (!$schema) {
            return;
        }
        if ($schema->getColumn('rules') === null) {
            $this->addColumn(
                $this->table,
                'rules',
                $this->text()->comment('提现规则自定义文案(一行一条;留空=用前端默认规则)')
            );
        }
    }

    public function safeDown()
    {
        $schema = $this->db->getTableSchema($this->table, true);
        if (!$schema) {
            return;
        }
        if ($schema->getColumn('rules') !== null) {
            $this->dropColumn($this->table, 'rules');
        }
    }
}
