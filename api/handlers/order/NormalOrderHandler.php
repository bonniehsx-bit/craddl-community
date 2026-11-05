<?php

/**
 * Copyright (c) 2026 佛山今日鼎盛信息科技有限公司
 * @author Refactored Version (Pipeline Pattern)
 * @date 2026-02-15
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

namespace app\handlers\order;

use app\services\goods\StandaloneGoodsGuard;
use app\core\Pipeline;
use app\events\MessageEvent as AppMessageEvent;
use app\helpers\MessageHelper;
use app\helpers\UserHelper;
use app\models\Attr;
use app\models\Cart;
use app\models\Goods;
use app\models\Order;
use app\plugins\mch\models\Mch;
use app\services\order\PickupAddressResolver;
use app\services\order\pipeline\OrderPayload;
use app\services\order\pipeline\PreviewPayload;
use app\services\order\pipeline\stages\InitBasicDataStage;
use app\services\order\pipeline\stages\preview\ApplyDiscountStage;
use app\services\order\pipeline\stages\preview\CalculateBasePriceStage;
use app\services\order\pipeline\stages\preview\FormatPreviewResponseStage;
use app\services\order\pipeline\stages\preview\InitContextStage;
use app\services\order\pipeline\stages\ProcessDeliveryStage;
use app\services\order\pipeline\stages\ProcessPriceStage;
use Yii;

/**
 * 普通订单处理器 (管道模式最终版)
 * * 重构改进：
 * 1. 彻底移除原有的面条式算价与数据填充逻辑。
 * 2. preview 和 fillOrderData 全面接入管道模式 (Pipeline)。
 * 3. 完美兼容原有的库存扣减、商户入账剥洋葱算法等。
 */
class NormalOrderHandler extends BaseOrderHandler
{
    /**
     * =========================================================================
     * 0. 参数校验规则
     * =========================================================================
     */
    protected function getValidationRules()
    {
        return [
            [['goods_list'], 'required', 'message' => '商品列表不能为空'],
            [['mch_id', 'address_id', 'user_coupon_id', 'is_use_integral', 'is_immediately'], 'integer'],
            [['mch_id'], 'default', 'value' => 0],
            [['deliver_type'], 'in', 'range' => ['express', 'pickup', 'city']],
            [['deliver_type'], 'default', 'value' => 'express'],
            [['mobile'], 'match', 'pattern' => '/^1[3-9]\d{9}$/', 'message' => '手机号格式错误'],
            [['name', 'remarks', 'delivery_time'], 'string'],
            [['locked_random_minus_price'], 'number'],
        ];
    }

    // 注:自提必填「提货人 + 手机号」的校验【不能】放在 afterValidate()。
    // OrderService::preview() 也会调 handler->validateParams() → afterValidate(),
    // 而预览时用户尚未填写提货人,一切到「到店自提」tab 就会抛异常、前端弹「无法下单」。
    // 该校验已下沉到 fillOrderData()(仅提交阶段执行)。

    /**
     * 专属商品(插件声明 standalone)不能走普通下单。
     * 放在 afterValidate:预览(OrderService::preview)和同步提交校验都会经过这里,
     * 用户在结算页就能看到原因,而不是等到异步建单才失败。
     */
    protected function afterValidate(array $data)
    {
        $ids = array_map(fn($g) => (int)($g['goods_id'] ?? 0), (array)($data['goods_list'] ?? []));
        StandaloneGoodsGuard::assertGenericPurchasable(array_filter($ids));
    }

    /**
     * =========================================================================
     * 1. 订单预览 (Preview Phase) - 管道模式
     * =========================================================================
     */
    public function preview(array $data)
    {
        $payload = new PreviewPayload($data, UserHelper::getUser());

        return (new Pipeline())
            ->send($payload)
            ->through([
                InitContextStage::class,             // 1. 初始化上下文
                CalculateBasePriceStage::class,      // 2. 计算基础价格/运费
                ApplyDiscountStage::class,           // 3. 应用营销优惠
                FormatPreviewResponseStage::class,   // 4. 格式化输出数据
            ])
            ->then(function (PreviewPayload $payload) {
                // 分享直达结算只清理本次购买数量，不能整行删掉原有的同规格购物车余量。
                if (($payload->requestData['cart_source'] ?? '') === 'goods-share') {
                    foreach ($payload->responseData['goods_list'] as &$item) {
                        $item['_goods_share_checkout'] = true;
                    }
                    unset($item);
                }
                return $payload->responseData;
            });
    }

