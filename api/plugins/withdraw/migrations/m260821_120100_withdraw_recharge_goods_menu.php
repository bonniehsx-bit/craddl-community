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
 * 充值商品化 —— 后台菜单从「充值档位」改为「充值商品」。
 *
 * 档位不再是独立配置页,而是充值商品编辑页里的一个 tab
 * (核心 /mall/goods/edit → GOODS_AFTER_SAVE → RechargeGoodsListener)。
 *
 * 幂等:只动这两个 menu_id,不碰提现自己的菜单。
 */
class m260821_120100_withdraw_recharge_goods_menu extends Migration
{
    private const OLD = 'withdraw_recharge_plan';
    private const NEW = 'withdraw_recharge_goods';

    public function safeUp()
    {
        \app\models\Menu::deleteAll(['menu_id' => [self::OLD, self::NEW]]);

        $this->batchInsert('{{%menu}}',
            [
                'menu_id', 'menu_name', 'icon', 'menu_type', 'order_num', 'parent_id',
                'is_cache', 'hidden', 'is_frame', 'status', 'path', 'component', 'query',
                'perms', 'deleted_at', 'link', 'plugin', 'active_menu', 'breadcrumb', 'name', 'show_tag',
            ],
            [[
                self::NEW, '充值商品', 'shopping', 'C', '4', 'withdraw',
                '0', '0', '1', '0',
                '/plugins/withdraw/recharge/goods', 'plugins/withdraw/recharge/goods', null,
                'plugin/withdraw/admin/goods/index', '0', '', 'withdraw', '', '1',
                self::NEW, '1',
            ]]
        );
    }

    public function safeDown()
    {
        \app\models\Menu::deleteAll(['menu_id' => self::NEW]);
    }
}
