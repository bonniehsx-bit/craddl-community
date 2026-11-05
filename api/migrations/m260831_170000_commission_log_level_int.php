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
 * commission_log.level 由 TINYINT 扩到 INT —— 解除分润记账的 127 层天花板。
 *
 * ## 为什么要改
 * 这一列存的是「受益人位于买家上方第几层」,写入方把**真实关系链层深**直接塞进来:
 *   api/plugins/syk/services/TradeService.php:111
 *     $chain[] = ['user_id' => (int)$p['id'], 'depth' => (int)$p['level']];   // getParents 的 level:1=直接上级
 *   同文件 :202 / :295 / :372
 *     'level' => $node['depth'],
 * 而列是 signed TINYINT,上限 127。
 *
 * 后果分两种环境,都不好:
 *   · 生产(STRICT_TRANS_TABLES):第 128 层起 SQL 1264 Out of range 直接报错,
 *     分润循环半途中断 —— 前面几层的账已经落了,后面的没落,对不平。
 *   · dev(非严格):静默截断成 127,错账无声无息。
 * 这正是「本地永远测不出、生产必然踩」的那类问题。
 *
 * 线上关系链最深 173 层、超 100 层的有 709 人,离 127 只剩一步。
 * 触发还需同时满足:该商城启用 syk + 链深 >127 + 级差循环未提前触顶 —— 目前实库
 * MAX(level) 还只有 5,属于尚未引爆的雷,趁早拆掉。
 *
 * ## 为什么用 INT 而不是 SMALLINT
 * SMALLINT(32767)对 173 层当然够,但那还是在给关系链设一个"看起来够大"的数字 ——
 * 和 ensurePath 当年那个 100 层保险是同一种思路。关系链业务上不设层数上限,
 * 这一列也不该设;INT 与 user_id 等列同宽,后患一次性了结。
 *
 * ## 注意
 * · TINYINT → INT 改变行存储,MySQL 走 COPY 算法**锁表重建**。实库 jxmall_commission_log
 *   约 34 万行,实测秒级;仍建议低峰期执行。
 * · level 上有索引(SHOW COLUMNS 显示 MUL),列宽变更不影响索引可用性,无需重建。
 * · down 会改回 TINYINT,若届时已存在 >127 或 <-128 的值会造成数据损坏,
 *   故 down 里先做范围检查,越界直接中止。
 */
class m260831_170000_commission_log_level_int extends Migration
{
    public function safeUp()
    {
        $this->alterColumn(
            '{{%commission_log}}',
            'level',
            "INT NOT NULL DEFAULT 1 COMMENT '分销层级(受益人位于买家上方第几层,1=直接上级)'"
        );
        return true;
    }

    public function safeDown()
    {
        $out = (int) $this->db->createCommand(
            'SELECT COUNT(*) FROM ' . $this->db->quoteTableName('{{%commission_log}}')
            . ' WHERE level > 127 OR level < -128'
        )->queryScalar();

        if ($out > 0) {
            echo "  拒绝回滚:有 {$out} 行 level 超出 TINYINT 范围,改回去会损坏数据。\n";
            echo "  如确需回滚,请先处理这些记录。\n";
            return false;
        }

        $this->alterColumn(
            '{{%commission_log}}',
            'level',
            "TINYINT NOT NULL DEFAULT 1 COMMENT '分销层级'"
        );
        return true;
    }
}
