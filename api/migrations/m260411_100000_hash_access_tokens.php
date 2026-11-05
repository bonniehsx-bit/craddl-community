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
 * 将 access_token 表中的明文 Token 迁移为 SHA256 哈希
 *
 * 背景：安全升级，DB 中不再存储原始 Token，改存 hash('sha256', token)
 * 原始 Token (generateRandomString) 长度 32 字符，SHA256 输出 64 字符
 * 利用长度差异实现幂等：只处理长度 != 64 的记录
 */
class m260411_100000_hash_access_tokens extends Migration
{
    public function safeUp()
    {
        // 使用 MySQL 原生 SHA2 函数，单条 SQL 批量转换，无需 PHP 循环
        // 条件：长度 != 64（未哈希）且未过期（已过期的无需处理，会被定时任务清理）
        $tableName = $this->db->tablePrefix . 'access_token';
        $count = $this->db->createCommand(
            "UPDATE `{$tableName}` SET `token` = SHA2(`token`, 256) WHERE CHAR_LENGTH(`token`) != 64"
        )->execute();

        echo "    > 已将 {$count} 条明文 Token 转换为 SHA256 哈希\n";
    }

    public function safeDown()
    {
        // 哈希不可逆，无法回滚
        // 但不影响系统运行：用户重新登录即可获得新 Token
        echo "    > 哈希不可逆，跳过回滚。受影响用户需重新登录。\n";
        return true;
    }
}
