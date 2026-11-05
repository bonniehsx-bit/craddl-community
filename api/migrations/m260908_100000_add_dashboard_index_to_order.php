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
 * Class m260908_100000_add_dashboard_index_to_order
 *
 * 给 jxmall_order 加一条面向「平台数据概览」的复合索引 (mall_id, is_delete, pay_status, pay_at)。
 *
 * 【为什么】多商户数据概览的每张卡片、趋势图、Top 榜都建立在同一个过滤条件上:
 *     mall_id = ? AND is_delete = 0 AND pay_status IN (1,2,3) AND pay_at BETWEEN ? AND ?
 *   表上原有的 idx-order-mall_id_is_delete 只覆盖前两列,命中后仍要把该商城的全部订单
 *   逐行过滤 pay_status 与 pay_at。dev 实测(492 行)优化器直接放弃索引走全表扫描;
 *   订单量上到几十万后,看板每打开一次就是数次全表扫描,还会连带冲刷 buffer pool、
 *   拖慢同库的其它查询。
 *
 * 【列顺序依据】等值(mall_id / is_delete)在前,IN 列表(pay_status)居中,范围(pay_at)在最后 ——
 *   范围列之后的列无法再用于索引查找,所以 pay_at 必须垫底。
 *   mch_id > 0 是范围谓词且选择性低,故意不入索引,留给回表后过滤。
 *
 * 【实测(dev,FORCE INDEX 验证列顺序可用)】
 *   主查询: type=ALL rows=502  →  type=range key_len=11 rows=3
 *   趋势查询(30天 GROUP BY): 同样命中 range
 *
 * 幂等:已存在则跳过,可重复执行。
 */
class m260908_100000_add_dashboard_index_to_order extends Migration
{
    private $table = '{{%order}}';
    private $index = 'idx_order_mall_pay_at';

    private function indexExists(): bool
    {
        $schema = $this->db->getTableSchema($this->table, true);
        if ($schema === null) {
            return false;
        }
        $rows = $this->db->createCommand("SHOW INDEX FROM {$schema->fullName}")->queryAll();
        foreach ($rows as $r) {
            if (($r['Key_name'] ?? '') === $this->index) {
                return true;
            }
        }
        return false;
    }

    public function safeUp()
    {
        if ($this->db->getTableSchema($this->table, true) === null) {
            echo "    {$this->table} 不存在,跳过\n";
            return;
        }
        if ($this->indexExists()) {
            echo "    索引 {$this->index} 已存在,跳过\n";
            return;
        }

        // ⚠ 大表加索引会锁写。MySQL 5.6+ / InnoDB 支持 ONLINE DDL(ALGORITHM=INPLACE),
        //   Yii 的 createIndex 不带算法提示,交由 MySQL 自行选择(默认即 INPLACE)。
        //   生产建议放业务低峰执行。
        echo "    正在为 {$this->table} 创建索引 {$this->index} (mall_id, is_delete, pay_status, pay_at) ...\n";
        $this->createIndex($this->index, $this->table, ['mall_id', 'is_delete', 'pay_status', 'pay_at']);
    }

    public function safeDown()
    {
        if ($this->db->getTableSchema($this->table, true) === null || !$this->indexExists()) {
            echo "    索引 {$this->index} 不存在,跳过\n";
            return;
        }
        $this->dropIndex($this->index, $this->table);
    }
}
