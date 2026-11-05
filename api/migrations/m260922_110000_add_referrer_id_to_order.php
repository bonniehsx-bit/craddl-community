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
 * 订单加「推荐人」快照列 referrer_id:下单那一刻买家的直属上级(User.parent_id)
 *
 * 后台订单列表的「推荐人」原来是现查买家当前上级,后台一改上级,历史订单的推荐人跟着全变,
 * 和当时的分佣对不上。改成下单时落一份,之后只认这一列。
 *
 * 写入:只在下单时由 CreateOrderRecordStage 按当时的 parent_id 填一次,之后不再改。
 *       这一列只记录「下单那会儿推荐人是谁」,不参与任何业务逻辑;下单时还没上级的就是 0。
 * 老数据:按买家**现在的**上级回填(改上级的情况很少,用户定的口径)。
 *        parent_id 为 -1(平台直属)/ -2(收银台游客)不算推荐人,保持 0。
 *
 * 不用 safeUp:回填按 id 分段、每段自动提交,避免一个大事务长时间锁整张订单表。
 * 幂等:列 / 索引已存在就跳过;回填只动 referrer_id=0 的行,重跑不会覆盖已有快照。
 */
class m260922_110000_add_referrer_id_to_order extends Migration
{
    const T = '{{%order}}';
    const IDX = 'idx_order_mall_referrer';
    const BATCH = 5000;

    public function up()
    {
        $schema = $this->db->schema->getTableSchema(self::T, true);
        if ($schema->getColumn('referrer_id') === null) {
            $this->addColumn(self::T, 'referrer_id', $this->integer()->notNull()->defaultValue(0)
                ->comment('推荐人快照:下单时买家的直属上级 user.id,0=无')->after('user_level'));
        }
        if (!$this->hasIndex(self::IDX)) {
            $this->createIndex(self::IDX, self::T, ['mall_id', 'referrer_id']);
        }

        $order = $this->db->quoteTableName($this->db->schema->getRawTableName(self::T));
        $user = $this->db->quoteTableName($this->db->schema->getRawTableName('{{%user}}'));
        // 按真实 id 翻页取每段的上界,别按 id 区间步进:id 可能被个别大 id 顶到上亿,区间步进会空转几十万次
        $filled = 0;
        $lastId = 0;
        while (true) {
            $upper = $this->db->createCommand(
                "SELECT MAX(id) FROM (SELECT id FROM {$order} WHERE id > :last ORDER BY id LIMIT " . self::BATCH . ") t",
                [':last' => $lastId]
            )->queryScalar();
            if (!$upper) {
                break;
            }
            $filled += $this->db->createCommand(
                "UPDATE {$order} o JOIN {$user} u ON u.id = o.user_id
                 SET o.referrer_id = u.parent_id
                 WHERE o.id > :last AND o.id <= :upper AND o.referrer_id = 0 AND u.parent_id > 0",
                [':last' => $lastId, ':upper' => (int)$upper]
            )->execute();
            $lastId = (int)$upper;
        }
        echo "    > 回填推荐人 {$filled} 单\n";
    }

    public function down()
    {
        if ($this->hasIndex(self::IDX)) {
            $this->dropIndex(self::IDX, self::T);
        }
        if ($this->db->schema->getTableSchema(self::T, true)->getColumn('referrer_id') !== null) {
            $this->dropColumn(self::T, 'referrer_id');
        }
    }

    private function hasIndex(string $name): bool
    {
        $table = $this->db->schema->getRawTableName(self::T);
        return (bool)$this->db->createCommand(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :t AND index_name = :i',
            [':t' => $table, ':i' => $name]
        )->queryScalar();
    }
}
