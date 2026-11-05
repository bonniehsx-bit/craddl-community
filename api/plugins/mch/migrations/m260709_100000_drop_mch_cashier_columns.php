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
 * 删除 jxmall_plugin_mch 的两个死列 cashier_status / cashier_remark + 索引 idx_cashier_status。
 *
 * 收银台拆成独立 cashier 插件后，这两列已是死存储：
 *   · 全仓零写入方（grep `->cashier_status =` / `'cashier_status' =>` 写入式均无命中）；
 *   · 收款码开通与否的真实判据是 other_application_id / other_user_id，不是这两列；
 *   · 前端 my-paycode 读的 res.data.cashier_status 是接口里写死的 0，与 DB 列无关（那个响应
 *     字段仍保留，值改为字面量 0，前端契约不变）。
 *
 * ⚠️ 破坏性 DROP COLUMN。部署顺序：先发代码（Mch 模型已不再声明这两列 + 常量），再跑本迁移。
 * safeDown 只重建列结构（历史数据无法恢复，本就全是默认值）。
 */
class m260709_100000_drop_mch_cashier_columns extends Migration
{
    public function safeUp()
    {
        $table = '{{%plugin_mch}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null) {
            return;
        }

        // 先删索引再删列（索引依赖 cashier_status 列）
        if (isset($schema->columns['cashier_status'])) {
            try {
                $this->dropIndex('idx_cashier_status', $table);
            } catch (\Throwable $e) {
                // 索引可能已不存在（历史环境差异），忽略
            }
            $this->dropColumn($table, 'cashier_status');
        }
        if (isset($schema->columns['cashier_remark'])) {
            $this->dropColumn($table, 'cashier_remark');
        }
    }

    public function safeDown()
    {
        $table = '{{%plugin_mch}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null) {
            return;
        }

        if (!isset($schema->columns['cashier_status'])) {
            $this->addColumn($table, 'cashier_status', $this->tinyInteger()->notNull()->defaultValue(0)->comment('收款码进件状态: 0=未申请, 10=审核中, 20=开通成功, 30=已驳回'));
            $this->createIndex('idx_cashier_status', $table, ['cashier_status'], false);
        }
        if (!isset($schema->columns['cashier_remark'])) {
            $this->addColumn($table, 'cashier_remark', $this->string(255)->null()->defaultValue('')->comment('进件审核备注/驳回理由'));
        }
        return true;
    }
}
