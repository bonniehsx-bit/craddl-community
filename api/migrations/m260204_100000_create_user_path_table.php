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

use yii\db\Schema;
use app\core\Migration;

class m260204_100000_create_user_path_table extends Migration
{
    public function safeUp()
    {
        $table = '{{%user_path}}';

        $this->createTable($table, [
            // user_id 作为主键，不使用自增，因为它必须与 user 表的 id 一致
            'user_id' => $this->integer(11)->notNull()->comment('用户ID(关联主表)'),

            // 您要求的 mall_id 字段
            'mall_id' => $this->integer(11)->notNull()->defaultValue(0)->comment('商城ID'),

            // 路径字段，使用 text 以支持深层级 (5000层约30KB，varchar可能不够或影响行大小)
            'path' => $this->text()->comment('关系链路径(例如 -1-2-5-)'),

            // 冗余字段：团队总人数，用于缓存统计结果
            'team_count' => $this->integer(11)->notNull()->defaultValue(0)->comment('团队总人数(缓存)'),
        ], null, '用户关系路径垂直分表');

        // 设置 user_id 为主键
        $this->addPrimaryKey('pk-user_path-user_id', $table, 'user_id');

        // === 手动添加 Path 的前缀索引 ===
        // 您的基类不会自动处理 text 类型的索引，这里必须手动加
        // 长度设为 255，足够覆盖大多数前缀查询，且符合 InnoDB 限制
        echo "    > 正在为表 `{$table}` 创建 path 前缀索引...\n";
        try {
            $this->createIndex('idx-user_path-path', $table, 'path(255)');
        } catch (\Exception $e) {
            echo "    > 注意: path 索引创建可能已存在或失败: " . $e->getMessage() . "\n";
        }
    }

    public function safeDown()
    {
        $this->dropTable('{{%user_path}}');
    }
}