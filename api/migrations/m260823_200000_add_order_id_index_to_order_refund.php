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
 * order_refund 补 order_id 索引。
 *
 * ============================================================
 * 为什么现在才发现
 * ============================================================
 * 这张表原有的索引只有 (mall_id, is_delete) 和 (user_id, is_delete),
 * 而代码里**四处**热点查询都是按 order_id 查:
 *
 *   OrderService::cancel()                  取消前查有无进行中售后(每次关单都走)
 *   OrderService::pendingReturnNotice()     强制关单前查退货在途
 *   RefundService::updateOrderStatusAfterAction()  每笔退款成功后都要数一次
 *   RefundService::calculateMaxRefundPrice…       申请售后时算已退总额
 *
 * 实测 EXPLAIN: type=ALL key=NULL —— 全表扫描。
 * 开发库只有 7 行看不出来,售后单在真实商城是持续增长的,
 * 而上面几处都在下单/退款的主链路上,一次关单就要扫一遍全表。
 *
 * 带上 is_delete 做复合索引,四处查询都带这个条件。
 */
class m260823_200000_add_order_id_index_to_order_refund extends Migration
{
    private $table = '{{%order_refund}}';
    private $idx   = 'idx-order_refund-order_id_is_delete';

    public function safeUp()
    {
        if ($this->indexExists()) {
            echo "    > 索引已存在,跳过\n";
            return;
        }
        $this->createIndex($this->idx, $this->table, ['order_id', 'is_delete']);
    }

    public function safeDown()
    {
        if ($this->indexExists()) {
            $this->dropIndex($this->idx, $this->table);
        }
    }

    /** 重复执行安全:线上可能已由他人手工加过同名索引 */
    private function indexExists(): bool
    {
        $real = $this->db->getSchema()->getRawTableName($this->table);
        foreach ($this->db->createCommand("SHOW INDEX FROM `{$real}`")->queryAll() as $row) {
            if ($row['Key_name'] === $this->idx) {
                return true;
            }
        }
        return false;
    }
}
