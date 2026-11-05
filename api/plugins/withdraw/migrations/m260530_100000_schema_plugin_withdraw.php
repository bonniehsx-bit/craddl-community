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
 * 提现插件 —— 建表
 *
 * 设计要点（与老 Cash 链路完全脱钩，独立内聚）：
 *  - plugin_withdraw_channel：提现渠道（配置）。一条渠道 = 方式 + 网关(1:1) + 资料 + 费规 + 适用对象。
 *      · fund_class    存完整网关类名供反射（遵守"禁 code 映射"铁律）
 *      · user_types    适用用户类型 json，解"谁能用"——区分 商户 / 普通用户
 *      · onboarding_override  资料 schema 覆盖 json，解"资料配网关"
 *  - plugin_withdraw_order：提现单据（记录），自管状态机 + 幂等单号。
 *
 * 自包含迁移：建表前先 dropTable IF EXISTS，保证可重复执行、干净。
 */
class m260530_100000_schema_plugin_withdraw extends Migration
{
    public function safeUp()
    {
        // 幂等：先删后建（自包含）
        $this->db->createCommand('DROP TABLE IF EXISTS {{%plugin_withdraw_order}}')->execute();
        $this->db->createCommand('DROP TABLE IF EXISTS {{%plugin_withdraw_channel}}')->execute();

        // ───────────────── 提现渠道（配置） ─────────────────
        $this->createTable('{{%plugin_withdraw_channel}}', [
            'id'                => $this->primaryKey(),
            'mall_id'           => $this->integer()->notNull()->comment('商城ID'),
            'name'              => $this->string(100)->notNull()->comment('渠道名称，如：提现到银行卡'),
            'icon'              => $this->string(255)->null()->comment('图标'),
            'subtitle'          => $this->string(255)->null()->comment('副标题/说明'),
            'color'             => $this->string(20)->null()->comment('主题色'),

            // ── 网关绑定（1:1，反射用完整类名）──
            'fund_class'        => $this->string(150)->notNull()->comment('打款网关完整类名(反射)，如 app\\funds\\BankFund'),
            'fund_config'       => $this->text()->null()->comment('对网关参数的覆盖(json)'),

            // ── 适用范围（解痛点1：谁能用 / 提哪个钱包）──
            'user_types'        => $this->string(100)->null()->comment('适用用户类型json，如 ["user","merchant"]'),
            'asset_types'       => $this->string(100)->null()->comment('适用资金来源json，如 ["balance","price","integral"]'),

            // ── 资料（解痛点2：资料 schema 可覆盖网关默认）──
            'onboarding_override' => $this->text()->null()->comment('资料字段schema覆盖(json)，空=用网关 getOnboardingFields 默认'),

            // ── 费规 / 限额 ──
            'fee_rate'          => $this->decimal(8, 4)->notNull()->defaultValue(0)->comment('手续费率'),
            'tax_rate'          => $this->decimal(8, 4)->notNull()->defaultValue(0)->comment('税率'),
            'min_amount'        => $this->decimal(10, 2)->notNull()->defaultValue(0)->comment('单笔最小提现额'),
            'max_amount'        => $this->decimal(10, 2)->notNull()->defaultValue(0)->comment('单笔上限，0=不限'),
            'multiple'          => $this->decimal(10, 2)->notNull()->defaultValue(0)->comment('提现倍数，0=不限'),
            'day_limit_amount'  => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('单日累计限额，0=不限'),
            'day_limit_count'   => $this->integer()->notNull()->defaultValue(0)->comment('单日次数限制，0=不限'),
            'arrival_desc'      => $this->string(100)->null()->comment('到账时效文案，如 T+1工作日'),

            // ── 流程 / 状态 ──
            'need_audit'        => $this->tinyInteger(1)->notNull()->defaultValue(1)->comment('是否需人工审核:0否1是'),
            'sort'              => $this->integer()->notNull()->defaultValue(0)->comment('排序，越小越前'),
            'status'            => $this->tinyInteger(1)->notNull()->defaultValue(1)->comment('状态:0停用1启用'),
            'remark'            => $this->string(255)->null()->comment('备注'),
        ], null, '提现渠道配置表');

        // ───────────────── 提现单据（记录） ─────────────────
        $this->createTable('{{%plugin_withdraw_order}}', [
            'id'             => $this->primaryKey(),
            'mall_id'        => $this->integer()->notNull()->comment('商城ID'),
            'user_id'        => $this->integer()->notNull()->comment('申请用户ID'),
            'order_no'       => $this->string(64)->notNull()->comment('提现单号(幂等唯一)'),

            // ── 渠道快照（防配置变更影响历史单）──
            'channel_id'     => $this->integer()->notNull()->comment('提现渠道ID'),
            'channel_name'   => $this->string(100)->null()->comment('渠道名称快照'),
            'fund_class'     => $this->string(150)->notNull()->comment('网关类名快照(反射打款)'),
            'user_type'      => $this->string(20)->null()->comment('用户类型快照:user/merchant'),
            'asset_type'     => $this->string(20)->null()->comment('资金来源:balance/price/integral'),

            // ── 金额（bc 定点）──
            'amount'         => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('申请金额'),
            'fee'            => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('手续费'),
            'tax'            => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('税费'),
            'actual_amount'  => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('实际到账'),
            'fee_rate'       => $this->decimal(8, 4)->notNull()->defaultValue(0)->comment('费率快照'),
            'tax_rate'       => $this->decimal(8, 4)->notNull()->defaultValue(0)->comment('税率快照'),

            // ── 账户/网关信息 ──
            'payout_info'    => $this->text()->null()->comment('账户资料快照(json，前端按label/value渲染)'),
            'snapshot'       => $this->text()->null()->comment('渠道配置快照(json)'),
            'payout_no'      => $this->string(100)->null()->comment('网关返回的打款单号'),

            // ── 状态机 ──
            'status'         => $this->smallInteger()->notNull()->defaultValue(0)->comment('0待审核 1待打款 2打款中 3已到账 4已驳回 5打款失败 6已退回'),
            'audit_admin_id' => $this->integer()->notNull()->defaultValue(0)->comment('审核人ID'),
            'audit_at'       => $this->integer()->notNull()->defaultValue(0)->comment('审核时间'),
            'audit_remark'   => $this->string(255)->null()->comment('审核备注'),
            'payout_at'      => $this->integer()->notNull()->defaultValue(0)->comment('打款时间'),
            'finish_at'      => $this->integer()->notNull()->defaultValue(0)->comment('完成时间'),
            'fail_reason'    => $this->string(255)->null()->comment('失败原因'),
            'user_remark'    => $this->string(255)->null()->comment('用户备注'),
        ], null, '提现单据表');

        // 幂等唯一索引
        $this->createIndex('uniq-plugin_withdraw_order-order_no', '{{%plugin_withdraw_order}}', ['order_no'], true);
        $this->createIndex('idx-plugin_withdraw_order-channel_id', '{{%plugin_withdraw_order}}', ['channel_id'], false);
        $this->createIndex('idx-plugin_withdraw_order-status', '{{%plugin_withdraw_order}}', ['status'], false);
    }

    public function safeDown()
    {
        $this->db->createCommand('DROP TABLE IF EXISTS {{%plugin_withdraw_order}}')->execute();
        $this->db->createCommand('DROP TABLE IF EXISTS {{%plugin_withdraw_channel}}')->execute();
    }
}
