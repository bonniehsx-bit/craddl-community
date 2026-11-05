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
 * 清理:删掉充值档位表里已作废的 asset_type 列。
 *
 * 商品化之后到账钱包配在**商品**上(plugin_withdraw_recharge_goods.asset_type),
 * 一个商品一个钱包。档位表这一列不再有任何读取方,却因为 NOT NULL,
 * 每次存档位都得跟着商品冗余写一份 —— 纯粹的认知负担和出错面
 * (哪天有人忘了同步,就会出现「档位说充积分、商品说充余额」的分裂状态)。
 *
 * 当前 0 行数据,直接删干净。
 * 将来真要做「同一商品多钱包」,那时的字段设计也不会长成现在这样,不值得为它留坑。
 */
class m260821_120200_drop_recharge_plan_asset_type extends Migration
{
    public function safeUp()
    {
        $this->dropColumn('{{%plugin_withdraw_recharge_plan}}', 'asset_type');
    }

    public function safeDown()
    {
        $this->addColumn('{{%plugin_withdraw_recharge_plan}}', 'asset_type',
            $this->string(32)->notNull()->defaultValue('balance')->comment('到账资产(已作废)'));
    }
}
