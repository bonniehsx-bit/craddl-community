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
 * 删除 Mch 表的商户级 is_commission_open 死字段(含索引)。
 *
 * 该「推客分销开关」全仓无业务逻辑读取——推客分销真正按【商品级】
 * DistributionGoods.is_commission_open 判定(distribution 插件),商户级这个开关是
 * 重构遗留的摆设,填了不生效。故清理。⚠️ 商品级同名字段不受影响。
 *
 * 幂等;不可逆(safeDown 空)。
 */
class m260728_120000_drop_mch_is_commission_open extends Migration
{
    public function safeUp()
    {
        $table = '{{%plugin_mch}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null) {
            return;
        }

        try {
            $this->dropIndex('idx_mch_is_commission_open', $table);
        } catch (\Throwable $e) {
            // 索引可能因历史环境差异已不存在,忽略
        }
        if (isset($schema->columns['is_commission_open'])) {
            $this->dropColumn($table, 'is_commission_open');
        }
    }

    public function safeDown()
    {
        echo "m260728_120000_drop_mch_is_commission_open 不可逆(商户级分销开关死字段清理)\n";
        return false;
    }
}
