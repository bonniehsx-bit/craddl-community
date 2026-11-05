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
 * 充值档位:每人累计限购次数。
 *
 * 与已有的 day_limit 是两个维度,可以同时配:
 *   - day_limit   每人每天最多充几次,次日 0 点重置(控节奏)
 *   - total_limit 每人**累计**最多充几次,充完就永远没有了(控总量)
 *
 * 典型用法:"新人首充 1 元得 10 元"这种亏本引流档,只能让每人买一次 ——
 * 光靠 day_limit=1 挡不住,用户每天都能再来一次。
 *
 * 0 = 不限制(默认),存量档位行为不变。
 */
class m260822_140000_add_total_limit_to_recharge_plan extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%plugin_withdraw_recharge_plan}}', 'total_limit',
            $this->integer()->notNull()->defaultValue(0)
                ->comment('每人累计限购次数,0=不限。与 day_limit 并存,两个都要满足'));
    }

    public function safeDown()
    {
        $this->dropColumn('{{%plugin_withdraw_recharge_plan}}', 'total_limit');
    }
}
