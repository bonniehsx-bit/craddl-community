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

/** 商品录入的外部来源链接，后台内部使用，默认留空。 */
class m261030_110000_add_source_url_to_goods_info extends Migration
{
    private const TABLE = '{{%goods_info}}';

    public function safeUp()
    {
        $schema = $this->db->getTableSchema(self::TABLE, true);
        if ($schema && !$schema->getColumn('source_url')) {
            $this->addColumn(self::TABLE, 'source_url', $this->text()->null()->comment('商品来源链接（仅供后台查阅，最多2048字符）'));
        }
    }

    public function safeDown()
    {
        $schema = $this->db->getTableSchema(self::TABLE, true);
        if ($schema && $schema->getColumn('source_url')) {
            $this->dropColumn(self::TABLE, 'source_url');
        }
    }
}
