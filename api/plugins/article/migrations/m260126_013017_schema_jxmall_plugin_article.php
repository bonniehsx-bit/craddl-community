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
 * Handles the creation of tables from existing database.
 */
class m260126_013017_schema_jxmall_plugin_article extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // 创建表: jxmall_plugin_article
        $this->createTable('{{%plugin_article}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'title' => $this->string(45)->notNull()->comment('文章标题'),
            'created_by' => $this->string(45)->null()->defaultValue('')->comment('由谁创建'),
            'detail' => $this->text()->notNull()->comment('文章内容'),
            'video' => $this->string(255)->null()->defaultValue('')->comment('视频链接'),
            'cover_pic' => $this->string(255)->null()->defaultValue('')->comment('文章封面图'),
            'cat_id' => $this->integer()->null()->defaultValue(0)->comment('文章分类'),
            'views' => $this->integer()->notNull()->defaultValue(0)->comment('阅读量'),
            'is_published' => $this->tinyInteger()->null()->defaultValue(0)->comment('0 未发布 1已发布'),
            'sort' => $this->integer()->null()->defaultValue(0)->comment('排序'),
            'sub_title' => $this->string(128)->null()->defaultValue('')->comment('小标题')
        ]);


        // 创建表: jxmall_plugin_article_cat
        $this->createTable('{{%plugin_article_cat}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull(),
            'name' => $this->string(45)->notNull()->comment('名称'),
            'cover_pic' => $this->string(255)->notNull()->comment('封面图')
        ]);



    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%plugin_article_cat}}');
        $this->dropTable('{{%plugin_article}}');

    }
}