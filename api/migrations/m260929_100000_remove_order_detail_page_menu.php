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
 * 下线后台独立的「订单详情」页(order/detail)。
 *
 * 订单详情统一用全局抽屉($previewOrder / <order-trigger>),售后也在抽屉里弹窗处理,
 * 独立页面文件 admin/src/views/order/detail.vue 已删除。菜单(隐藏路由)留着,
 * 有人拿老链接 #/order/detail?id= 打开就是找不到组件的坏页,所以一起摘掉。
 *
 * 连带清掉角色授权(role_menu)和商城菜单自定义(mall_menu_option)里挂着的这一项,
 * 不然角色编辑里会勾着一个已不存在的菜单。
 */
class m260929_100000_remove_order_detail_page_menu extends Migration
{
    /** 见 m260106_011912_export_menu 第 33 行 */
    private const MENU_ID = 'order-detail';

    public function safeUp()
    {
        $menu = $this->db->schema->getRawTableName('{{%menu}}');
        if ($this->db->schema->getTableSchema($menu, true) === null) {
            echo "    > 菜单表不存在,跳过\n";
            return true;
        }

        // 带上 component 一起判,别误删别人同名的菜单
        $n = $this->db->createCommand()
            ->delete($menu, ['menu_id' => self::MENU_ID, 'component' => 'order/detail'])
            ->execute();
        echo "    > 已移除后台菜单 " . self::MENU_ID . "(影响 {$n} 行)\n";

        foreach (['{{%role_menu}}', '{{%mall_menu_option}}'] as $t) {
            $raw = $this->db->schema->getRawTableName($t);
            if ($this->db->schema->getTableSchema($raw, true) === null) {
                continue;
            }
            $n = $this->db->createCommand()->delete($raw, ['menu_id' => self::MENU_ID])->execute();
            echo "    > {$raw} 清掉 {$n} 行\n";
        }
        return true;
    }

    /**
     * 不恢复 —— 页面文件已删,恢复菜单只会得到一个点进去空白的坏链接。
     */
    public function safeDown()
    {
        echo "    > 订单详情独立页已删除,不恢复菜单\n";
        return true;
    }
}
