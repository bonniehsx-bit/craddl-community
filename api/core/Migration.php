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

namespace app\core;

class Migration extends \yii\db\Migration
{
    public function init()
    {
        $this->db = 'db';
        parent::init();
    }

    public function createTable($table, $columns, $options = null, $comment = '')
    {
        $default_columns = [
            'is_delete'    => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('是否删除'),
            'created_at'   => $this->integer(11)->null()->defaultValue(0)->comment('创建时间戳'),
            'updated_at'   => $this->integer(11)->null()->defaultValue(0)->comment('更新时间'),
            'deleted_at'   => $this->integer(11)->null()->defaultValue(0)->comment('删除时间'),
            'created_time' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP')->comment('创建时间'),
            'updated_time' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP')->comment('更新时间'),
            'deleted_time' => $this->timestamp()->null()->comment('删除时间'),
        ];

        $final_columns = array_merge($columns, $default_columns);

        $final_options = $options ?: 'ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        if ($comment) {
            $final_options .= " COMMENT='{$comment}'";
        }

        parent::createTable($table, $final_columns, $final_options);

        $this->autoCreateIndexes($table, $columns);
    }

    protected function autoCreateIndexes($table, $columns)
    {
        echo "    > 正在为表 `{$table}` 自动创建索引...\n";

        $tableNameWithoutPrefix = str_replace('{{%', '', str_replace('}}', '', $table));

        $hasMallId  = isset($columns['mall_id']);
        $hasUserId  = isset($columns['user_id']);
        $hasIsDelete = true; // 默认一定有

        try {
            // mall_id + is_delete 复合索引
            if ($hasMallId && $hasIsDelete) {
                $indexName = "idx-{$tableNameWithoutPrefix}-mall_id_is_delete";
                echo "      - 正在创建复合索引 `{$indexName}` (mall_id, is_delete)\n";
                $this->createIndex($indexName, $table, ['mall_id', 'is_delete']);
            }

            // user_id + is_delete 复合索引
            if ($hasUserId && $hasIsDelete) {
                $indexName = "idx-{$tableNameWithoutPrefix}-user_id_is_delete";
                echo "      - 正在创建复合索引 `{$indexName}` (user_id, is_delete)\n";
                $this->createIndex($indexName, $table, ['user_id', 'is_delete']);
            }
        } catch (\Exception $e) {
            echo "    > 创建索引时发生错误: " . $e->getMessage() . "\n";
        }
    }
}
