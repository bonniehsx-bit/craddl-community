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
 * 文件表记图片大小和尺寸:后台图片选择器据此标出「大图 806KB」、悬停显示 1440×1440 · 806KB。
 * 老数据由 `php yii image-thumb/run` 补算(顺带补生成缩略图)。
 */
class m261002_100000_add_size_dimensions_to_file extends Migration
{
    public function safeUp()
    {
        $table = '{{%file}}';
        $schema = $this->db->getTableSchema($table, true);
        if ($schema->getColumn('size') === null) {
            $this->addColumn($table, 'size', $this->integer()->unsigned()->notNull()->defaultValue(0)->comment('文件大小(字节),0=未知'));
        }
        if ($schema->getColumn('width') === null) {
            $this->addColumn($table, 'width', $this->integer()->unsigned()->notNull()->defaultValue(0)->comment('图片宽(像素),0=未知'));
        }
        if ($schema->getColumn('height') === null) {
            $this->addColumn($table, 'height', $this->integer()->unsigned()->notNull()->defaultValue(0)->comment('图片高(像素),0=未知'));
        }
    }

    public function safeDown()
    {
        $table = '{{%file}}';
        $schema = $this->db->getTableSchema($table, true);
        foreach (['height', 'width', 'size'] as $col) {
            if ($schema->getColumn($col) !== null) {
                $this->dropColumn($table, $col);
            }
        }
    }
}
