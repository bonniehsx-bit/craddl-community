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
 * 商品服务标签:详情页价格下面那排「✓ 全国包邮 / ✓ 支持退款」
 *
 * 原来这排标签是**算出来的** —— 运费按 express_type 推、退款看 is_refund 开关、库存看剩余数,
 * 想加「正品保障」「7天无理由」这类话术一个都加不了,商家也没有任何干预余地。
 * 现在改成勾选:平台和商家各自维护标签,上传商品时选几个,详情页照着显示。
 *
 * 可见范围:mch_id = 0 是平台的标签,全商城的商品都能选;mch_id > 0 是某个商家自建的,
 * 只有他自己的商品能选、也只有他自己能改。两边都按 mall_id 隔离。
 *
 * ⚠ 标签是纯展示话术,不参与任何计算:选了「全国包邮」不会让订单真的不收运费。
 *   运费以商品的运费设置为准(结算走 ShippingCalculator),两者对不上要靠运营自己把关。
 */
class m260920_100000_create_goods_service_tag extends Migration
{
    const T = '{{%goods_service_tag}}';

    /** 建表时给每个商城铺一套常用标签,后台开箱就有得选 */
    const PRESET = ['全国包邮', '支持退款', '7天无理由退货', '正品保障', '闪电发货', '售后无忧'];

    public function safeUp()
    {
        if ($this->db->schema->getTableSchema(self::T) === null) {
            $this->createTable(self::T, [
                'id'      => $this->primaryKey(),
                'mall_id' => $this->integer()->notNull()->defaultValue(0)->comment('商城 ID'),
                'mch_id'  => $this->integer()->notNull()->defaultValue(0)->comment('0=平台标签(全商城可选);>0=该商家自建,只有他的商品能选'),
                'name'    => $this->string(30)->notNull()->defaultValue('')->comment('标签文字,如「全国包邮」'),
                'sort'    => $this->integer()->notNull()->defaultValue(0)->comment('排序,小的在前'),
                'status'  => $this->tinyInteger(1)->notNull()->defaultValue(1)->comment('1=启用 0=停用(停用后详情页不显示,已选中的商品也不显示)'),
            ]);
            $this->createIndex('idx_goods_service_tag_scope', self::T, ['mall_id', 'mch_id', 'status']);
        } else {
            echo "    > 表 goods_service_tag 已存在，跳过建表。\n";
        }

        $goods = $this->db->schema->getTableSchema('{{%goods}}');
        if ($goods !== null && $goods->getColumn('service_tags') === null) {
            $this->addColumn('{{%goods}}', 'service_tags', $this->text()->null()->comment('详情页服务标签:标签 ID 数组 JSON,如 [1,3,5]'));
        } else {
            echo "    > goods.service_tags 已存在，跳过加列。\n";
        }

        $this->seedPreset();
    }

    /**
     * 给每个已有商城铺一套平台标签。已经有平台标签的商城不动(重跑安全、也不覆盖运营改过的)。
     */
    private function seedPreset()
    {
        $malls = $this->db->createCommand('SELECT id FROM ' . $this->db->quoteTableName('{{%mall}}') . ' WHERE is_delete = 0')->queryColumn();
        $now = time();
        $nowTime = date('Y-m-d H:i:s', $now);
        $added = 0;
        foreach ($malls as $mallId) {
            $exists = $this->db->createCommand(
                'SELECT COUNT(1) FROM ' . $this->db->quoteTableName(self::T) . ' WHERE mall_id = :m AND mch_id = 0 AND is_delete = 0',
                [':m' => $mallId]
            )->queryScalar();
            if ($exists > 0) {
                continue;
            }
            $rows = [];
            foreach (self::PRESET as $i => $name) {
                $rows[] = [(int)$mallId, 0, $name, $i * 10, 1, 0, $now, $now, $nowTime, $nowTime];
            }
            $this->db->createCommand()->batchInsert(self::T,
                ['mall_id', 'mch_id', 'name', 'sort', 'status', 'is_delete', 'created_at', 'updated_at', 'created_time', 'updated_time'],
                $rows)->execute();
            $added += count($rows);
        }
        echo "    > 铺了 {$added} 条平台标签(" . count($malls) . " 个商城)。\n";
    }

    public function safeDown()
    {
        $goods = $this->db->schema->getTableSchema('{{%goods}}');
        if ($goods !== null && $goods->getColumn('service_tags') !== null) {
            $this->dropColumn('{{%goods}}', 'service_tags');
        }
        if ($this->db->schema->getTableSchema(self::T) !== null) {
            $this->dropTable(self::T);
        }
    }
}
