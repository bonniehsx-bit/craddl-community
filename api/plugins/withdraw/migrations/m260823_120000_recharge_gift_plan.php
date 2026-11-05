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
 * 充值分期赠送。
 *
 * 原来一个档位只能一次性送一笔 gift_amount。现在支持:
 *   · 送券(可多张) / 送赠金,可混搭
 *   · 分多期,每期一个到账时间
 *   · 时间锚点是**相对充值日**(充值日 +N 天/周/月),
 *     即天猫那种「09.21 到账 / 10.21 到账」—— "满一个月给你",不同人不会有体验差异
 *
 * ── 为什么是两张表 ──
 * 配置表(plan_gift)是运营改的;订单表(order_gift)是下单那一刻的**快照**。
 * 不快照的话,运营改了分期方案,已经充过值的用户的赠品会跟着变 —— 那是事故。
 * 快照里连"计划发放时间"都算死,所以发放逻辑只是照表发,不用再解释规则。
 *
 * ── 为什么规则不落库,只落展开后的明细 ──
 * 后台按「按月 × 3 期 × 每期 1 张」生成,落库的是 3 行。好处:
 *   1. 发放逻辑简单到不可能写错
 *   2. 运营能把第 1 期单独改成 2 张(纯规则式表达不了"首期多送")
 *   3. 规则改了不会影响已生成的订单
 */
class m260823_120000_recharge_gift_plan extends Migration
{
    public function safeUp()
    {
        // ── 档位的分期赠品配置(展开后的明细,一期一行) ──
        $this->createTable('{{%plugin_withdraw_recharge_plan_gift}}', [
            'id'          => $this->primaryKey(),
            'mall_id'     => $this->integer()->notNull()->comment('商城ID'),
            'plan_id'     => $this->integer()->notNull()->comment('所属充值档位'),
            'period'      => $this->integer()->notNull()->defaultValue(1)->comment('第几期,从 1 起'),
            'gift_type'   => $this->string(16)->notNull()->defaultValue('coupon')->comment('赠品类型: coupon=优惠券 asset=赠金'),
            'coupon_id'   => $this->integer()->notNull()->defaultValue(0)->comment('券模板ID,gift_type=coupon 时有效'),
            'num'         => $this->integer()->notNull()->defaultValue(1)->comment('张数,gift_type=coupon 时有效'),
            'asset_type'  => $this->string(32)->null()->comment('钱包key,gift_type=asset 时有效。留空则取所属商品的资产类型'),
            'amount'      => $this->decimal(10, 2)->notNull()->defaultValue(0)->comment('赠金金额,gift_type=asset 时有效'),
            'delay_mode'  => $this->string(16)->notNull()->defaultValue('immediate')
                ->comment('到账时机: immediate=充值立返 day=N天后 week=N周后 month=N月后。一律相对充值日'),
            'delay_value' => $this->integer()->notNull()->defaultValue(0)->comment('延迟数量,配合 delay_mode。immediate 时忽略'),
            'sort'        => $this->integer()->notNull()->defaultValue(0)->comment('排序'),
        ]);
        $this->createIndex('idx_rc_plan_gift_plan', '{{%plugin_withdraw_recharge_plan_gift}}', ['plan_id', 'is_delete']);

        // ── 订单的分期发放计划(快照 + 状态机) ──
        $this->createTable('{{%plugin_withdraw_recharge_order_gift}}', [
            'id'                => $this->primaryKey(),
            'mall_id'           => $this->integer()->notNull()->comment('商城ID'),
            'recharge_order_id' => $this->integer()->notNull()->comment('所属充值单'),
            'user_id'           => $this->integer()->notNull()->comment('收赠人'),
            'period'            => $this->integer()->notNull()->defaultValue(1)->comment('第几期'),
            // ↓ 全部是下单那一刻的快照,配置改了不影响已充值用户
            'gift_type'         => $this->string(16)->notNull()->defaultValue('coupon')->comment('赠品类型(快照)'),
            'coupon_id'         => $this->integer()->notNull()->defaultValue(0)->comment('券模板ID(快照)'),
            'coupon_name'       => $this->string(128)->null()->comment('券名称(快照),给 C 端展示,券被删了也还看得到'),
            'num'               => $this->integer()->notNull()->defaultValue(1)->comment('张数(快照)'),
            'asset_type'        => $this->string(32)->null()->comment('钱包key(快照)'),
            'amount'            => $this->decimal(10, 2)->notNull()->defaultValue(0)->comment('赠金金额(快照)'),
            'plan_grant_at'     => $this->integer()->notNull()->defaultValue(0)
                ->comment('计划发放时间戳。下单时就按充值日算死,不在发放时再解释规则'),
            // ⚠ 这一位就是发放的幂等锁,发放前必须 UPDATE ... WHERE status=0 抢占
            'status'            => $this->tinyInteger(1)->notNull()->defaultValue(0)
                ->comment('0=待发放 1=已发放 2=发放失败(可重试) 3=已作废(充值退款)'),
            'granted_at'        => $this->integer()->notNull()->defaultValue(0)->comment('实际发放时间'),
            'attempts'          => $this->integer()->notNull()->defaultValue(0)->comment('发放尝试次数,用于止损'),
            'fail_reason'       => $this->string(255)->null()->comment('最后一次失败原因,给运营看'),
            'user_coupon_ids'   => $this->string(255)->null()->comment('实际发出的 user_coupon ID,逗号分隔。退款收回时按它作废'),
            'revoked_at'        => $this->integer()->notNull()->defaultValue(0)->comment('退款收回时间'),
        ]);
        // 定时任务的主查询:按 状态 + 计划时间 捞
        $this->createIndex('idx_rc_order_gift_due', '{{%plugin_withdraw_recharge_order_gift}}', ['status', 'plan_grant_at']);
        $this->createIndex('idx_rc_order_gift_order', '{{%plugin_withdraw_recharge_order_gift}}', ['recharge_order_id']);
        $this->createIndex('idx_rc_order_gift_user', '{{%plugin_withdraw_recharge_order_gift}}', ['user_id', 'status']);
    }

    public function safeDown()
    {
        $this->dropTable('{{%plugin_withdraw_recharge_order_gift}}');
        $this->dropTable('{{%plugin_withdraw_recharge_plan_gift}}');
    }
}
