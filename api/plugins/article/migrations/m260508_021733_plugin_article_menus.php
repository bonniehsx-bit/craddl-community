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
 * Created by PhpStorm.
 * User: dujxmall
 * Date: 2022/11/30
 * Time: 16:26
 */


use app\core\Migration;

class m260508_021733_plugin_article_menus extends Migration
{
    public function safeUp()
    {
        \app\models\Menu::deleteAll(['plugin' => 'article']);
        $this->batchInsert('{{%menu}}',
            ["menu_id", "menu_name", "icon", "menu_type", "order_num", "parent_id", "is_cache", "hidden", "is_frame", "status", "path", "component", "query", "perms", "deleted_at", "link", "plugin", "active_menu", "breadcrumb", "name", "show_tag"],
            [
                [
                    'menu_id' => 'article',
                    'menu_name' => '文章营销',
                    'icon' => 'example',
                    'menu_type' => 'M',
                    'order_num' => '9',
                    'parent_id' => 'plugins',
                    'is_cache' => '1',
                    'hidden' => '0',
                    'is_frame' => '1',
                    'status' => '0',
                    'path' => '/plugins/article',
                    'component' => null,
                    'query' => null,
                    'perms' => null,
                    'deleted_at' => '0',
                    'link' => '',
                    'plugin' => 'article',
                    'active_menu' => '',
                    'breadcrumb' => '1',
                    'name' => null,
                    'show_tag' => '1',
                ],
                [
                    'menu_id' => 'article_index',
                    'menu_name' => '文章列表',
                    'icon' => 'email',
                    'menu_type' => 'C',
                    'order_num' => '0',
                    'parent_id' => 'article',
                    'is_cache' => '1',
                    'hidden' => '0',
                    'is_frame' => '1',
                    'status' => '0',
                    'path' => '/plugins/article/index',
                    'component' => 'plugins/article/index',
                    'query' => null,
                    'perms' => null,
                    'deleted_at' => '0',
                    'link' => '',
                    'plugin' => 'article',
                    'active_menu' => '',
                    'breadcrumb' => '1',
                    'name' => 'plugins-article-index',
                    'show_tag' => '1',
                ],
                [
                    'menu_id' => 'article_cat',
                    'menu_name' => '文章分类',
                    'icon' => 'example',
                    'menu_type' => 'C',
                    'order_num' => '2',
                    'parent_id' => 'article',
                    'is_cache' => '1',
                    'hidden' => '0',
                    'is_frame' => '1',
                    'status' => '0',
                    'path' => '/plugins/article/cat',
                    'component' => 'plugins/article/cat',
                    'query' => null,
                    'perms' => null,
                    'deleted_at' => '0',
                    'link' => '',
                    'plugin' => 'article',
                    'active_menu' => '',
                    'breadcrumb' => '1',
                    'name' => 'plugins-article-cat',
                    'show_tag' => '1',
                ],
                [
                    'menu_id' => 'article_cat_edit',
                    'menu_name' => '分类编辑',
                    'icon' => 'example',
                    'menu_type' => 'C',
                    'order_num' => '1',
                    'parent_id' => 'article',
                    'is_cache' => '1',
                    'hidden' => '1',
                    'is_frame' => '1',
                    'status' => '0',
                    'path' => '/plugins/article/cat-edit',
                    'component' => 'plugins/article/cat-edit',
                    'query' => null,
                    'perms' => null,
                    'deleted_at' => '0',
                    'link' => '',
                    'plugin' => 'article',
                    'active_menu' => '/plugins/article/cat',
                    'breadcrumb' => '0',
                    'name' => 'plugins-article-cat-edit',
                    'show_tag' => '0',
                ],
                [
                    'menu_id' => 'article_edit',
                    'menu_name' => '文章编辑',
                    'icon' => 'component',
                    'menu_type' => 'C',
                    'order_num' => '3',
                    'parent_id' => 'article',
                    'is_cache' => '1',
                    'hidden' => '1',
                    'is_frame' => '1',
                    'status' => '0',
                    'path' => '/plugins/article/edit',
                    'component' => 'plugins/article/edit',
                    'query' => null,
                    'perms' => null,
                    'deleted_at' => '0',
                    'link' => '',
                    'plugin' => 'article',
                    'active_menu' => '',
                    'breadcrumb' => '0',
                    'name' => 'plugins-article-edit',
                    'show_tag' => '0',
                ],
            ]);
    }
}