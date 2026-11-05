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
 * 分销插件新增「插件文档」菜单（运营手册）。
 *
 * 与 dealer 的 plugins-dealer-doc 对齐。纯前端静态页、不需要接口，
 * 因此 perms 留空 —— 有本插件菜单权限的人就能看。
 *
 * ⚠ 分销的菜单 id 沿用本插件既有的无前缀风格(distribution / distribution-log / ...)，
 *   不要照抄 dealer 的 plugins-dealer-* 前缀，否则挂不到正确的父级下。
 */
class m260819_190000_add_distribution_doc_menu extends Migration
{
    private $menuId = 'distribution-doc';

    public function safeUp()
    {
        $this->safeDown(); // 幂等：重复执行先清掉旧记录

        $this->insert('{{%menu}}', [
            'menu_id'     => $this->menuId,
            'menu_name'   => '插件文档',
            'icon'        => 'el-icon-notebook-2',
            'menu_type'   => 'C',
            'order_num'   => '99', // 排在本插件菜单最后
            'parent_id'   => 'distribution',
            'is_cache'    => '1',
            'hidden'      => '0',
            'is_frame'    => '1',
            'status'      => '0',
            'path'        => '/plugins/distribution/doc',
            'component'   => 'plugins/distribution/doc',
            'query'       => null,
            'perms'       => null,
            'deleted_at'  => '0',
            'link'        => '',
            'plugin'      => 'distribution',
            'active_menu' => '',
            'breadcrumb'  => '1',
            'name'        => $this->menuId,
            'show_tag'    => '1',
        ]);
    }

    public function safeDown()
    {
        $this->delete('{{%menu}}', ['menu_id' => $this->menuId, 'plugin' => 'distribution']);
    }
}
