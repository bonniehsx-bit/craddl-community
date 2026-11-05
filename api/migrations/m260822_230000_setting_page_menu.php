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
use yii\db\Query;

/**
 * 后台「商城 → 设置页菜单」。
 * 组件 admin/src/views/mall/setting-page.vue,接口 /mall/mall/setting-page。
 *
 * 配的是手机端「我的 → 设置」那一页的菜单清单(uniapp others/setting)。
 * 与隔壁「用户中心」是两个独立的 option 键 —— 那边是整份 JSON 覆盖保存,
 * 混在一起两个页面同时开着编辑会互相冲掉。
 */
class m260822_230000_setting_page_menu extends Migration
{
    public function safeUp()
    {
        $exists = (new Query())->from('{{%menu}}')->where(['menu_id' => 'mall-setting-page'])->exists();
        if ($exists) {
            echo "    > 菜单 mall-setting-page 已存在,跳过\n";
            return;
        }
        $this->insert('{{%menu}}', [
            'menu_id'     => 'mall-setting-page',
            'menu_name'   => '设置页菜单',
            'icon'        => 'el-icon-menu',
            'menu_type'   => 'C',
            'order_num'   => 7,          // 紧跟「用户中心」(order_num 6)
            'parent_id'   => 'mall',
            'is_cache'    => 1,
            'hidden'      => 0,
            'is_frame'    => 1,
            'status'      => 0,
            'path'        => 'setting-page',
            'component'   => 'mall/setting-page',
            'query'       => '',
            'perms'       => '',
            'link'        => '',
            'plugin'      => 'mall',
            'active_menu' => '',
            'breadcrumb'  => 1,
            'name'        => 'mall-setting-page',
            'show_tag'    => 1,
        ]);
    }

    public function safeDown()
    {
        $this->delete('{{%menu}}', ['menu_id' => 'mall-setting-page']);
    }
}
