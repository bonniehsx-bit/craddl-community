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

namespace app\plugins\withdraw\handlers;

use app\core\Pipeline;
use app\handlers\order\BaseOrderHandler;
use app\helpers\UserHelper;
use app\models\Goods;
use app\models\Order;
use app\models\OrderDetail;
use app\plugins\withdraw\listeners\RechargeGoodsListener;
use app\plugins\withdraw\models\RechargeGoods;
use app\plugins\withdraw\models\RechargeOrder;
use app\plugins\withdraw\models\RechargePlan;
use app\plugins\withdraw\services\RechargeService;
use app\services\order\pipeline\OrderPayload;
use app\services\order\pipeline\PreviewPayload;
use app\services\order\pipeline\stages\InitBasicDataStage;
use app\services\order\pipeline\stages\preview\CalculateBasePriceStage;
use app\services\order\pipeline\stages\preview\InitContextStage;
use app\plugins\withdraw\pipeline\stages\FormatRechargePreviewStage;

/**
 * 账户充值订单处理器（虚拟商品 · 动态定价）。
 *
 * 商品是运营在后台建的**正经充值商品**(Goods.type='recharge'),不是代码偷建的占位商品——
 * 所以它在插件的商品管理页可见可编辑,编辑时「分销设置」tab 会自然出现:
 * **要不要给充值分润,由分润插件按商品自行决定**,这边不做任何排除。
 *
 * 金额在 fillOrderData 按**档位**动态覆盖(与 plaza 账单缴费同范式),
 * 不扣库存、不发货、付款即完成。
 *
 * ⛔ 金额以**档位配置为准**，不接受前端传值 —— 前端传金额等于让用户自己定价。
 *   前端只传 plan_id。
 *
 * ⛔ 支付成功后**不在这里发钱**。发放在 RechargeSettledListener 里，
 *   因为"钱到账"和"额度进钱包"是两件可能分开失败的事，
 *   而且核心的补结算链路也会重新触发结算事件，发放必须能被重复调用而只生效一次。
 */
class WithdrawRechargeOrderHandler extends BaseOrderHandler
{
    public function getOrderType()
    {
        return 'withdraw_recharge';
    }

    public function getPlugin()
    {
        return 'withdraw';
    }

    protected function getValidationRules()
    {
        return [
            [['goods_list'], 'required', 'message' => '缺少充值商品'],
            [['plan_id'], 'required', 'message' => '请选择充值档位'],
            [['plan_id'], 'integer'],
            [['deliver_type'], 'default', 'value' => Order::DELIVER_TYPE_NONE],
        ];
    }

    public function preview(array $data)
    {
        $payload = new PreviewPayload($data, UserHelper::getUser());
        return (new Pipeline())
            ->send($payload)
            ->through([
                InitContextStage::class,
                CalculateBasePriceStage::class,
                // ⛔ 不用核心 FormatPreviewResponseStage：它强依赖 ApplyDiscountStage 赋的
                //    discountResult(不挂就读 null 属性直接 500)，而充值又不能挂优惠管线
                //    (券打到充值单上=套利)。详见 FormatRechargePreviewStage 的注释。
                FormatRechargePreviewStage::class,
            ])
            ->then(function (PreviewPayload $payload) {
                return $payload->responseData;
            });
    }

    public function fillOrderData(Order $order, array $data, array $previewData)
    {
        $payload = new OrderPayload($order, $data, $previewData);
        return (new Pipeline())
            ->send($payload)
            ->through([InitBasicDataStage::class])
            ->then(function (OrderPayload $payload) use ($data) {
                $order = $payload->order;
                $plan  = $this->resolvePlan($order, $data);
                $amount = round((float)$plan->pay_amount, 2);

                $order->plugin       = 'withdraw';
                $order->deliver_type = Order::DELIVER_TYPE_NONE;
                $order->send_type    = Order::SEND_TYPE_NONE;
                // 让核心在 EVENT_ORDER_PAID 所有 listener 跑完后统一结算，
                // 避免与 Distribution/Area/Dealer 等"同一事件写 pending 记录"的插件竞态
                $order->is_auto_complete = 1;

                $order->goods_total_price = $amount;
                $order->total_price       = $amount;
                $order->pay_price         = $amount;
                $order->express_price     = 0;

                // 存订单通用扩展位 extra(系统用)，不污染 remarks(买家备注)
                // 到账资产看**商品**配置(一个商品一个钱包),档位表没有这个字段
                $cfg = RechargeGoods::ofGoods((int)$plan->goods_id, (int)$order->mall_id);
                $order->extra = json_encode([
                    'recharge_goods_id' => (int)$plan->goods_id,
                    'recharge_plan_id'  => (int)$plan->id,
                    'asset_type'        => $cfg ? (string)$cfg->asset_type : '',
                    'gain'              => $plan->totalGain(),
                ], JSON_UNESCAPED_UNICODE);

                return $order;
            });
    }

