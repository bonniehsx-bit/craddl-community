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
 * user_path.path 由 TEXT 扩到 MEDIUMTEXT —— 解除关系链深度的物理上限。
 *
 * ## 为什么要改
 * path 是物化路径,深度 n 的串里就有 n 个 id,长度 ≈ n × 9 字节。
 * TEXT 上限 65535 字节,换算下来**约 7280 层就写不下了**:再深的层数会被
 * MySQL 静默截断(本地非严格模式下不报错,生产 STRICT 模式下则直接写入失败),
 * 结果就是 path 缺尾 → 归属判断、团队统计、大区/小区分腿全部错乱。
 *
 * 实测(2026-08-31,本地用真实结构造链):
 *   · 7280 层  写入完全正确
 *   · 10000 层 有 2718 层被截断(10000 − 7282,与 65535÷9 的换算严丝合缝)
 *
 * 业务上关系链不设层数上限,存储也不该设。MEDIUMTEXT 上限 16,777,215 字节,
 * 同样换算约 186 万层,可视为无限。
 *
 * 与之配套的代码改动:UserService::ensurePath 已从「递归 + 100 层上限」改成纯循环,
 * 原先那道上限会把超限点以下每一层的 path 都写成截断值(线上 56 条坏 path 的成因)。
 *
 * ## 注意
 * · TEXT → MEDIUMTEXT 会改变行存储格式,MySQL 走 COPY 算法**锁表重建**。
 *   user_path 每商城约十万行、单行很小,实测秒级完成;仍建议低峰期执行。
 * · 索引 idx-user_path-path 是 path(255) 前缀索引,列类型变更不影响它,无需重建。
 * · 本迁移**不可逆**:down 会把列改回 TEXT,若届时已存在超过 65535 字节的 path
 *   就会造成数据截断,所以 down 里做了长度检查,发现超长直接中止。
 */
class m260831_150000_user_path_mediumtext extends Migration
{
    public function safeUp()
    {
        $this->alterColumn(
            '{{%user_path}}',
            'path',
            "MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '关系链路径(例如 -1-2-5-)'"
        );
        return true;
    }

    public function safeDown()
    {
        $tooLong = (int) $this->db->createCommand(
            'SELECT COUNT(*) FROM ' . $this->db->quoteTableName('{{%user_path}}')
            . ' WHERE LENGTH(path) > 65535'
        )->queryScalar();

        if ($tooLong > 0) {
            echo "  拒绝回滚:有 {$tooLong} 行 path 超过 65535 字节,改回 TEXT 会截断数据。\n";
            echo "  如确需回滚,请先处理这些超深关系链。\n";
            return false;
        }

        $this->alterColumn(
            '{{%user_path}}',
            'path',
            "TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '关系链路径(例如 -1-2-5-)'"
        );
        return true;
    }
}
