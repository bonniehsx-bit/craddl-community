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
 * Class m260713_100000_add_parent_id_index_to_user
 *
 * jxmall_user 补 (parent_id, is_delete) 复合索引。
 *
 * user 表此前只有 PRIMARY / access_token / (mall_id,is_delete) / agent_level_id / nickname,
 * 没有任何 parent_id 索引 —— 而全仓大量查询按 parent_id 等值过滤:
 *   · 升级引擎的直推人头(calcActiveDirectCount / calcDirectValidCount / calcDirectSpend)
 *   · 关系图谱树的逐层加载(UserMapForm 按 parent_id 查子节点)
 *   · 直推数统计(childrenCounts)、tryBindParent 等
 * dev 202 行无感,生产数十万用户时这些查询走 (mall_id,is_delete) 后过滤或全表扫。
 *
 * 带上 is_delete 让「活跃直推」类查询(parent_id = ? AND is_delete = 0)全走索引。
 */
class m260713_100000_add_parent_id_index_to_user extends Migration
{
    private $table = '{{%user}}';
    private $index = 'idx-user-parent_id_is_delete';

    public function safeUp()
    {
        if ($this->db->getTableSchema($this->table, true)->getColumn('parent_id') === null) {
            echo "    {$this->table} 无 parent_id 列,跳过\n";
            return;
        }
        // 幂等:已存在同名索引则跳过
        $exists = $this->db->createCommand(
            "SHOW INDEX FROM {$this->db->quoteSql($this->table)} WHERE Key_name = :k",
            [':k' => $this->index]
        )->queryOne();
        if ($exists) {
            echo "    索引 {$this->index} 已存在,跳过\n";
            return;
        }
        $this->createIndex($this->index, $this->table, ['parent_id', 'is_delete']);
    }

    public function safeDown()
    {
        $exists = $this->db->createCommand(
            "SHOW INDEX FROM {$this->db->quoteSql($this->table)} WHERE Key_name = :k",
            [':k' => $this->index]
        )->queryOne();
        if ($exists) {
            $this->dropIndex($this->index, $this->table);
        }
    }
}
