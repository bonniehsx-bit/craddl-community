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

class m260403_000002_clerk_menus extends \app\core\Migration
{
    public function safeUp()
    {
        // 覆盖清理旧菜单
        \app\models\Menu::deleteAll(['plugin' => 'clerk']);

        $this->batchInsert(
            '{{%menu}}',
            [
                "menu_id",
                "menu_name",
                "icon",
                "menu_type",
                "order_num",
                "parent_id",
                "is_cache",
                "hidden",
                "is_frame",
                "status",
                "path",
                "component",
                "query",
                "perms",
                "deleted_at",
                "link",
                "plugin",
                "active_menu",
                "breadcrumb",
                "name",
                "show_tag"
            ],
            [
                [
                    'clerk',
                    '平台核销',
                    'el-icon-s-custom',
                    'M',
                    '10',
                    'plugins',
                    '0',
                    '0',
                    '1',
                    '0',
                    '/plugins/clerk',
                    null,
                    null,
                    null,
                    null,
                    '',
                    'clerk',
                    '',
                    '1',
                    'clerk',
                    '0'
                ],
                [
                    'clerk_user',
                    '核销人员',
                    'el-icon-s-custom',
                    'C',
                    '1',
                    'clerk',
                    '0',
                    '0',
                    '1',
                    '0',
                    '/plugins/clerk/user',
                    'plugins/clerk/user',
                    null,
                    'plugin/clerk/user',
                    null,
                    '',
                    'clerk',
                    '',
                    '1',
                    'clerk_user',
                    '1'
                ],
                [
                    'clerk_log',
                    '核销记录',
                    'el-icon-data-line',
                    'C',
                    '2',
                    'clerk',
                    '0',
                    '0',
                    '1',
                    '0',
                    '/plugins/clerk/log',
                    'plugins/clerk/log',
                    null,
                    'plugin/clerk/log',
                    null,
                    '',
                    'clerk',
                    '',
                    '1',
                    'clerk_log',
                    '1'
                ],
            ]
        );
    }

    public function safeDown()
    {
        \app\models\Menu::deleteAll(['plugin' => 'clerk']);
    }
}
