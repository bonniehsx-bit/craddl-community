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
 * 优惠券管理迁入营销插件:
 *  - 删除核心「营销管理(market)」菜单及其子项(优惠券/优惠券编辑/用户持有)
 *  - 在营销插件(marketing)菜单下重挂「优惠券」「用户持有」
 * 券的模型/CouponService/结算仍在核心,此处仅迁移后台管理界面归属。
 */
class m260703_130000_marketing_coupon_menu extends Migration
{
    public function safeUp()
    {
        // 1. 移除核心 market 菜单(整棵就是优惠券,无其它营销项)
        \app\models\Menu::deleteAll([
            'menu_id' => ['market', 'market-coupon', 'market-coupon-edit', 'market-user-coupon-list'],
        ]);
        // 清理旧插件菜单占位(幂等)
        \app\models\Menu::deleteAll(['menu_id' => ['marketing_coupon', 'marketing_user_coupon']]);

        $columns = [
            "menu_id", "menu_name", "icon", "menu_type", "order_num", "parent_id",
            "is_cache", "hidden", "is_frame", "status", "path", "component", "query",
            "perms", "deleted_at", "link", "plugin", "active_menu", "breadcrumb",
            "name", "show_tag",
        ];

        $this->batchInsert('{{%menu}}', $columns, [
            [
                'menu_id' => 'marketing_coupon', 'menu_name' => '优惠券', 'icon' => 'el-icon-ticket',
                'menu_type' => 'C', 'order_num' => '3', 'parent_id' => 'marketing',
                'is_cache' => '0', 'hidden' => '0', 'is_frame' => '1', 'status' => '0',
                'path' => '/plugins/marketing/coupon/index', 'component' => 'plugins/marketing/coupon/index',
                'query' => null, 'perms' => 'plugin/marketing/mall/coupon/list',
                'deleted_at' => '0', 'link' => '', 'plugin' => 'marketing', 'active_menu' => '',
                'breadcrumb' => '1', 'name' => 'marketing_coupon', 'show_tag' => '1',
            ],
            [
                'menu_id' => 'marketing_user_coupon', 'menu_name' => '用户持有', 'icon' => 'el-icon-folder-opened',
                'menu_type' => 'C', 'order_num' => '4', 'parent_id' => 'marketing',
                'is_cache' => '0', 'hidden' => '0', 'is_frame' => '1', 'status' => '0',
                'path' => '/plugins/marketing/coupon/user-coupon-list', 'component' => 'plugins/marketing/coupon/user-coupon-list',
                'query' => null, 'perms' => 'plugin/marketing/mall/user-coupon/list',
                'deleted_at' => '0', 'link' => '', 'plugin' => 'marketing', 'active_menu' => '',
                'breadcrumb' => '1', 'name' => 'marketing_user_coupon', 'show_tag' => '1',
            ],
        ]);
    }

    public function safeDown()
    {
        \app\models\Menu::deleteAll(['menu_id' => ['marketing_coupon', 'marketing_user_coupon']]);
    }
}
