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
 * 售后类型老编号 0 改成现在的 2(退货退款)。
 *
 * 2026-01-31 改退款时给售后类型重新编了号,老数据没跟着改:
 *   · 老编号:0 = 退货退款、1 = 换货
 *   · 新编号:1 = 仅退款、2 = 退货退款、3 = 换货
 * 类型 0 在新代码里不存在,后台列表只给 1 / 2 出「确认打款」、给 3 出「完成换货」,
 * 于是类型 0 的售后停在「处理中」却没有任何操作按钮(实测 bonnie 的 RF2512296804337013)。
 * 0 只可能是老的「退货退款」,直接改成 2。
 *
 * 老的 1(换货)不动:改编号之后新建的 1 是「仅退款」,两种混在一起分不出来。
 * 改编号之前、还没处理完的 1,请人工按实际情况核对(bonnie 上那两张都已完成)。
 * 重复执行安全。
 */
class m260928_100000_normalize_legacy_order_refund_type extends Migration
{
    public function safeUp()
    {
        $affected = $this->db->createCommand()
            ->update('{{%order_refund}}', ['type' => 2], ['type' => 0])
            ->execute();
        echo "    > 售后类型 0 → 2(退货退款): {$affected} 行\n";
    }

    public function safeDown()
    {
        // 不回滚:改回 0 等于重新制造「处理中却没有按钮」的单
        echo "m260928_100000_normalize_legacy_order_refund_type 不支持回滚(原值是新代码不认识的老编号)\n";
        return true;
    }
}
