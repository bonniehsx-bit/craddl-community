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
 * user_team_stat.team_legs_map 由 TEXT 扩到 MEDIUMTEXT —— 大团队长的「各直推线」写不下。
 *
 * ## 为什么要改
 * team_legs_map 是 JSON {直推 uid: {count, max_level}},**每个直推下级占一项**,人数越多越长。
 * 改等级批量化之后(TeamStatUpdater::bumpLegMaxIn),这一列由 MySQL 的 JSON 函数原地改写,
 * 写回的 JSON 带空格,一项 45~47 字节(实测 9 位 uid):直推一千四百人左右就超过 TEXT 的 65535 字节。
 * 生产是 STRICT 模式,超长直接报 Data too long —— 而且那是一条语句批量改 500 个祖先,
 * 一个超长整批失败,这批祖先的统计都没更新(onLevelChanged 还会吞掉这个异常)。
 * MEDIUMTEXT 上限 16,777,215 字节,约 35 万个直推,可视为无限。
 *
 * 只改这一列:direct_level_map / team_level_map 按等级计数,项数 = 等级数;
 * purchased_goods_map 按商品计数,要几千种不同商品才会接近上限,都不需要。
 *
 * ## 注意
 * · TEXT → MEDIUMTEXT 会改变行存储格式,MySQL 走 COPY 算法**锁表重建**。
 *   user_team_stat 每个用户一行、单行很小,预计秒级;重建期间对这张表的写入会等待。
 * · 幂等:列已经是 MEDIUMTEXT 就跳过,重跑不会再重建一次表。
 * · down 改回 TEXT 前先检查长度,有超过 65535 字节的行就拒绝,免得截断数据。
 */
class m260923_100000_user_team_stat_legs_map_mediumtext extends Migration
{
    const T = '{{%user_team_stat}}';
    const COMMENT = '各直推线情况 JSON {uid:{count,max_level}}';

    public function safeUp()
    {
        $col = $this->column();
        if ($col === null) {
            echo "  " . static::T . ".team_legs_map 不存在,跳过。\n";
            return true;
        }
        if (strtolower($col->dbType) === 'mediumtext') {
            echo "  team_legs_map 已是 MEDIUMTEXT,跳过。\n";
            return true;
        }
        $this->alterColumn(static::T, 'team_legs_map', $this->definition('MEDIUMTEXT'));
        return true;
    }

    public function safeDown()
    {
        $col = $this->column();
        if ($col === null || strtolower($col->dbType) !== 'mediumtext') {
            return true;
        }
        $tooLong = (int)$this->db->createCommand(
            'SELECT COUNT(*) FROM ' . $this->db->quoteTableName(static::T) . ' WHERE LENGTH(team_legs_map) > 65535'
        )->queryScalar();
        if ($tooLong > 0) {
            echo "  拒绝回滚:有 {$tooLong} 行 team_legs_map 超过 65535 字节,改回 TEXT 会截断数据。\n";
            return false;
        }
        $this->alterColumn(static::T, 'team_legs_map', $this->definition('TEXT'));
        return true;
    }

    private function column()
    {
        $schema = $this->db->getTableSchema(static::T, true);
        return $schema ? $schema->getColumn('team_legs_map') : null;
    }

    private function definition(string $type): string
    {
        return "{$type} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '" . static::COMMENT . "'";
    }
}
