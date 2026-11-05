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
 * 提现插件 —— 充值相关后台菜单（挂在 提现中心 下）
 *   ├─ 充值档位 (withdraw_recharge_plan)  运营配置充多少送多少、进哪个钱包
 *   └─ 充值记录 (withdraw_recharge_order) 查记录 + 卡单重发
 *
 * 幂等：只删这两行，不动 channel/order/wallet 菜单，可重复执行。
 * ⛔ 不能照抄 m260530_100100 里的 deleteAll(['plugin'=>'withdraw']) ——
 *   那会把提现自己的菜单一起抹掉。
 */
class m260821_110100_plugin_withdraw_recharge_menus extends Migration
{
    private const IDS = ['withdraw_recharge_plan', 'withdraw_recharge_order'];

    public function safeUp()
    {
        \app\models\Menu::deleteAll(['menu_id' => self::IDS]);

        $this->batchInsert('{{%menu}}',
            [
                'menu_id', 'menu_name', 'icon', 'menu_type', 'order_num', 'parent_id',
                'is_cache', 'hidden', 'is_frame', 'status', 'path', 'component', 'query',
                'perms', 'deleted_at', 'link', 'plugin', 'active_menu', 'breadcrumb', 'name', 'show_tag',
            ],
            [
                [
                    'withdraw_recharge_plan', '充值档位', 'money', 'C', '4', 'withdraw',
                    '0', '0', '1', '0',
                    '/plugins/withdraw/recharge/plan', 'plugins/withdraw/recharge/plan', null,
                    'plugin/withdraw/admin/recharge/plans', '0', '', 'withdraw', '', '1',
                    'withdraw_recharge_plan', '1',
                ],
                [
                    'withdraw_recharge_order', '充值记录', 'list', 'C', '5', 'withdraw',
                    '0', '0', '1', '0',
                    '/plugins/withdraw/recharge/order', 'plugins/withdraw/recharge/order', null,
                    'plugin/withdraw/admin/recharge/orders', '0', '', 'withdraw', '', '1',
                    'withdraw_recharge_order', '1',
                ],
            ]
        );
    }

    public function safeDown()
    {
        \app\models\Menu::deleteAll(['menu_id' => self::IDS]);
    }
}