    public function checkStockAndPermission(Goods $goods, array $itemData, $lockedAttrMap, array $data)
    {
        // 商品类型必须对得上 —— 否则可以拿任意普通商品配一个充值档位 ID 下单,
        // 按档位价收钱、再按档位到账,等于用别的商品的价格买余额。
        if ($goods->type !== RechargeGoodsListener::GOODS_TYPE) {
            throw new \Exception('该商品不支持充值下单');
        }
        // 档位资格在这里就拦（下单前），别等到订单都建好了才报错
        $this->resolvePlan(null, $data, (int)$goods->id);
    }

    public function saveOrderDetail(Order $order, Goods $goods, array $itemData, array $data)
    {
        $plan   = $this->resolvePlan($order, $data, (int)$goods->id);
        $amount = round((float)$plan->pay_amount, 2);

        $itemData['price']       = $amount;
        $itemData['total_price'] = $amount;

        $detail = $this->createBaseOrderDetail($order, $goods, $itemData);
        // 充值到账后额度已进钱包、用户可能已经花掉，不走通用退款流程；
        // 真要退只能由运营在后台单独处理（扣回钱包 + 手工退款）。
        $detail->is_can_refund  = 0;
        $detail->refund_status  = OrderDetail::REFUND_STATUS_NO_REFUND;
        $this->calculateMchIncome($detail, $goods);
        if (!$detail->save()) {
            throw new \Exception('充值订单明细保存失败：' . implode('; ', $detail->getFirstErrors()));
        }

        // 明细落成后才建充值单：此时 order.id 已经有了
        RechargeService::createForOrder($order, $plan);

        return $detail;
    }

    public function deductStock(Goods $goods, $attr, $num, $data = []) {}

    public function returnStock(Goods $goods, $attr, $num, $data = []) {}

    public function makeSnapshot(array $itemData)
    {
        return [['group' => '类型', 'value' => '账户充值']];
    }

    public function isAutoComplete(Goods $goods)
    {
        return true;
    }

    public function getActionConfig(Order $order)
    {
        return [
            'show_ship' => false, 'show_confirm' => false,
            'show_force_settle' => false, 'show_force_verify' => false,
        ];
    }

    public function getFrontendDisplayPanels(Order $order)
    {
        $ro = self::readRechargeOrder($order);
        $items = [['label' => '订单类型', 'value' => '账户充值(虚拟)']];
        if ($ro) {
            $items[] = ['label' => '充值档位', 'value' => (string)$ro->plan_title];
            $items[] = ['label' => '到账账户', 'value' => $ro->assetLabel()];
            $items[] = ['label' => '到账数量', 'value' => $ro->totalGain()];
            $items[] = ['label' => '状态', 'value' => $ro->statusText()];
        }
        return $this->formatDisplayPanels('充值信息', $items);
    }

    /** 从订单反查充值单 */
    public static function readRechargeOrder($order): ?RechargeOrder
    {
        if (!$order || !$order->id) {
            return null;
        }
        return RechargeOrder::findOne(['order_id' => (int)$order->id, 'is_delete' => 0]);
    }

    // ── 内部 ──────────────────────────────────────────────

    /** @var RechargePlan|null 单次请求内复用，避免管线里多个钩子重复查库 */
    private $planCache = null;

    private function resolvePlan($order, array $data, int $goodsId = 0): RechargePlan
    {
        if ($this->planCache !== null) {
            return $this->planCache;
        }
        $mallId = (int)(\Yii::$app->mall->id ?? 0);
        $userId = (int)($order ? $order->user_id : (\Yii::$app->user->id ?? 0));

        // goodsId 为 0 时(fillOrderData 阶段拿不到 goods)从 goods_list 兜一个,
        // 保证「档位必须属于所下单的商品」这条校验在每个钩子里都成立。
        if ($goodsId <= 0) {
            $goodsId = (int)($data['goods_list'][0]['goods_id'] ?? 0);
        }

        return $this->planCache = RechargeService::resolvePlan(
            (int)($data['plan_id'] ?? 0),
            $mallId,
            $userId,
            $goodsId
        );
    }
}
