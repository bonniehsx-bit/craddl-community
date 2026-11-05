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
 * 后台「商城管理」下新增「订单列表配置」菜单
 * 对应 admin/src/views/mall/order-config/index.vue
 * 镜像现有 mall-setting(商城设置)菜单行,parent_id='mall'。
 */
class m260628_120000_order_list_config_menu extends Migration
{
    public function safeUp()
    {
        // 幂等:先删旧行
        \app\models\Menu::deleteAll(['menu_id' => 'mall-order-config']);

        $this->batchInsert('{{%menu}}',
            [
                'menu_id', 'menu_name', 'icon', 'menu_type', 'order_num', 'parent_id',
                'is_cache', 'hidden', 'is_frame', 'status', 'path', 'component', 'query',
                'perms', 'deleted_at', 'link', 'plugin', 'active_menu', 'breadcrumb',
                'name', 'show_tag',
            ],
            [[
                'mall-order-config', '订单配置', 'el-icon-tickets', 'C', '20', 'mall',
                '1', '0', '1', '0', 'order-config', 'mall/order-config/index', null,
                '', '0', '', 'mall', '', '1',
                'mall-order-config', '1',
            ]]
        );
    }

    public function safeDown()
    {
        \app\models\Menu::deleteAll(['menu_id' => 'mall-order-config']);
    }
}
