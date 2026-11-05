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

use yii\db\Migration;

/**
 * 优化 /plugin/xwy/api/index/index 接口性能
 *
 * 问题：用户2团队人数达7万+，每次打开首页都要全表 COUNT
 * 修复：添加覆盖索引，让 team/direct COUNT 查询走纯索引扫描 (Using index)
 */
class m260429_080000_add_index_for_team_count extends Migration
{
    public function safeUp()
    {
        $this->addIndexIfNotExists(
            'idx_parent_mall_del_level',
            '{{%user_parent}}',
            ['parent_id', 'mall_id', 'is_delete', 'level']
        );


    }

    public function safeDown()
    {
        $this->dropIndexIfExists('idx_parent_mall_del_level', '{{%user_parent}}');
    }

    // ---------------------------------------------------------------

    private function addIndexIfNotExists(string $name, string $table, array $columns): void
    {
        $rawTable = $this->db->schema->getRawTableName($table);
        $exists = $this->db->createCommand(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = :t
               AND INDEX_NAME   = :n",
            [':t' => $rawTable, ':n' => $name]
        )->queryScalar();

        if ($exists) {
            echo "    > index {$name} already exists, skipped.\n";
            return;
        }
        $this->createIndex($name, $table, $columns);
    }

    private function dropIndexIfExists(string $name, string $table): void
    {
        $rawTable = $this->db->schema->getRawTableName($table);
        $exists = $this->db->createCommand(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = :t
               AND INDEX_NAME   = :n",
            [':t' => $rawTable, ':n' => $name]
        )->queryScalar();

        if (!$exists) {
            echo "    > index {$name} not found, skipped.\n";
            return;
        }
        $this->dropIndex($name, $table);
    }
}
