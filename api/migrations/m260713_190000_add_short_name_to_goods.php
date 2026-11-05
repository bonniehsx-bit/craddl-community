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

/**
 * 商品增加「简称」字段(short_name)。
 * 客户定制(xwy-new):仅用于订单列表导出时输出商品简称,无其它业务作用。
 */
class m260713_190000_add_short_name_to_goods extends \app\core\Migration
{
    private $table = '{{%goods}}';

    public function safeUp()
    {
        $schema = $this->db->getTableSchema($this->table, true);
        if ($schema && $schema->getColumn('short_name') === null) {
            $this->addColumn($this->table, 'short_name', $this->string(255)->notNull()->defaultValue('')->comment('商品简称(仅用于订单导出)')->after('name'));
        }
    }

    public function safeDown()
    {
        $schema = $this->db->getTableSchema($this->table, true);
        if ($schema && $schema->getColumn('short_name') !== null) {
            $this->dropColumn($this->table, 'short_name');
        }
    }
}