    /**
     * =========================================================================
     * 2. 填充主订单数据 (Submit Phase) - 管道模式
     * =========================================================================
     */
    public function fillOrderData(Order $order, array $data, array $previewData)
    {
        // 自提必须有提货人和手机号。只在提交阶段校验 —— 预览时用户还没填,不能拦(见上方注释)。
        // 例外:平台自提且商城配置为「用户选收货地址」时,提货人直接取该地址的收货人,前端不再单独填,故不校验。
        if (($data['deliver_type'] ?? '') === Order::DELIVER_TYPE_PICKUP) {
            $isPlatformPickup = empty($data['mch_id']);
            $useUserAddress = $isPlatformPickup
                && PickupAddressResolver::type((int)Yii::$app->mall->id) === PickupAddressResolver::TYPE_USER_ADDRESS;

            if (!$useUserAddress && (empty($data['name']) || empty($data['mobile']))) {
                throw new \Exception('自提订单必须填写提货人和手机号');
            }
        }

        $payload = new OrderPayload($order, $data, $previewData);

        return (new Pipeline())
            ->send($payload)
            ->through([
                InitBasicDataStage::class,     // 1. 基础状态与关系绑定
                ProcessDeliveryStage::class,   // 2. 物流配送处理
                ProcessPriceStage::class,      // 3. 价格与各项优惠字段填充
            ])
            ->then(function (OrderPayload $payload) {
                // 平台自营:默认只允许快递;开了「支持自提」开关(mall_setting.support_pickup)才允许 pickup;其余(city 等)仍拦。
                // 商家单(mch_id>0)沿用商家自己的 delivery_options,不受此限制。
                if (!$payload->order->mch_id) {
                    $dt = $payload->order->deliver_type;
                    $s = \app\helpers\OptionHelper::get('mall_setting', $payload->order->mall_id) ?: [];
                    $supportPickup = (int)($s['support_pickup'] ?? 0) === 1;
                    $ok = ($dt == 'express') || ($dt == Order::DELIVER_TYPE_PICKUP && $supportPickup);
                    if (!$ok) {
                        throw new \Exception('平台自营商品暂不支持该配送方式');
                    }
                }
                return $payload->order;
            });
    }

    /**
     * =========================================================================
     * 3. 校验库存与权限
     * =========================================================================
     */
    public function checkStockAndPermission(Goods $goods, array $itemData, $lockedAttrMap, array $data)
    {
        // 双保险:建单阶段再拦一次专属商品(afterValidate 拦的是入口,这里拦的是真正落单)
        StandaloneGoodsGuard::assertGenericPurchasable([$goods]);

        $itemName = $goods->name;

        // 检查规格库存
        if (!empty($itemData['goods_attr_id'])) {
            /** @var Attr $attr */
            $attr = $lockedAttrMap[$itemData['goods_attr_id']] ?? null;
            if (!$attr) {
                throw new \Exception("商品规格数据异常: {$itemName}");
            }

            if ($attr->stock < $itemData['num']) {
                throw new \Exception("商品 {$itemName} (规格)库存不足");
            }
        } else {
            // 检查商品主库存
            if ($goods->stock < $itemData['num']) {
                throw new \Exception("商品 {$itemName} 库存不足");
            }
        }
    }

    /**
     * =========================================================================
     * 4. 保存订单详情 - 完全匹配 OrderDetail 模型
     * =========================================================================
     */
    public function saveOrderDetail(Order $order, Goods $goods, array $itemData, array $data)
    {
        $detail = $this->createBaseOrderDetail($order, $goods, $itemData);

        // ===== 积分相关 =====
        // 从 IntegralCalculator 分摊后的 item 上读取，退款时按 detail 返还
        $detail->use_integral = (int) ($itemData['use_integral'] ?? 0);

        // ===== 优惠券关联 =====
        if ($detail->coupon_cut_price > 0 && $order->user_coupon_id > 0) {
            $detail->user_coupon_id = $order->user_coupon_id;
        }

        // ===== 调用父类的商户收益计算方法（剥洋葱算法） =====
        // 注意:calculateMchIncome 内才定稿 $detail->pay_price(实付:已扣所有优惠+支付随机减,且为整行总额),
        // 故"按实付比例赠送积分"必须放在它之后算。
        $this->calculateMchIncome($detail, $goods);

        // ===== 赠送积分(快照,放在 pay_price 定稿之后)=====
        // goods.send_integral_type: 0=固定积分(每件 × 数量)  1=按实付金额比例(pay_price × %)
        if ($goods->send_integral > 0) {
            $detail->is_send_integral = 1;
            if ((int)$goods->send_integral_type === 1) {
                // 比例:该明细实付金额 × 比例%(pay_price 已含数量与所有优惠,【不再乘 num】);向下取整,不超送
                $detail->send_integral = (int)bcdiv(bcmul((string)$detail->pay_price, (string)$goods->send_integral, 2), '100', 0);
            } else {
                // 固定:每件赠送积分 × 数量(现状口径不变)
                $detail->send_integral = bcmul((string)$goods->send_integral, (string)$itemData['num'], 2);
            }
        } else {
            $detail->is_send_integral = 0;
            $detail->send_integral = 0;
        }
        // 赠送时机快照(0=结算后赠送 1=支付即赠送):与金额同理冻结下单时配置,发放/追回都以快照为准
        $detail->send_integral_timing = (int)$goods->send_integral_timing;

        // ===== 保存详情 =====
        if (!$detail->save()) {
            throw new \Exception("详情保存失败: " . json_encode($detail->errors));
        }

        return $detail;
    }

