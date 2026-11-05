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
 * 提现插件 —— 后台「钱包流水」菜单(挂在 提现中心 下)
 * 看通用钱包(插件 getWallets 声明)的入账/扣减明细。
 * 对应 admin/src/views/plugins/withdraw/wallet/index.vue
 *
 * 幂等:只删本行(不动 channel/order 菜单),可重复执行。
 */
class m260629_110000_plugin_withdraw_wallet_menu extends Migration
{
    public function safeUp()
    {
        \app\models\Menu::deleteAll(['menu_id' => 'withdraw_wallet']);

        $this->batchInsert('{{%menu}}',
            [
                'menu_id', 'menu_name', 'icon', 'menu_type', 'order_num', 'parent_id',
                'is_cache', 'hidden', 'is_frame', 'status', 'path', 'component', 'query',
                'perms', 'deleted_at', 'link', 'plugin', 'active_menu', 'breadcrumb', 'name', 'show_tag',
            ],
            [[
                'withdraw_wallet', '钱包流水', 'documentation', 'C', '3', 'withdraw',
                '0', '0', '1', '0', '/plugins/withdraw/wallet/index', 'plugins/withdraw/wallet/index', null,
                null, '0', '', 'withdraw', '', '1', 'withdraw_wallet', '1',
            ]]
        );
    }

    public function safeDown()
    {
        \app\models\Menu::deleteAll(['menu_id' => 'withdraw_wallet']);
    }
}
