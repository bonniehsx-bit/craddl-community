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

namespace app\plugins\withdraw\listeners;

use app\events\GoodsEvent;
use app\listeners\BaseListener;
use app\plugins\withdraw\models\RechargeGoods;
use app\plugins\withdraw\models\RechargePlan;
use app\plugins\withdraw\services\RechargeGiftService;
use app\plugins\withdraw\services\AssetService;
use Yii;

/**
 * 充值商品的配置挂载 —— 让「充值」成为一个正经商品。
 *
 * ── 为什么走这条路 ──────────────────────────────────────────────────
 * 商品编辑走的是核心 `/mall/goods/edit`,它在保存管线末尾触发 `GOODS_AFTER_SAVE`。
 * 挂上来之后:
 *   · 充值商品是**运营可见可编辑**的正经商品(在插件自己的商品列表页);
 *   · 编辑它时「分销设置」tab 会自然出现 —— 想让充值参与分润就勾上,不勾就不参与,
 *     完全由分润插件按商品自行决定,不需要在充值这边做任何排除。
 * 原来那个 `RechargeGoodsHelper` 用 `save(false)` 偷建的隐形占位商品,
 * 既进不了商品列表、也从不触发本事件,所以永远配不了分润。
 *
 * ── 为什么**不带** component_name ──────────────────────────────────
 * `app-base-goods-panel` 只对带 component_name 的插件数据做动态组件渲染,
 * 那是给「要出现在**所有**商品编辑页」的插件用的(如分销、区域代理)。
 * 充值档位只属于充值商品自己,所以走 `GoodsEditDrawer` 的 `#extra-tabs` 插槽,
 * 由插件自己的页面渲染 —— **核心组件一行都不用改**。
 */
class RechargeGoodsListener extends BaseListener
{
    /**
     * 充值商品的 Goods.type。核心 GoodsForm 原样接受任意 type,无需在核心注册。
     *
     * ⛔ 这个值**必须等于** WithdrawRechargeOrderHandler::getOrderType()。
     *   下单管线里 handler 分两处解析、依据不同:
     *     · mainHandler(preview / fillOrderData) → 按 form->type
     *     · 逐商品(checkStockAndPermission / saveOrderDetail) → 按 **$goods->type**
     *   两者不一致时,明细阶段会落到 NormalOrderHandler,`RechargeService::createForOrder`
     *   根本不执行 → 充值单不存在 → **付款成功但永远不到账**,而订单看起来一切正常。
     *   (plaza 的在线购电就是栽在这上面,见 plugins/plaza/CLAUDE.md §9.21。)
     *   `withdraw-classcheck/order-routing` 有断言锁死这条不变量。
     */
    public const GOODS_TYPE = 'withdraw_recharge';

    /** 插件数据在 pluginData / plugins_data 里的键 */
    public const KEY = 'withdraw_recharge';

    public function register()
    {
        Yii::$app->on(GoodsEvent::GOODS_EDIT_FORM_LOAD, [$this, 'editLoad']);
        Yii::$app->on(GoodsEvent::GOODS_AFTER_SAVE, [$this, 'save']);
    }

    /**
     * 编辑页加载:把「到账钱包 + 档位列表 + 可选钱包」下发给前端插槽。
     */
    public function editLoad(GoodsEvent $event)
    {
        $goods  = $event->goods;
        $mallId = (int)($goods ? $goods->mall_id : (Yii::$app->mall->id ?? 0));

        // 只管充值商品。新建时 $goods 可能为 null / type 还没定,
        // 由前端传 goods-type 决定,这里对非充值商品直接不注入。
        if ($goods && $goods->type !== self::GOODS_TYPE) {
            return;
        }

        try {
            $goodsId = $goods ? (int)$goods->id : 0;

            $cfg = $goodsId > 0 ? RechargeGoods::ofGoods($goodsId, $mallId) : null;
            $plans = [];
            if ($goodsId > 0) {
                $rows = RechargePlan::find()
                    ->where(['goods_id' => $goodsId, 'mall_id' => $mallId, 'is_delete' => 0])
                    ->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])
                    ->all();
                foreach ($rows as $r) {
                    $plans[] = [
                        'id'          => (int)$r->id,
                        'title'       => (string)$r->title,
                        'pay_amount'  => (float)$r->pay_amount,
                        'gain_amount' => (float)$r->gain_amount,
                        'gift_amount' => (float)$r->gift_amount,
                        'user_types'  => array_values(array_filter(explode(',', (string)$r->user_types))),
                        'day_limit'   => (int)$r->day_limit,
                        'total_limit' => (int)$r->total_limit,
                        'sort'        => (int)$r->sort,
                        'status'      => (int)$r->status,
                        // 分期赠品明细。落库的就是展开后的一期一行,规则只活在后台配置界面
                        'gifts'       => RechargeGiftService::configOf((int)$r->id),
                    ];
                }
            }

