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
 * 后台「用户管理 → 团队数据」菜单(展示 user_team_stat)。
 * 组件 admin/src/views/user/team-stat.vue,接口 /mall/user/team-stat。
 */
class m260702_170000_user_team_stat_menu extends Migration
{
    public function safeUp()
    {
        $exists = (new Query())->from('{{%menu}}')->where(['menu_id' => 'user-team-stat'])->exists();
        if ($exists) {
            echo "    > 菜单 user-team-stat 已存在,跳过\n";
            return;
        }
        $this->insert('{{%menu}}', [
            'menu_id'     => 'user-team-stat',
            'menu_name'   => '团队数据',
            'icon'        => 'el-icon-s-data',
            'menu_type'   => 'C',
            'order_num'   => 5,
            'parent_id'   => 'user',   // 挂在「用户管理」下
            'is_cache'    => 1,
            'hidden'      => 0,
            'is_frame'    => 1,
            'status'      => 0,
            'path'        => 'team-stat',
            'component'   => 'user/team-stat',
            'query'       => '',
            'perms'       => '',
            'link'        => '',
            'plugin'      => 'mall',
            'active_menu' => '',
            'breadcrumb'  => 1,
            'name'        => 'user-team-stat',
            'show_tag'    => 1,
        ]);
    }

    public function safeDown()
    {
        $this->delete('{{%menu}}', ['menu_id' => 'user-team-stat']);
    }
}
