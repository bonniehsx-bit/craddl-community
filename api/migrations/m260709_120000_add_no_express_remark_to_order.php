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
 * Class m260709_120000_add_no_express_remark_to_order
 *
 * jxmall_order 加列 no_express_remark:后台「无需物流」发货(send_type=2)时填写的独立备注。
 * 它是核心发货属性(与 send_type/send_at/express_no 同族),独立成列——
 *   - 不占用买家备注 remarks
 *   - 不塞订单扩展位 extra(extra 归各插件业务附加数据,避免与分润/账单等混用)
 * 前端订单详情 order.no_express_remark 直接读取展示。
 */
class m260709_120000_add_no_express_remark_to_order extends Migration
{
    private $table = '{{%order}}';

    public function safeUp()
    {
        $col = $this->db->getTableSchema($this->table, true)->getColumn('no_express_remark');
        if ($col !== null) {
            echo "    no_express_remark 列已存在,跳过\n";
            return;
        }
        $this->addColumn(
            $this->table,
            'no_express_remark',
            $this->string(500)->null()->defaultValue(null)
                ->comment('无需物流发货备注(send_type=2 时填写,与买家备注 remarks 隔离)')
        );
    }

    public function safeDown()
    {
        $col = $this->db->getTableSchema($this->table, true)->getColumn('no_express_remark');
        if ($col !== null) {
            $this->dropColumn($this->table, 'no_express_remark');
        }
    }
}
