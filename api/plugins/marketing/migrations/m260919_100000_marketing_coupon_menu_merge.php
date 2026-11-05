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
 * 「优惠券」「用户优惠券」两个菜单合并为「发券管理」
 *
 * 页面 plugins/marketing/coupon/index 改成页签容器(优惠券列表 / 用户优惠券),
 * 路径和组件不变,只改菜单名;「用户优惠券」菜单删掉。
 * 只改仍是旧名的行,不覆盖后台已手动改过的名字。
 */
class m260919_100000_marketing_coupon_menu_merge extends Migration
{
    public function safeUp()
    {
        $this->update('{{%menu}}', ['menu_name' => '发券管理'],
            ['menu_id' => 'marketing_coupon', 'plugin' => 'marketing', 'menu_name' => '优惠券']);
        $this->delete('{{%menu}}', ['menu_id' => 'marketing_user_coupon', 'plugin' => 'marketing']);
    }

    public function safeDown()
    {
        $this->update('{{%menu}}', ['menu_name' => '优惠券'],
            ['menu_id' => 'marketing_coupon', 'plugin' => 'marketing', 'menu_name' => '发券管理']);

        if (!(new \yii\db\Query())->from('{{%menu}}')->where(['menu_id' => 'marketing_user_coupon'])->exists()) {
            $this->insert('{{%menu}}', [
                'menu_id' => 'marketing_user_coupon', 'menu_name' => '用户优惠券', 'icon' => 'el-icon-folder-opened',
                'menu_type' => 'C', 'order_num' => '4', 'parent_id' => 'marketing',
                'is_cache' => '0', 'hidden' => '0', 'is_frame' => '1', 'status' => '0',
                'path' => '/plugins/marketing/coupon/user-coupon-list', 'component' => 'plugins/marketing/coupon/user-coupon-list',
                'query' => null, 'perms' => 'plugin/marketing/mall/user-coupon/list',
                'deleted_at' => '0', 'link' => '', 'plugin' => 'marketing', 'active_menu' => '',
                'breadcrumb' => '1', 'name' => 'marketing_user_coupon', 'show_tag' => '1',
            ]);
        }
    }
}