            $event->pluginData[] = [
                'key'   => self::KEY,
                'label' => '充值设置',
                // ⛔ 故意不带 component_name,见类注释
                'data'  => [
                    'asset_type'   => $cfg ? (string)$cfg->asset_type : 'balance',
                    'remark'       => $cfg ? (string)$cfg->remark : '',
                    'plans'        => $plans,
                    // 下拉框选项:核心 3 资产 + 已授权插件声明的钱包(开放注册表)
                    'assetOptions' => AssetService::assetOptions($mallId),
                ],
            ];
        } catch (\Throwable $e) {
            Yii::error('[充值] 商品配置加载失败: ' . $e->getMessage());
        }
    }

    /**
     * 商品保存后:落到账钱包 + 全量重写档位。
     *
     * ⛔ 档位用「软删旧的 + 按 id 复用/新增」而不是物理删重建 ——
     *   已售出的充值单按 plan_id 追溯档位名称,物理删会让历史记录变成孤儿。
     */
    public function save(GoodsEvent $event)
    {
        $goods = $event->goods;
        if (!$goods || $goods->type !== self::GOODS_TYPE) {
            return;   // 跨类型拦截:只管自己的商品
        }

        $data = $this->pickData($event->pluginData);
        if ($data === null) {
            // 充值商品必须带配置,否则会建出一个没有档位、C 端点不动的死商品
            throw new \Exception('充值设置缺失,请填写到账账户与充值档位');
        }

        $mallId  = (int)$goods->mall_id;
        $goodsId = (int)$goods->id;

        // ── 1. 到账钱包 ──────────────────────────────────────────
        $assetType = trim((string)($data['asset_type'] ?? 'balance'));
        if (!AssetService::isValid($assetType)) {
            throw new \Exception('到账账户不可用:' . $assetType);
        }

        $cfg = RechargeGoods::ofGoods($goodsId, $mallId);
        if (!$cfg) {
            $cfg = new RechargeGoods();
            $cfg->mall_id  = $mallId;
            $cfg->goods_id = $goodsId;
        }
        $cfg->asset_type = $assetType;
        $cfg->remark     = mb_substr((string)($data['remark'] ?? ''), 0, 255);
        if (!$cfg->save()) {
            throw new \Exception('充值商品配置保存失败:' . $cfg->getErrorMsg());
        }

        // ── 2. 档位 ─────────────────────────────────────────────
        $plans = is_array($data['plans'] ?? null) ? $data['plans'] : [];
        $keepIds = [];

        foreach ($plans as $item) {
            if (!is_array($item) || trim((string)($item['title'] ?? '')) === '') {
                continue;   // 空行丢弃
            }
            $pay = round((float)($item['pay_amount'] ?? 0), 2);
            if ($pay <= 0) {
                throw new \Exception('档位「' . $item['title'] . '」的实付金额必须大于 0');
            }

            $id = (int)($item['id'] ?? 0);
            $plan = $id > 0
                ? RechargePlan::findOne(['id' => $id, 'goods_id' => $goodsId, 'mall_id' => $mallId, 'is_delete' => 0])
                : null;
            if (!$plan) {
                $plan = new RechargePlan();
                $plan->mall_id  = $mallId;
                $plan->goods_id = $goodsId;
            }

            $types = $item['user_types'] ?? [];
            if (is_array($types)) {
                $types = implode(',', array_filter(array_map('trim', $types)));
            }

            $plan->title       = mb_substr((string)$item['title'], 0, 64);
            $plan->pay_amount  = $pay;
            $plan->gain_amount = round((float)($item['gain_amount'] ?? 0), 2);
            $plan->gift_amount = round((float)($item['gift_amount'] ?? 0), 2);
            $plan->user_types  = trim((string)$types) ?: 'user';
            $plan->day_limit   = (int)($item['day_limit'] ?? 0);
            $plan->total_limit = (int)($item['total_limit'] ?? 0);
            $plan->sort        = (int)($item['sort'] ?? 0);
            $plan->status      = (int)($item['status'] ?? RechargePlan::STATUS_ON);

            if (!$plan->save()) {
                throw new \Exception('档位「' . $plan->title . '」保存失败:' . $plan->getErrorMsg());
            }
            // 分期赠品跟着档位一起存。校验(券是否存在/有效期类型)也在里面,
            // 不通过就抛,整个商品保存回滚 —— 半个配置比没配置更危险
            RechargeGiftService::saveConfig(
                $mallId, (int)$plan->id, is_array($item['gifts'] ?? null) ? $item['gifts'] : [], (string)$plan->title
            );
            $keepIds[] = (int)$plan->id;
        }

        if (!$plans) {
            throw new \Exception('至少配置一个充值档位,否则 C 端点不动');
        }

        // 本次没提交的档位 → 软删(不物理删,已售出的充值单还按 plan_id 追溯档位名)
        $stale = RechargePlan::find()
            ->where(['goods_id' => $goodsId, 'mall_id' => $mallId, 'is_delete' => 0])
            ->andFilterWhere(['not in', 'id', $keepIds])
            ->all();
        foreach ($stale as $s) {
            $s->is_delete = 1;
            $s->save(false);
        }
    }

    /**
     * 从 pluginData 里挑出本插件的数据。
     * 核心下发的结构有两种形态(键值 / 列表),两种都兼容 —— 与 voucher 的做法一致。
     */
    private function pickData($pluginData): ?array
    {
        if (!is_array($pluginData)) {
            return null;
        }
        if (isset($pluginData[self::KEY]) && is_array($pluginData[self::KEY])) {
            return $pluginData[self::KEY];
        }
        foreach ($pluginData as $item) {
            if (is_array($item) && ($item['key'] ?? '') === self::KEY) {
                return is_array($item['data'] ?? null) ? $item['data'] : $item;
            }
        }
        return null;
    }
}
