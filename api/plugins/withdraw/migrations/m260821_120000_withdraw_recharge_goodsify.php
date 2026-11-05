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
 * 充值商品化 —— 把「插件私有档位 + 隐形占位商品」改成「一个正经商品 + 它的档位」。
 *
 * ============================================================
 * 为什么要改
 * ============================================================
 * 原来的占位商品是 `RechargeGoodsHelper` 用 `new Goods(); $g->save(false)` 建的:
 *   ① 后台商品列表只列 type=normal,这个商品**运营根本看不见**,
 *      于是永远配不了分销 —— 「将来想让充值也分润」这条路是堵死的;
 *   ② save(false) 不走商品表单,`GoodsEvent::GOODS_AFTER_SAVE` 从没触发过,
 *      所有分润插件的 GoodsListener 都没见过它,自然没有配置行。
 *
 * 改成真商品后,运营在插件的商品管理页编辑它时,「分销设置」tab 会自然出现,
 * 勾一下就能让充值参与分润;不勾就不参与 —— 完全由分润插件按商品自行决定。
 *
 * ============================================================
 * 为什么档位不用核心 SKU(jxmall_attr)
 * ============================================================
 * 试过,走不通,两个硬伤:
 *   ① `SkuGenerator.vue` 的表格列是写死的(价格/成本价/采购价/库存/重量/编码/预览图),
 *      所有商品共用、没有插槽。为充值加一列「赠送额」会污染所有商品的规格表。
 *   ② 更致命:`generateSkus()` 新生成的规格行**连 id 字段都没有**,
 *      新建商品时规格只是前端内存里的组合,没有 attr_id —— 插件面板无从挂靠赠送额。
 *
 * 所以照 voucher 的 `plugin_voucher_bundle_item` 范式:档位是**插件自己的子表**,
 * 在商品编辑抽屉的 `#extra-tabs` 插槽里渲染,完全不碰核心规格组件。
 */
class m260821_120000_withdraw_recharge_goodsify extends Migration
{
    public function safeUp()
    {
        // ── 充值商品的插件级配置(一个商品 = 一个到账钱包)──────────────
        $this->createTable('{{%plugin_withdraw_recharge_goods}}', [
            'id'         => $this->primaryKey(),
            'mall_id'    => $this->integer()->notNull()->comment('商城ID'),
            'goods_id'   => $this->integer()->notNull()->comment('关联主商品ID'),
            'asset_type' => $this->string(32)->notNull()->defaultValue('balance')
                ->comment('到账资产: balance余额 / price佣金 / integral积分 / 任意扩展钱包 key。'
                    . '⛔ 开放注册表,不要写死枚举(见 AssetService::isValid)'),
            'remark'     => $this->string(255)->null()->comment('充值说明,展示在 C 端'),
        ]);
        // 一个商品只能对应一份配置 —— 想充两种资产就建两个商品(各自还能配不同分销比例)
        $this->createIndex('uk_wrg_goods', '{{%plugin_withdraw_recharge_goods}}', ['goods_id'], true);

        // ── 档位挂到商品下 ────────────────────────────────────────────
        $t = '{{%plugin_withdraw_recharge_plan}}';
        $this->addColumn($t, 'goods_id', $this->integer()->notNull()->defaultValue(0)
            ->comment('所属充值商品ID。0 = 旧数据(商品化之前的档位),不再下发'));
        $this->createIndex('idx_wrp_goods', $t, ['goods_id', 'status', 'sort']);

        // asset_type 上移到商品级:一个商品一个钱包,档位不再各自选钱包。
        // 留着这列不删 —— 万一将来要做「同一商品多钱包」还能捡回来,删了就得重建。
        // 读取一律以 recharge_goods.asset_type 为准,本列作废。
        $this->alterColumn($t, 'asset_type', $this->string(32)->notNull()->defaultValue('balance')
            ->comment('⚠ 已作废:到账资产改由 plugin_withdraw_recharge_goods.asset_type 决定'));
    }

    public function safeDown()
    {
        $this->dropTable('{{%plugin_withdraw_recharge_goods}}');
        $this->dropIndex('idx_wrp_goods', '{{%plugin_withdraw_recharge_plan}}');
        $this->dropColumn('{{%plugin_withdraw_recharge_plan}}', 'goods_id');
    }
}
