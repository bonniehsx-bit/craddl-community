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
 * 给提现渠道/单据补「到账方式」字段 destination。
 *
 * 一个渠道的网关(fund_class)可能支持多种到账方式（如 ManualFund 支持 bank/wechat/alipay），
 * payout() 必须收到具体的 channel key。destination 即该渠道实际使用的到账方式，
 * 取值须落在所选网关 getSupportedChannels() 之内（保存时校验）。
 */
class m260530_100200_add_destination_to_withdraw extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%plugin_withdraw_channel}}', 'destination',
            $this->string(20)->null()->after('fund_class')->comment('到账方式key:bank/alipay/wechat/balance，须在网关支持范围内'));

        $this->addColumn('{{%plugin_withdraw_order}}', 'destination',
            $this->string(20)->null()->after('fund_class')->comment('到账方式key快照'));
    }

    public function safeDown()
    {
        $this->dropColumn('{{%plugin_withdraw_channel}}', 'destination');
        $this->dropColumn('{{%plugin_withdraw_order}}', 'destination');
    }
}
