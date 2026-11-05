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
 * Class m260710_180000_drop_dead_user_parent_table
 *
 * 删除已废弃的 jxmall_user_parent 闭包表。
 *
 * 关系链早已全面迁移到 UserPath 物化路径(见 docs/规范-分销与资产.md §4)。
 * 本次全仓审计确认该表是彻底的死代码:
 *   · models/UserParent.php 全仓零调用(唯一引用在 xwy 的一个 .bak 文件里)
 *   · 无任何写入方,dev 库仅残留 2 行 2026-03/04 的历史数据
 *   · UserParentChangeListener 只是类名相似,不读这张表
 *
 * 表与模型一并删除,避免后来者误以为它还在维护而去读它
 * (规范明确:禁止再读 user_parent,向上查祖先一律走 UserService::getParents)。
 */
class m260710_180000_drop_dead_user_parent_table extends Migration
{
    private $table = '{{%user_parent}}';

    public function safeUp()
    {
        if ($this->db->getTableSchema($this->table, true) === null) {
            echo "    {$this->table} 不存在,跳过\n";
            return;
        }

        // 留痕:dropTable 不可逆(safeDown 只重建空表)。这里不做硬 abort ——
        // 那会破坏 migrate/auto 的非交互幂等;生产上线前请先备份该表。
        $count = (new \yii\db\Query())->from($this->table)->count('*', $this->db);
        echo "    即将删除 {$this->table},当前 {$count} 行(该表已废弃,全仓零读写)\n";

        $this->dropTable($this->table);
    }

    /**
     * 回滚:按原结构重建(含三个原有索引)。
     * 数据不恢复 —— 该表已无写入方,重建的是一张空表,仅供结构回退。
     */
    public function safeDown()
    {
        if ($this->db->getTableSchema($this->table, true) !== null) {
            echo "    {$this->table} 已存在,跳过\n";
            return;
        }

        // app\core\Migration::createTable 会自动追加 is_delete/时间戳列,
        // 并由 autoCreateIndexes 自动建好 idx-user_parent-mall_id_is_delete
        // 与 idx-user_parent-user_id_is_delete —— 这里再手建会因索引重名而失败。
        $this->createTable($this->table, [
            'id'        => $this->primaryKey(),
            'mall_id'   => $this->integer()->notNull()->comment('商城ID'),
            'user_id'   => $this->integer()->notNull()->comment('用户ID'),
            'parent_id' => $this->integer()->notNull()->comment('上级用户ID'),
            'level'     => $this->integer()->notNull()->comment('层级(1=直推)'),
        ]);

        // 只补基类不会自动建的那个原有复合索引
        $this->createIndex('idx_parent_mall_del_level', $this->table, ['parent_id', 'mall_id', 'is_delete', 'level']);
    }
}
