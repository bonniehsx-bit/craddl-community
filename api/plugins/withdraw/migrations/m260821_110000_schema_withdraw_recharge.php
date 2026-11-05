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
 * 提现中心 —— 补上「充值」这条反向链路。
 *
 * ============================================================
 * 为什么充值放在 withdraw 插件里
 * ============================================================
 * 提现和充值是同一件事的两个方向,资金侧的基建 withdraw 已经全建好了:
 * 钱包中心页、通用钱包注册表(WalletRegistry)、资产桥接(AssetService)、
 * 渠道化配置范式、资产字段映射的坑。另起一个插件等于把这些再造一遍。
 *
 * 全库此前**没有**通用的「用真钱充值余额/积分」能力 —— 只有 plaza 的预付费购电
 * (业务专用)。各业务插件想引导充值,只能在自己后台填一个跳转链接,跳到哪全靠运营。
 *
 * ============================================================
 * 两张表的分工
 * ============================================================
 * · plan  = 运营配置的档位(充多少、到账多少、进哪个钱包、谁能买)
 * · order = 每一次充值的实例,一条对应一个核心订单
 *
 * 为什么 order 表不能省(只靠核心 order + extra):
 *   ① 到账要幂等 —— 支付回调/补结算/人工重跑都会再次触发发放,
 *      没有一个「按 order_id 唯一」的状态位就会重复发钱(资损);
 *   ② 档位随时会被运营改价改比例,已售出的必须按**下单时快照**发放;
 *   ③ C 端充值记录、后台充值报表都要按用户/时间/钱包聚合,
 *      从 order.extra 里 JSON 抠字段做统计是不可维护的。
 */
class m260821_110000_schema_withdraw_recharge extends Migration
{
    public function safeUp()
    {
        // ── 充值档位 ──────────────────────────────────────────────
        $this->createTable('{{%plugin_withdraw_recharge_plan}}', [
            'id'          => $this->primaryKey(),
            'mall_id'     => $this->integer()->notNull()->comment('商城ID'),
            'title'       => $this->string(64)->notNull()->comment('档位名称,如「充100送10」'),
            'asset_type'  => $this->string(32)->notNull()->defaultValue('balance')
                ->comment('到账资产: balance余额 / price佣金 / integral积分 / 任意扩展钱包 key。'
                    . '⛔ 开放注册表,不要在代码里写死枚举(见 AssetService)'),
            'pay_amount'  => $this->decimal(10, 2)->notNull()->defaultValue(0)->comment('实付金额(元)'),
            'gain_amount' => $this->decimal(10, 2)->notNull()->defaultValue(0)->comment('基础到账数量'),
            'gift_amount' => $this->decimal(10, 2)->notNull()->defaultValue(0)->comment('额外赠送数量,0=不送'),
            'user_types'  => $this->string(255)->notNull()->defaultValue('user')
                ->comment('允许购买的用户类型,逗号分隔: user普通用户 / merchant商户。与提现渠道同口径'),
            'day_limit'   => $this->integer()->notNull()->defaultValue(0)->comment('每人每天限购次数,0=不限'),
            'sort'        => $this->integer()->notNull()->defaultValue(0)->comment('排序,越小越前'),
            'status'      => $this->tinyInteger(1)->notNull()->defaultValue(1)->comment('1=上架 0=下架'),
            'remark'      => $this->string(255)->null()->comment('档位说明,展示在 C 端'),
        ]);
        $this->createIndex('idx_wr_plan_mall', '{{%plugin_withdraw_recharge_plan}}', ['mall_id', 'status', 'sort']);

        // ── 充值单 ────────────────────────────────────────────────
        $this->createTable('{{%plugin_withdraw_recharge_order}}', [
            'id'          => $this->primaryKey(),
            'mall_id'     => $this->integer()->notNull()->comment('商城ID'),
            'user_id'     => $this->integer()->notNull()->comment('充值人'),
            'plan_id'     => $this->integer()->notNull()->defaultValue(0)->comment('档位ID(档位可能已被删,仅作追溯)'),
            'order_id'    => $this->integer()->notNull()->defaultValue(0)->comment('核心订单ID'),
            'order_no'    => $this->string(64)->null()->comment('核心订单号,便于人工对账'),
            // ↓ 下单那一刻的快照。运营改价不影响已售出的,退款/对账也只认这份
            'asset_type'  => $this->string(32)->notNull()->defaultValue('balance')->comment('到账资产(快照)'),
            'pay_amount'  => $this->decimal(10, 2)->notNull()->defaultValue(0)->comment('实付金额(快照)'),
            'gain_amount' => $this->decimal(10, 2)->notNull()->defaultValue(0)->comment('基础到账(快照)'),
            'gift_amount' => $this->decimal(10, 2)->notNull()->defaultValue(0)->comment('赠送(快照)'),
            'plan_title'  => $this->string(64)->null()->comment('档位名称(快照)'),
            'status'      => $this->tinyInteger(1)->notNull()->defaultValue(0)
                ->comment('0=待支付 1=已到账。⚠ 这一位就是发放的幂等锁,'
                    . '发放前必须 UPDATE ... WHERE status=0 抢占,抢不到说明已发过'),
            'credited_at' => $this->integer()->notNull()->defaultValue(0)->comment('到账时间戳'),
        ]);
        // 一个核心订单只能对应一条充值单 —— 这条唯一索引是"重复发放"的最后一道物理防线
        $this->createIndex('uk_wr_order', '{{%plugin_withdraw_recharge_order}}', ['order_id'], true);
        $this->createIndex('idx_wr_order_user', '{{%plugin_withdraw_recharge_order}}', ['mall_id', 'user_id', 'status']);
    }

    public function safeDown()
    {
        $this->dropTable('{{%plugin_withdraw_recharge_order}}');
        $this->dropTable('{{%plugin_withdraw_recharge_plan}}');
    }
}
