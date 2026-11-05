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
 * 文章营销:文章挂商品。
 *
 * 一篇文章最多挂 6 件商品,可排序、每件可写一句推荐语、其中一件作为底部购买条主推。
 * 挂载关系存在插件自己的表里,不动核心商品表;保存文章时整组替换(物理删除后重建)。
 *
 * 幂等:表已存在就跳过。
 */
class m261006_140000_create_plugin_article_goods extends Migration
{
    private const TABLE = '{{%plugin_article_goods}}';

    public function safeUp()
    {
        if ($this->db->getTableSchema(self::TABLE, true) !== null) {
            echo "    - 表 plugin_article_goods 已存在,跳过\n";
            return;
        }
        $this->createTable(self::TABLE, [
            'id'         => $this->primaryKey(),
            'mall_id'    => $this->integer()->notNull(),
            'article_id' => $this->integer()->notNull()->comment('文章ID'),
            'goods_id'   => $this->integer()->notNull()->comment('商品ID'),
            'sort'       => $this->integer()->notNull()->defaultValue(0)->comment('排序,小的在前'),
            'recommend'  => $this->string(60)->notNull()->defaultValue('')->comment('推荐语(选填)'),
            'is_main'    => $this->tinyInteger()->notNull()->defaultValue(0)->comment('1=底部购买条主推'),
        ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'文章挂商品\'');
        $this->createIndex('uk_article_goods', self::TABLE, ['article_id', 'goods_id'], true);
        $this->createIndex('idx_article_goods_mall', self::TABLE, ['mall_id', 'goods_id']);
        echo "    ✓ 已建表 plugin_article_goods\n";
    }

    public function safeDown()
    {
        if ($this->db->getTableSchema(self::TABLE, true) !== null) {
            $this->dropTable(self::TABLE);
        }
    }
}
