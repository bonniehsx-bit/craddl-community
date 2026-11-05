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

/**
 * 主框架:订单记录「下单时用户会员等级」快照 —— 供所有分润/分销插件按「下单那一刻身份」计算,
 * 避免读实时 User.level 受异步升级 / 延迟支付时序影响。纯加字段 + 创建时填充,不改现有逻辑,向后兼容。
 */
class m260618_100000_add_user_level_snapshot_to_order extends \app\core\Migration
{
    public function safeUp()
    {
        $table = '{{%order}}';
        $schema = $this->db->getTableSchema($table, true);
        if (!$schema) {
            return;
        }
        if ($schema->getColumn('user_level_id') === null) {
            $this->addColumn($table, 'user_level_id', $this->integer()->notNull()->defaultValue(0)->comment('下单时用户会员等级ID(Level.id 快照,0=未记录/历史单)'));
        }
        if ($schema->getColumn('user_level') === null) {
            $this->addColumn($table, 'user_level', $this->integer()->notNull()->defaultValue(0)->comment('下单时用户会员等级权重(level 快照)'));
        }
    }

    public function safeDown()
    {
        $table = '{{%order}}';
        $schema = $this->db->getTableSchema($table, true);
        if (!$schema) {
            return;
        }
        foreach (['user_level_id', 'user_level'] as $col) {
            if ($schema->getColumn($col) !== null) {
                $this->dropColumn($table, $col);
            }
        }
    }
}
