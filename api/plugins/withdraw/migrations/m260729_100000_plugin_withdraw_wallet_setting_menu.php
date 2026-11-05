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
 * 提现插件 —— 后台「扩展钱包设置」菜单(挂在 提现中心 下)
 *
 * 控制本商城是否挂载各扩展钱包(插件 getWallets 声明的),以及能否作为提现来源。
 * 与同级的「钱包流水」区分:那边看的是入账/扣减明细并能手动调余额,这里只管开关。
 * 对应 admin/src/views/plugins/withdraw/wallet-setting/index.vue
 * 接口 /plugin/withdraw/admin/wallet-setting/config
 *
 * order_num=4:排在「提现设置(3)」「钱包流水(3)」之后。
 * 幂等:只删本行,可重复执行。
 */
class m260729_100000_plugin_withdraw_wallet_setting_menu extends Migration
{
    public function safeUp()
    {
        // 该菜单最初错放在核心「商城管理」下(menu_id=mall-wallet-setting),
        // 归属应随插件走 —— 一并清掉旧行,免得两个入口并存。
        \app\models\Menu::deleteAll(['menu_id' => ['withdraw_wallet_setting', 'mall-wallet-setting']]);

        $this->batchInsert('{{%menu}}',
            [
                'menu_id', 'menu_name', 'icon', 'menu_type', 'order_num', 'parent_id',
                'is_cache', 'hidden', 'is_frame', 'status', 'path', 'component', 'query',
                'perms', 'deleted_at', 'link', 'plugin', 'active_menu', 'breadcrumb', 'name', 'show_tag',
            ],
            [[
                'withdraw_wallet_setting', '钱包设置', 'el-icon-wallet', 'C', '4', 'withdraw',
                '0', '0', '1', '0', '/plugins/withdraw/wallet-setting/index', 'plugins/withdraw/wallet-setting/index', null,
                null, '0', '', 'withdraw', '', '1', 'withdraw_wallet_setting', '1',
            ]]
        );
    }

    public function safeDown()
    {
        \app\models\Menu::deleteAll(['menu_id' => 'withdraw_wallet_setting']);
    }
}
