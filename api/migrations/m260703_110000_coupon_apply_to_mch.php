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
 * Class m260703_110000_coupon_apply_to_mch
 *
 * jxmall_coupon 加列 apply_to_mch:平台券(mch_id=0)是否允许用于商家商品。
 *   1 = 允许跨店使用（该 mall 下所有商家商品都能用，优惠金额由平台承担、不扣商家收入）
 *   0 = 仅平台自营（默认，维持原「平台券不跨店」隔离）
 * 仅对平台券(mch_id=0)有意义；商家券(mch_id>0)此列忽略。
 *
 * 匹配放开在 CouponCalculator（本商户券 OR 平台券且 apply_to_mch=1）；
 * 承担方判定沿用 isPlatformCoupon(UserCoupon.mch_id===0)，平台自动买单，结算侧无需改动。
 */
class m260703_110000_coupon_apply_to_mch extends Migration
{
    private $table = '{{%coupon}}';

    public function safeUp()
    {
        $col = $this->db->getTableSchema($this->table, true)->getColumn('apply_to_mch');
        if ($col !== null) {
            echo "    apply_to_mch 列已存在,跳过\n";
            return;
        }
        $this->addColumn(
            $this->table,
            'apply_to_mch',
            $this->tinyInteger(1)->notNull()->defaultValue(0)
                ->comment('平台券是否可用于商家商品:1=可(优惠平台买单),0=仅自营')
        );
    }

    public function safeDown()
    {
        $col = $this->db->getTableSchema($this->table, true)->getColumn('apply_to_mch');
        if ($col !== null) {
            $this->dropColumn($this->table, 'apply_to_mch');
        }
    }
}
