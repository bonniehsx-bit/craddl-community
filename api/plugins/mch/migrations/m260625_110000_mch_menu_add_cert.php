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
 * 在「多商户」菜单下新增「证照库」管理页(入驻字段可配置改造)。
 *
 * 按 §2.2.1:菜单只能走迁移管理。这里只幂等地加/覆盖单个菜单项(按 menu_id 先删后插),
 * 不动 mch 其它菜单,可安全重跑。页面组件:admin/src/views/plugins/mch/cert-def.vue
 */
class m260625_110000_mch_menu_add_cert extends Migration
{
    public function init()
    {
        $this->db = 'db';
        parent::init();
    }

    public function safeUp()
    {
        \app\models\Menu::deleteAll(['menu_id' => 'mch-cert-def']);
        $this->batchInsert(
            '{{%menu}}',
            [
                'menu_id', 'menu_name', 'icon', 'menu_type', 'order_num', 'parent_id',
                'is_cache', 'hidden', 'is_frame', 'status', 'path', 'component', 'name',
                'plugin', 'perms', 'breadcrumb', 'show_tag', 'active_menu', 'query',
            ],
            [
                [
                    'menu_id'     => 'mch-cert-def',
                    'menu_name'   => '证照库',
                    'icon'        => 'el-icon-postcard',
                    'menu_type'   => 'C',
                    'order_num'   => '24',          // 排在「插件配置」(25)前
                    'parent_id'   => 'mch',
                    'is_cache'    => '1',
                    'hidden'      => '0',
                    'is_frame'    => '1',
                    'status'      => '0',
                    'path'        => '/plugins/mch/cert-def',
                    'component'   => 'plugins/mch/cert-def',
                    'name'        => 'plugins-mch-cert-def',
                    'plugin'      => 'mch',
                    'perms'       => '',
                    'breadcrumb'  => '1',
                    'show_tag'    => '1',
                    'active_menu' => '',
                    'query'       => '',
                ],
            ]
        );
    }

    public function safeDown()
    {
        $this->delete('{{%menu}}', ['menu_id' => 'mch-cert-def']);
    }
}
