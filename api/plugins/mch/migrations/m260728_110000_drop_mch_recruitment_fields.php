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
 * 删除 Mch 表的招商绑定死字段:recruitment_partner_id / recruitment_center_id(含索引)。
 *
 * 这两列本是「手动给商户绑定招商合伙人/招商中心」,但全仓无任何业务逻辑读取——
 * 招商分红由 recruit 插件按自己的招商会员树(RecruitMember 下线团队 + 门店归属)自动算,
 * 不看这两列。招商业务归 recruit 插件,mch 商户编辑不该出现招商字段,故清理。
 *
 * 幂等;不可逆(safeDown 空,历史值本就是无用绑定)。
 */
class m260728_110000_drop_mch_recruitment_fields extends Migration
{
    public function safeUp()
    {
        $table = '{{%plugin_mch}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null) {
            return;
        }

        foreach (['idx_mch_recruitment_center', 'idx_mch_recruitment_partner'] as $idx) {
            try {
                $this->dropIndex($idx, $table);
            } catch (\Throwable $e) {
                // 索引可能因历史环境差异已不存在,忽略
            }
        }
        foreach (['recruitment_partner_id', 'recruitment_center_id'] as $col) {
            if (isset($schema->columns[$col])) {
                $this->dropColumn($table, $col);
            }
        }
    }

    public function safeDown()
    {
        echo "m260728_110000_drop_mch_recruitment_fields 不可逆(招商绑定死字段清理)\n";
        return false;
    }
}