    /**
     * =========================================================================
     * 5. 扣减库存
     * =========================================================================
     */
    public function deductStock(Goods $goods, $attr, $num, $itemData)
    {
        // 调用父类的通用库存扣减逻辑（乐观锁）
        parent::deductStock($goods, $attr, $num, $itemData);

        // 清理购物车中的该商品
        if (Yii::$app->user->id) {
            if (!empty($itemData['_goods_share_checkout'])) {
                \app\services\cart\SharedCartConsumption::consume(
                    (int) Yii::$app->mall->id, (int) Yii::$app->user->id,
                    (int) $goods->id, (int) ($itemData['goods_attr_id'] ?? 0), (int) $num
                );
                return;
            }
            Cart::deleteAll([
                'user_id' => Yii::$app->user->id,
                'goods_id' => $goods->id,
                'goods_attr_id' => (int)($itemData['goods_attr_id'] ?? 0)
            ]);
        }
    }

    /**
     * =========================================================================
     * 6. 生成规格快照
     * =========================================================================
     */
    public function makeSnapshot(array $itemData)
    {
        return $itemData['specs_data'] ?? [['group' => '规格', 'value' => '默认']];
    }

    /**
     * =========================================================================
     * 7. 获取订单类型
     * =========================================================================
     */
    public function getOrderType()
    {
        return Order::TYPE_NORMAL;
    }

    /**
     * =========================================================================
     * 8. 获取插件标识
     * =========================================================================
     */
    public function getPlugin()
    {
        return 'mall';
    }

    /**
     * =========================================================================
     * 9. 订单支付成功后的处理
     * =========================================================================
     */
    public function orderPaid(Order $order)
    {
        // 调用父类方法（更新销量、虚拟销量）
        parent::orderPaid($order);

        // 发送通知
        $this->sendNotifications($order);
    }

    /**
     * 发送订单通知
     */
    private function sendNotifications(Order $order)
    {
        // 1. 通知买家：订单支付成功
        MessageHelper::sendToUser(
            $order->user_id,
            '订单支付成功',
            "您的订单（尾号" . substr($order->order_no, -4) . "）已支付成功，支付金额￥{$order->pay_price}。商家将尽快为您安排发货。",
            AppMessageEvent::TYPE_ORDER_PAY_SUCCESS
        );

        // 2. 通知商户：有新的待发货订单
        if ($order->mch_id > 0) {
            $mch = Mch::findOne($order->mch_id);
            if ($mch && $mch->user_id) {
                $customerNickname = $order->user ? $order->user->nickname : '未知用户';

                MessageHelper::sendToUser(
                    $mch->user_id,
                    "【{$mch->mch_name}】您有新的待发货订单",
                    "顾客{$customerNickname}下单成功，订单号：{$order->order_no}，实付金额￥{$order->pay_price}，请及时备货处理。",
                    AppMessageEvent::TYPE_ORDER_NEW_CREATED
                );
            }
        } else {
            // 3. 通知平台管理员：平台有新的待发货订单
            MessageHelper::sendSystemNotification(
                '平台有新的待发货订单',
                "平台有新的待发货订单，订单号：{$order->order_no}，请及时处理。",
                AppMessageEvent::TYPE_ORDER_NEW_CREATED
            );
        }
    }

    // getMobileActions 直接继承 BaseOrderHandler 的默认实现（通用实体/虚拟订单按钮集合）。
    // 如需定制状态文案或按钮，在此重写即可。

    /**
     * =========================================================================
     * 10. 检查取消订单权限
     * =========================================================================
     */
    public function checkCancelPermission(Order $order, $force = false)
    {
        // 强制取消（管理员操作）
        if ($force) {
            return true;
        }

        // 未支付的订单可以取消
        if (!$order->isPaid()) {
            return true;
        }

        // 「禁止取消/退款」(mall_setting.is_ban_cancel) 只约束【用户端】申请退款/售后,
        // 已下沉到 C 端入口 RefundForm(preview/save)。后台关闭订单 / 系统流程走本方法,
        // 不受该开关限制——此处曾以 force=false 误拦后台关单,报"已付款订单不允许取消"。

        // 原来这里还拦「订单含不可退款商品就不许取消」。用户端只能取消未付款的单,走不到这里;
        // 实际拦住的只有后台 / 商户后台关闭已付款订单 —— 报「请联系客服处理」,可后台就是客服,成了死路
        // (缺货发不了货也没法整单退)。关单是商家主动的决定,不再拦;「不可退款」只约束顾客申请售后
        // (OrderDetail::canRefund)。

        return true;
    }
}
