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
 * 给 mch 插件后台加「运营手册」菜单(静态说明页,组件 admin/src/views/plugins/mch/guide.vue)。
 * 内容:订单资金流时序图、结算运作逻辑、配置说明、常见疑问 FAQ。挂在 mch 菜单下。
 *
 * ⚠️ 前端页面(guide.vue)是新增文件,后台需 `cnpm run build:prod` 重新构建后菜单点进去才有内容。
 * 幂等:已存在则跳过。
 */
class m260728_100000_mch_menu_add_guide extends Migration
{
    private string $menuId = 'mch-guide';

    public function safeUp()
    {
        $exists = (new Query())->from('{{%menu}}')->where(['menu_id' => $this->menuId])->exists($this->db);
        if ($exists) {
            echo "    > 菜单 {$this->menuId} 已存在,跳过\n";
            return;
        }

        $this->insert('{{%menu}}', [
            'menu_id'     => $this->menuId,
            'menu_name'   => '运营手册',
            'icon'        => 'el-icon-notebook-2',
            'menu_type'   => 'C',
            'order_num'   => '99',
            'parent_id'   => 'mch',
            'is_cache'    => '1',
            'hidden'      => '0',
            'is_frame'    => '1',
            'status'      => '0',
            'path'        => '/plugins/mch/guide',
            'component'   => 'plugins/mch/guide',
            'name'        => 'plugins-mch-guide',
            'plugin'      => 'mch',
            'perms'       => '',
            'breadcrumb'  => '1',
            'show_tag'    => '1',
            'active_menu' => '',
            'query'       => '',
        ]);
    }

    public function safeDown()
    {
        $this->delete('{{%menu}}', ['menu_id' => $this->menuId]);
    }
}
