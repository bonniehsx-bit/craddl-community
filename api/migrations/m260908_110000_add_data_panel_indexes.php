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
 * Class m260908_110000_add_data_panel_indexes
 *
 * 为「数据概览 / 数据栏」页面补三条复合索引。
 *
 * 【背景】用户量涨到几十万后该页面明显变慢。实测(EXPLAIN)瓶颈有三处,
 * 共同点是:过滤条件里的时间/排序列都不在索引中,命中前两列后仍要在
 * 「本商城全部行」上逐行过滤或排序 —— 数据量越大越慢,且与订单量无关,
 * 所以订单不多的商城一样卡。
 *
 * 1) user (mall_id, is_delete, created_at)
 *    「今日新增用户」:原本命中 idx-user-mall_id_is_delete(key_len=5)后,
 *    要在该商城的全部用户上过滤 created_at —— 30 万用户就扫 30 万条索引项,
 *    只为找出今天新增的几条。
 *    实测:type=ref key_len=5 Using where → type=range key_len=10 Using index(覆盖索引)
 *    顺带「总用户数」也变成覆盖索引扫描。
 *    命中方:DashboardController::actionData(顶级数据概览)、MallDataForm(数据栏)。
 *
 * 2) order (mall_id, is_delete, created_at)
 *    数据栏主统计按 created_at 取时间范围,原本优化器直接放弃索引走全表扫描。
 *    实测:type=ALL rows=502 → type=range rows=1
 *    (注:上一条 migration 加的 idx_order_mall_pay_at 走的是 pay_at,两者面向不同查询,不重复。)
 *
 * 3) goods (mall_id, is_delete, sales)
 *    商品销量 TOP10 的 ORDER BY sales DESC 原本要 filesort 整个商城的商品。
 *    实测:Using filesort → Backward index scan(直接倒序取前 10,无需排序)
 *
 * 幂等:逐条判断,已存在则跳过。
 * ⚠ 大表加索引会锁写,InnoDB 走 ONLINE DDL(INPLACE)但仍建议放业务低峰执行。
 */
class m260908_110000_add_data_panel_indexes extends Migration
{
    /** [索引名, 表, 列] */
    private array $indexes = [
        ['idx_user_mall_created',  '{{%user}}',  ['mall_id', 'is_delete', 'created_at']],
        ['idx_order_mall_created', '{{%order}}', ['mall_id', 'is_delete', 'created_at']],
        ['idx_goods_mall_sales',   '{{%goods}}', ['mall_id', 'is_delete', 'sales']],
    ];

    private function indexExists(string $table, string $index): bool
    {
        $schema = $this->db->getTableSchema($table, true);
        if ($schema === null) {
            return false;
        }
        foreach ($this->db->createCommand("SHOW INDEX FROM {$schema->fullName}")->queryAll() as $r) {
            if (($r['Key_name'] ?? '') === $index) {
                return true;
            }
        }
        return false;
    }

    public function safeUp()
    {
        foreach ($this->indexes as [$name, $table, $cols]) {
            if ($this->db->getTableSchema($table, true) === null) {
                echo "    {$table} 不存在,跳过 {$name}\n";
                continue;
            }
            if ($this->indexExists($table, $name)) {
                echo "    索引 {$name} 已存在,跳过\n";
                continue;
            }
            echo "    创建 {$name} on {$table} (" . implode(', ', $cols) . ") ...\n";
            $this->createIndex($name, $table, $cols);
        }
    }

    public function safeDown()
    {
        foreach ($this->indexes as [$name, $table, $cols]) {
            if ($this->db->getTableSchema($table, true) === null || !$this->indexExists($table, $name)) {
                echo "    索引 {$name} 不存在,跳过\n";
                continue;
            }
            $this->dropIndex($name, $table);
        }
    }
}
