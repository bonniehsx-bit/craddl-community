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
 * 订单明细加「商品快照」:下单那一刻的商品名称、图片、编码、原售价、成本价。
 *
 * 为什么要:订单是交易凭证,买家 / 客服 / 对账看到的应该是当时买的东西。原来全系统 49 处
 * 都是 join 商品表读**现在**的名称和图片 —— 商品改名、换图、删除后,历史订单跟着变
 * (真实例子:bonnie 商品 #287 从「测试232323」改成「1元15积分充值」,以前的单现在显示成新名字)。
 * 单价 price / 规格 attr / 能否退款 / 赠送积分 本来就有快照,这次补齐商品本身的信息。
 *
 * 字段口径(新订单在 ProcessOrderDetailsStage 写入,见 OrderDetail::writeGoodsSnapshot):
 *   goods_name     商品名称
 *   goods_pic      商品图片(选了规格且规格有图用规格图,否则商品主图;与下单页显示一致)
 *   goods_no       商品编码(规格的 SKU 编码优先,否则商品货号)
 *   original_price 原售价:规格售价 / 商品售价,即**会员折扣、活动价之前**的单价。
 *                  price 存的是成交单价(会员价 / 活动价),两者之差就是单件优惠
 *   cost_price     成本价(规格成本优先,否则商品成本;口径同 dealer MemberPriceService)
 *
 * 老订单补齐(只补 goods_name 为空的行,可重复执行):
 *   - 名称 / 图片 / 编码 / 成本价:取**补齐时**商品表和规格表的值 —— 历史值已经找不回来了
 *     (改过名的商品,老订单补进去的是新名字;商品被物理删除的行补不上,保持空,读取时自动回退)
 *   - 原售价:用下单时就存了的数据倒推 = 成交单价 + 单件会员折扣(member_discount_price ÷ 数量),
 *     普通订单是准的、不受后来改价影响;活动订单(秒杀/拼团等)没有会员折扣,原售价 = 成交价
 *
 * ⚠ cost_price 是商家敏感数据:买家端接口(api/order/detail、api/order/list)返回订单明细整行,
 *   已在 OrderDetail::scrubForCustomer() 里去掉,新增买家接口返回明细时也要过一遍它。
 */
class m261014_120000_add_goods_snapshot_to_order_detail extends Migration
{
    private const TABLE = '{{%order_detail}}';
    private const BATCH = 2000;

    public function safeUp()
    {
        $columns = [
            'goods_name'     => $this->string(255)->notNull()->defaultValue('')->comment('商品名称快照(下单时)'),
            'goods_pic'      => $this->string(512)->notNull()->defaultValue('')->comment('商品图片快照(规格图优先)'),
            'goods_no'       => $this->string(255)->notNull()->defaultValue('')->comment('商品编码快照(SKU编码优先)'),
            'original_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('原售价快照(会员折扣/活动价之前的单价)'),
            'cost_price'     => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('成本价快照(规格成本优先)'),
        ];
        $schema = $this->db->getTableSchema(self::TABLE, true);
        foreach ($columns as $name => $type) {
            if ($schema->getColumn($name) === null) {
                $this->addColumn(self::TABLE, $name, $type);
                echo "    > order_detail.{$name} added\n";
            }
        }
        $this->db->getTableSchema(self::TABLE, true);

        $this->backfill();
        return true;
    }

    /**
     * 老订单补齐:按 id 分段批量 UPDATE,线上数据多也不会一条语句锁全表
     */
    private function backfill(): void
    {
        $od   = $this->db->schema->getRawTableName(self::TABLE);
        $goods = $this->db->schema->getRawTableName('{{%goods}}');
        $attr = $this->db->schema->getRawTableName('{{%attr}}');

        $range = $this->db->createCommand("SELECT MIN(id) AS lo, MAX(id) AS hi FROM `{$od}`")->queryOne();
        if (empty($range['hi'])) {
            echo "    > 订单明细为空,不用补齐\n";
            return;
        }

        $total = 0;
        for ($start = (int) $range['lo']; $start <= (int) $range['hi']; $start += self::BATCH) {
            $end = $start + self::BATCH - 1;
            $n = $this->db->createCommand("
                UPDATE `{$od}` od
                LEFT JOIN `{$goods}` g ON g.id = od.goods_id
                LEFT JOIN `{$attr}` a ON a.id = od.attr_id AND od.attr_id > 0
                SET od.goods_name     = COALESCE(g.name, ''),
                    od.goods_pic      = COALESCE(NULLIF(a.cover_pic, ''), g.cover_pic, ''),
                    od.goods_no       = COALESCE(NULLIF(a.no, ''), g.no, ''),
                    od.original_price = ROUND(od.price + IFNULL(od.member_discount_price, 0) / GREATEST(od.num, 1), 2),
                    od.cost_price     = CASE WHEN a.id IS NOT NULL AND a.cost_price IS NOT NULL THEN a.cost_price
                                             ELSE IFNULL(g.cost_price, 0) END
                WHERE od.id BETWEEN :s AND :e AND od.goods_name = ''
            ", [':s' => $start, ':e' => $end])->execute();
            $total += $n;
        }
        $left = (int) $this->db->createCommand("SELECT COUNT(*) FROM `{$od}` WHERE goods_name = ''")->queryScalar();
        echo "    > 老订单补齐 {$total} 行" . ($left ? ",{$left} 行商品已不存在补不上(读取时回退商品表)" : '') . "\n";
    }

    public function safeDown()
    {
        $schema = $this->db->getTableSchema(self::TABLE, true);
        foreach (['goods_name', 'goods_pic', 'goods_no', 'original_price', 'cost_price'] as $name) {
            if ($schema->getColumn($name) !== null) {
                $this->dropColumn(self::TABLE, $name);
            }
        }
        return true;
    }
}
