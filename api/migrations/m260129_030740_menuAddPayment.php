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

use yii\db\Schema;
use app\core\Migration;

class m260129_030740_menuAddPayment extends Migration
{

    public function init()
    {
        $this->db = 'db';
        parent::init();
    }

    public function safeUp()
    {
        $this->batchInsert('{{%menu}}',
            ["menu_id", "menu_name", "icon", "menu_type", "order_num", "parent_id", "is_cache", "hidden", "is_frame", "status", "path", "component", "query", "perms", "deleted_at", "link", "plugin", "active_menu", "breadcrumb", "name", "show_tag"],
            [
                [
                    'menu_id' => 'payment-wxwork',
                    'menu_name' => '企业微信支付',
                    'icon' => 'table',
                    'menu_type' => 'C',
                    'order_num' => '50',
                    'parent_id' => 'payment',
                    'is_cache' => '1',
                    'hidden' => '0',
                    'is_frame' => '1',
                    'status' => '0',
                    'path' => 'wxwork',
                    'component' => 'payment/wxwork',
                    'query' => '',
                    'perms' => '',
                    'deleted_at' => '0',
                    'link' => '',
                    'plugin' => 'mall',
                    'active_menu' => '/payment/wxwork',
                    'breadcrumb' => '1',
                    'name' => 'payment-wxwork',
                    'show_tag' => '1',
                ]
            ]
        );
    }

    public function safeDown()
    {
        //$this->truncateTable('{{%menu}} CASCADE');
    }
}
