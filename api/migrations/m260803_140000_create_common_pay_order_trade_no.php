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
 * 支付商户单号历史表 —— 修「重复发起支付导致回调对不上 = 掉单」。
 *
 * ## 病灶
 * 商户单号(out_trade_no)每次发起支付都会重算并**覆盖**同一行:
 *   PayForm::payData()          $order->out_trade_no = $baseOrderNo . '_' . time();
 *   CommonPayOrder::buildGatewayOrderData()  同上(MpLaunchPayForm 走这条)
 * 重算本身是刻意的 —— 换支付方式/超时重来时,多数网关不接受重复的商户单号。
 *
 * 但回调只认「当前那一个」:
 *   CommonPayOrder::findOne(['out_trade_no' => $no]) ?: findOne(['order_no' => $no])
 * 用户如果在**旧的**预支付单上付了钱(点了付、取消、隔一秒再点,旧的收银台在
 * H5/公众号里往往还能拉起来),回调带回旧单号:
 *   · 匹配 out_trade_no 失败 —— 库里已被覆盖成新号
 *   · 兜底 order_no 也失败 —— 旧号形如 order_no + '_' + 时间戳,永远不等于 order_no
 * → 找不到订单,钱扣了单还挂着。而且 OrderPayCheckTask 拿的是**覆盖后**的新号去
 *   问网关,网关答"没付过"(用户付的是旧号),所以补单也救不回来,只能人工对账。
 *
 * 注:time() 是秒级的,所以同一秒内的连点反而无害(生成的号一样);
 *     真正危险的是间隔超过 1 秒的重复发起,而那恰恰更常见。
 *
 * ## 治法
 * 不动生成逻辑(设计意图原样保留,零网关兼容性风险),改为把**每一个**发出去过的
 * 商户单号都记下来,回调走 CommonPayOrder::findByTradeNo() 三级匹配:
 *   当前 out_trade_no → 本表历史 → order_no(改造前的老单)
 * 于是旧单号的回调也能落到正确的订单上。
 *
 * ## 幂等
 * out_trade_no 上是唯一索引,重复记录靠 INSERT IGNORE 吞掉;
 * 存量回填用 NOT EXISTS 去重,重复执行不会产生二次数据。
 */
class m260803_140000_create_common_pay_order_trade_no extends Migration
{
    public function safeUp()
    {
        $tableOptions = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $this->createTable('{{%common_pay_order_trade_no}}', [
            'id'            => $this->primaryKey(),
            'mall_id'       => $this->integer()->defaultValue(0)->comment('商城'),
            'pay_order_id'  => $this->integer()->notNull()->comment('common_pay_order.id'),
            'order_no'      => $this->string(64)->defaultValue('')->comment('系统单号,冗余便于人工对账'),
            'out_trade_no'  => $this->string(64)->notNull()->comment('发给网关的商户单号'),
            'payment'       => $this->string(120)->defaultValue('')->comment('本次发起所用的支付类名'),
        ], $tableOptions);

        // 回调按商户单号反查,必须唯一 —— 同一个号不可能属于两个订单
        $this->createIndex('uk_cpo_trade_no', '{{%common_pay_order_trade_no}}', 'out_trade_no', true);
        $this->createIndex('idx_cpo_trade_pay_order', '{{%common_pay_order_trade_no}}', 'pay_order_id');

        // 主表 common_pay_order.out_trade_no 的索引已由 m260215_175731 建过(idx_out_trade_no),
        // 此处不再重复建。

        $this->backfill();
    }

    public function safeDown()
    {
        $this->dropTableIfExists('{{%common_pay_order_trade_no}}');
    }

    /**
     * 把存量订单**当前**的 out_trade_no 灌进历史表。
     *
     * 只能回填当前这一个 —— 历史上被覆盖掉的号已经无从得知(这正是本次要根治的)。
     * 意义在于:改造后仍在途的未支付单,其正在使用的号从此有据可查。
     *
     * 去重靠 out_trade_no 上的唯一索引 + INSERT IGNORE,**不做跨表字符串比较**:
     * 本表按项目规范建成 utf8mb4_unicode_ci,而线上 common_pay_order 可能是
     * MySQL 8 默认的 utf8mb4_0900_ai_ci,`t.x = o.x` 会直接抛
     * "Illegal mix of collations"。
     */
    private function backfill(): void
    {
        $rows = (int)$this->db->createCommand("
            INSERT IGNORE INTO {{%common_pay_order_trade_no}}
                (mall_id, pay_order_id, order_no, out_trade_no, payment, is_delete, created_at, updated_at)
            SELECT o.mall_id, o.id, o.order_no, o.out_trade_no, COALESCE(o.payment, ''), 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
            FROM {{%common_pay_order}} o
            WHERE o.out_trade_no IS NOT NULL
              AND o.out_trade_no <> ''
              AND o.is_delete = 0
        ")->execute();

        echo "    > 回填存量商户单号 {$rows} 条\n";
    }
}
