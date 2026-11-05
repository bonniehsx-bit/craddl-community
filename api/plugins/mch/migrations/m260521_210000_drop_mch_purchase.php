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
 * 清理 mch 插件中已废弃的「采购」功能。
 * mch 自带的采购已被独立的 purchase 插件取代，此处删除残留：
 *  - 后台「商家采购」菜单 mch-purchase
 *  - 两张无 plugin_ 前缀的采购表 jxmall_purchase_order / jxmall_purchase_order_detail
 *
 * 注意：独立 purchase 插件的表是 jxmall_plugin_purchase_order（带 plugin_ 前缀），与此无关，不要误删。
 */
class m260521_210000_drop_mch_purchase extends Migration
{
    public function safeUp()
    {
        // 删除后台「商家采购」菜单
        \app\models\Menu::deleteAll(['menu_id' => 'mch-purchase']);

        // drop 采购订单表（存在才删，避免报错）
        foreach (['{{%purchase_order}}', '{{%purchase_order_detail}}'] as $table) {
            if ($this->db->getTableSchema($table, true) !== null) {
                $this->dropTable($table);
            }
        }
    }

    public function safeDown()
    {
        return false;
    }
}
