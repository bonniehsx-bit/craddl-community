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

class m251026_200012_plugin_distribution_menus extends Migration
{
    public function safeUp()
    {
        \app\models\Menu::deleteAll(['plugin' => 'distribution']);
        $this->batchInsert('{{%menu}}',
            ["menu_id", "menu_name", "icon", "menu_type", "order_num", "parent_id", "path", "component", "plugin", "name"],
            [
                [
                    'menu_id' => 'distribution', 'menu_name' => '分销推客', 'icon' => 'table',
                    'menu_type' => 'M', 'order_num' => '12', 'parent_id' => 'plugins', 'path' => '/plugins/distribution',
                    'component' => null, 'plugin' => 'distribution', 'name' => null,
                ],
                [
                    'menu_id' => 'distribution-user', 'menu_name' => '分销人员', 'icon' => 'user',
                    'menu_type' => 'C', 'order_num' => '1', 'parent_id' => 'distribution', 'path' => '/plugins/distribution/user',
                    'component' => 'plugins/distribution/user/index', 'plugin' => 'distribution', 'name' => 'plugins-distribution-user',
                ],
                [
                    'menu_id' => 'distribution-log', 'menu_name' => '分销记录', 'icon' => 'table',
                    'menu_type' => 'C', 'order_num' => '2', 'parent_id' => 'distribution', 'path' => '/plugins/distribution/log',
                    'component' => 'plugins/distribution/log/index', 'plugin' => 'distribution', 'name' => 'plugins-distribution-log',
                ],
                [
                    'menu_id' => 'distribution-level', 'menu_name' => '分销等级', 'icon' => 'table',
                    'menu_type' => 'C', 'order_num' => '3', 'parent_id' => 'distribution', 'path' => '/plugins/distribution/level',
                    'component' => 'plugins/distribution/level/index', 'plugin' => 'distribution', 'name' => 'plugins-distribution-level',
                ],
                [
                    'menu_id' => 'distribution-setting', 'menu_name' => '分销设置', 'icon' => 'table',
                    'menu_type' => 'C', 'order_num' => '99', 'parent_id' => 'distribution', 'path' => '/plugins/distribution/setting',
                    'component' => 'plugins/distribution/setting/index', 'plugin' => 'distribution', 'name' => 'plugins-distribution-setting',
                ],
            ]);
    }

    public function safeDown()
    {
        \app\models\Menu::deleteAll(['plugin' => 'distribution']);
    }
}
