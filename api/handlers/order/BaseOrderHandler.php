<?php

/**
 * Copyright (c) 2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: Refactored Version
 * Date: 2026-02-14
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

use app\helpers\LockHelper;
use app\helpers\OptionHelper;
use app\models\Attr;
use app\models\Goods;
use app\models\Order;
use app\models\OrderDetail;
use app\models\UserCoupon;
use app\plugins\mch\models\Mch;
use yii\base\DynamicModel;
use yii\helpers\Json;

/**
 * 订单处理器抽象基类 (Core Layer) - 重构版
 */
abstract class BaseOrderHandler
{
    /**
     * ============================================================================
     * 第一部分：参数校验体系
     * ============================================================================
     */
    public function validateParams(array $data)
    {
        \app\services\order\OrderInputGuard::assertItems($data['goods_list'] ?? null);
        $rules = $this->getValidationRules();
        $model = new DynamicModel($data);
        foreach ($rules as $rule) {
            $model->addRule($rule[0], $rule[1], array_slice($rule, 2));
        }
        if (!$model->validate()) {
            $errors = $model->getFirstErrors();
            \Yii::error("Yuda OrderSubmit Validation Failed: " . json_encode($errors, JSON_UNESCAPED_UNICODE), 'order.submit');
            throw new \Exception(reset($errors));
        }
        $this->afterValidate($data);
    }

    abstract protected function getValidationRules();

    protected function afterValidate(array $data)
    {
    }

    /**
     * ============================================================================
     * 第二部分：核心抽象方法 (业务逻辑)
     * ============================================================================
     */
    abstract public function preview(array $data);

    abstract public function checkStockAndPermission(Goods $goods, array $itemData, $lockedAttrMap, array $data);

    abstract public function fillOrderData(Order $order, array $data, array $previewData);

    abstract public function saveOrderDetail(Order $order, Goods $goods, array $itemData, array $data);


    abstract public function makeSnapshot(array $itemData);

    abstract public function getOrderType();

    abstract public function getPlugin();

    /**
     * 格式化前端展示面板 (视图辅助方法)
     *
     * @param string $title 面板标题
     * @param array $items 面板内容项 [['label' => '...', 'value' => '...']]
     * @return array
     */
    protected function formatDisplayPanels($title, array $items)
    {
        return [
            [
                'title' => $title,
                'items' => $items,
            ]
        ];
    }

    /**
     * 获取订单生命周期在通用后台（如PC总控订单列表/详情页）的操作控制配置
     * 返回结构中，键为前端通用的权限锁（如 show_ship, show_confirm 等），值为是否允许显示
     *
     * @param Order $order
     * @return array
     */
    public function getActionConfig(Order $order)
    {
        return [
            'show_ship' => true,
            'show_confirm' => true,
            'show_force_settle' => true,
            'show_force_verify' => true,
            'show_mch_force_verify' => true, // 初始化商户专属的特权核销开关，默认放行
            'custom_tip' => '', // 额外的警告或提示文字
        ];
    }

    /**
     * 【PC 后台用】订单详情展示面板专属数据 [Handler-Delegation 模式]
     *
     * 此方法专供管理后台（`forms/mall/order/OrderForm`）的订单详情页使用，
     * 格式固定为"标题 + label/value 列表"：
     *
     *   [
     *     ['title' => '酒店预订信息',
     *      'items' => [['label'=>'预订人', 'value'=>'张三'], ...]]
     *   ]
     *
     * ⚠ 不要把这个方法改造成 Panel-Driven 组件结构 ——
     *   后台 PC 订单详情依赖扁平 label/value 结构渲染表格。
     *   小程序前端走 {@see getMobileDetailPanels()}。
     *
     * @param Order $order
     * @return array
     */
    public function getFrontendDisplayPanels(Order $order)
    {
        return [];
    }

    // ═════════════════════════════════════════════════════════════════════
    // 小程序前端（uniapp）专属接口 —— 与后台 PC 的 getFrontendDisplayPanels
    // / getActionConfig 完全解耦，互不影响，避免两端渲染需求耦合。
    // ═════════════════════════════════════════════════════════════════════

    /**
     * 【小程序前端用】订单详情"动态面板"数据（Panel-Driven）
     *
     * 小程序详情页（通用 Shell）消费此返回值，按 component 名动态装载组件。
     *
     *   [
     *     [
     *       'component' => 'HotelStayPanel',   // 前端 panelRegistry 映射
     *       'priority'  => 10,                 // 渲染顺序；数字越小越靠上，默认 50
     *       'data'      => [                   // 自由结构，交给前端组件消费
     *         'room_name' => '...', 'check_in' => '...', 'nights' => 3,
     *       ],
     *     ],
     *     ['component' => 'VerifyCodePanel', 'data' => [...]]
     *   ]
     *
     * 默认返回空数组，通用订单无特殊面板。
     *
     * @param Order $order
     * @return array
     */
    public function getMobileDetailPanels(Order $order)
    {
        return [];
    }

    /**
     * 【小程序前端用】订单详情页动作配置（底部栏按钮 / 状态文案）
     *
     * 由后端决定前端渲染什么，替代各 OrderDetailXxx.vue 重复实现 shouldShowXxx。
     *
     *   [
     *     'status_text' => '待发货',
     *     'status_desc' => '商家正在打包...',
     *     'status_icon' => 'check-circle',
     *     'actions' => [
     *       ['type'=>'cancel','label'=>'取消订单','style'=>'plain','visible'=>true],
     *       ['type'=>'pay','label'=>'立即支付','style'=>'primary','visible'=>true,
     *        'data_key'=>'order'],
     *     ],
     *   ]
     *
     * 【默认实现】按 status + deliver_type 给出普通实体/虚拟订单的通用按钮集合，
     * 覆盖：取消/支付/查物流/确认收货/评价/再次购买/申请退款（单件订单）。
     * 特殊订单（Hotel/Group/Voucher/Lucky/Yuda...）如需定制按钮或文案，
     * 在子类重写此方法即可。
     *
     * @param Order $order
     * @return array|null 返回 null 表示前端走老版 fallback
     */
    public function getMobileActions(Order $order)
    {
        $status       = (int)$order->status;
        $deliverType  = $order->deliver_type;
        $refundStatus = (int)($order->refund_status ?? 0);

        // ── 状态文案 ──
        $statusMap = [
            0  => ['text' => '交易关闭', 'desc' => '订单已取消或关闭'],
            10 => ['text' => '待付款',   'desc' => sprintf('需付款 ¥%s，超时将自动关闭', $order->pay_price)],
            20 => ['text' => '待发货',   'desc' => '商家正在打包中，如需退款请点击商品申请售后'],
            30 => ['text' => '待收货',   'desc' => '商品运输中，请留意查收'],
            40 => ['text' => '交易完成', 'desc' => '交易已完成，期待再次光临'],
        ];
        $statusInfo = $statusMap[$status] ?? ['text' => '未知状态', 'desc' => ''];

        if ($status === 20) {
            if ($deliverType === 'pickup') {
                $statusInfo = ['text' => '待自提', 'desc' => '商家已接单，请等待备货完成后前往门店核销'];
            } elseif ($deliverType === 'city') {
                $statusInfo = ['text' => '待配送', 'desc' => '等待骑手接单，如需退款请点击商品申请售后'];
            }
        }
        if ($status === 30 && $deliverType === 'pickup') {
            $statusInfo = ['text' => '待自提', 'desc' => '备货完成，请尽快前往门店核销'];
        }
        if ($refundStatus > 0 && $refundStatus < 30) {
            $statusInfo = ['text' => '售后处理中', 'desc' => '您的售后申请正在处理中'];
        }
        // 退过款的关单(整单售后退完会自动关单、后台关单退款)说清楚退了多少,别笼统写「已取消或关闭」
        if ($status === 0 && (float)$order->refund_price > 0) {
            $statusInfo['desc'] = (float)$order->refund_price >= (float)$order->pay_price
                ? sprintf('商品已全部退款 ¥%s，订单已关闭', $order->refund_price)
                : sprintf('已退款 ¥%s，订单已关闭', $order->refund_price);
        }

        // ── 单件商品快捷退款条件 ──
        $canQuickRefund = false;
        if ($status === 20 && $refundStatus === 0) {
            $orderDetails = $order->orderDetails ?: [];
            if (count($orderDetails) === 1) {
                $only = $orderDetails[0];
                if ((int)($only->refund_status ?? 0) === 0) {
                    $canQuickRefund = true;
                }
            }
        }

        $canComment = ($status === 40 && (int)($order->comment_status ?? 0) === 0);

        // 「禁止取消/退款」(mall_setting.is_ban_cancel):只约束用户端,开启时隐藏「申请退款」按钮(后台关单不受限)
        $refundBanned = (int)(OptionHelper::get('mall_setting', $order->mall_id, [])['is_ban_cancel'] ?? 0) === 1;

        $actions = [
            ['type' => 'cancel',      'label' => '取消订单', 'style' => 'plain',   'visible' => $status === 10, 'data_key' => 'order'],
            ['type' => 'pay',         'label' => '去支付',   'style' => 'primary', 'visible' => $status === 10, 'data_key' => 'order'],
            ['type' => 'applyRefund', 'label' => '申请退款', 'style' => 'plain',   'visible' => $canQuickRefund && !$refundBanned, 'data_key' => 'list[0]'],
            ['type' => 'express',     'label' => '查看物流', 'style' => 'plain',
                // 后台选「无需物流」发货(send_type=2)不生成物流单,点进去什么都查不到,不给这个按钮
                'visible' => ($status === 30 || $status === 40) && $deliverType === 'express' && (int)$order->send_type !== 2,
                'data_key' => 'null'],
            ['type' => 'confirm',     'label' => '确认收货', 'style' => 'primary',
                'visible' => $status === 30 && $deliverType !== 'pickup', 'data_key' => 'order'],
            ['type' => 'comment',     'label' => '去评价',   'style' => 'plain', 'visible' => $canComment, 'data_key' => 'order'],
            // 待发货、待自提也给「再次购买」:这两种状态右侧原本常常一个按钮都没有,底栏只剩客服。
            // 待收货(快递/同城)已有查看物流/确认收货,不再加,免得主按钮被夹在中间
            ['type' => 'rebuy',       'label' => '再次购买', 'style' => 'plain',
                'visible' => in_array($status, [0, 20, 40], true) || ($status === 30 && $deliverType === 'pickup'),
                'data_key' => 'order'],
        ];

        return [
            'status_text' => $statusInfo['text'],
            'status_desc' => $statusInfo['desc'],
            'actions'     => array_values(array_filter($actions, fn($a) => $a['visible'])),
        ];
    }

    /**
     * 【小程序前端用】整页接管（逃生舱口）
     *
     * 默认所有订单类型都走通用 Shell（L1 + L2 Panel-Driven），无需使用此钩子。
     *
     * 仅在 UI 完全无法套用标准结构时才需要整页接管：
     *   - 返回一个组件名字符串
     *   - 同时需要在 `uniapp/others/order-detail/order-detail.vue` 的 template
     *     里显式增加 `v-if="overrideTemplate === 'XxxName'"` 分支
     *     （微信小程序 WXML 不支持 `<component :is>` 动态组件）
     *
     * 目前没有订单类型使用整页接管。
     *
     * @param Order $order
     * @return string|null
     */
    public function getMobileOverrideTemplate(Order $order)
    {
        return null;
    }

    /**
     * 统一创建订单详情基座数据 (工厂/骨架方法)
     * 负责将通用的关联ID、价格要素、各项优惠分摊默认值、规格快照统一打包。
     * 子类只需挂载特有属性并保存，极大减轻子类“样板代码”的体积。
     *
     * @param Order $order 主订单模型
     * @param Goods $goods 商品模型
     * @param array $itemData 携带价格与参数项的商品行数据
     * @return OrderDetail
     */
    protected function createBaseOrderDetail(Order $order, Goods $goods, array $itemData)
    {
        $detail = new OrderDetail();

        // 1. 基础关系映射
        $detail->mall_id = $order->mall_id;
        $detail->user_id = $order->user_id;
        $detail->order_id = $order->id;
        $detail->order_no = $order->order_no;
        $detail->goods_id = $goods->id;
        $detail->num = $itemData['num'] ?? 1;

        // 2. 价格骨架
        $detail->price = $itemData['price'] ?? 0;
        $detail->total_price = $itemData['total_price'] ?? 0;

        // 3. 通用优惠字段 (兜底 0.00，防止数据库严格模式报错)
        $detail->member_discount_price = $itemData['total_member_discount_price'] ?? '0.00';
        $detail->coupon_cut_price = $itemData['coupon_cut_price'] ?? '0.00';
        $detail->first_order_discount_price = $itemData['first_order_discount_price'] ?? '0.00';
        $detail->integral_cut_price = $itemData['integral_cut_price'] ?? '0.00';
        $detail->pickup_discount_price = $itemData['pickup_discount_price'] ?? '0.00';
        // platform_subsidy_cut_price 字段已废弃，统一写 0；随机减走 platform_subsidy_random_minus
        $detail->platform_subsidy_cut_price = '0.00';
        $detail->platform_subsidy_random_minus = $itemData['platform_subsidy_random_minus'] ?? '0.00';

        // 4. 规格快照绑定
        $detail->attr_id = $itemData['goods_attr_id'] ?? 0;
        $detail->attr = Json::encode($this->makeSnapshot($itemData), JSON_UNESCAPED_UNICODE);

        // 5. 状态默认值
        $detail->status = OrderDetail::STATUS_NORMAL;
        $detail->refund_status = OrderDetail::REFUND_STATUS_NO_REFUND;
        $detail->is_send = 0;
        $detail->express_log_id = 0;
        $detail->is_mch_settled = 0;
        $detail->is_can_refund = (int)($goods->is_refund ?? 1); // 默认读取主商品退款支持状态

        return $detail;
    }

    public function isAutoComplete(Goods $goods)
    {
        return $goods->is_auto_complete == 1;
    }

    public function checkCancelPermission(Order $order, $force = false)
    {
        return true;
    }


    public function orderPaid(Order $order)
    {
        // 幂等：同一订单销量只累加一次（防回调重试导致虚增）
        if (!LockHelper::lock('order_paid_sales_' . $order->id, 3600)) {
            \Yii::warning("[orderPaid] 订单 #{$order->id} 销量已累加过，跳过");
            return;
        }

        $orderDetails = OrderDetail::findAll(['order_id' => $order->id, 'is_delete' => 0]);
        foreach ($orderDetails as $detail) {
            Goods::updateAllCounters(['sales' => $detail->num, 'virtual_sales' => $detail->num], ['id' => $detail->goods_id]);
        }
    }

    /**
     * 通用库存扣减逻辑：扣 Goods 主库存 + 扣 Attr 规格库存
     * [核心修复]：移除危险的手动补偿逻辑，完全依赖外层 (OrderCreator (Actions)) 的全局事务回滚。
     */
    public function deductStock(Goods $goods, $attr, $num, $itemData)
    {
        \app\services\order\OrderInputGuard::assertQuantity($num);
        // 1. 扣主表库存 (乐观锁防超卖)
        $res = Goods::updateAllCounters(['stock' => -$num], ['and', ['id' => $goods->id], ['>=', 'stock', $num]]);
        if ($res == 0) {
            throw new \Exception("商品[{$goods->name}]库存不足，或已被抢空");
        }

        // 2. 扣规格表库存
        if ($attr) {
            $resAttr = Attr::updateAllCounters(['stock' => -$num], ['and', ['id' => $attr->id], ['>=', 'stock', $num]]);
            if ($resAttr == 0) {
                // 直接抛出异常，触发外层事务 RollBack 回滚上面的主库存
                throw new \Exception("商品[{$goods->name}]规格库存不足");
            }
        }
    }

    /**
     * 通用库存回滚逻辑
     */
    public function returnStock(Goods $goods, $attr, $num, $itemData)
    {
        // 幂等：同一笔退款只回滚一次库存
        $idempotentKey = 'return_stock_' . ($itemData['order_detail_id'] ?? $goods->id . '_' . $num);
        if (!LockHelper::lock($idempotentKey, 3600)) {
            \Yii::warning("[returnStock] 库存已回滚过，跳过 goods_id={$goods->id}");
            return;
        }

        if ($attr) {
            Attr::updateAllCounters(['stock' => $num], ['id' => $attr->id]);
        } elseif (!empty($itemData['attr_id'])) {
            Attr::updateAllCounters(['stock' => $num], ['id' => $itemData['attr_id']]);
        }

        Goods::updateAllCounters(['stock' => $num], ['id' => $goods->id]);
        
        // 2. 仅当明确传入已支付标识时回滚销量，且使用 GREATEST 严防死守负数 (-1) 的出现
        if (isset($itemData['is_pay']) && $itemData['is_pay']) {
            Goods::updateAll([
                'sales' => new \yii\db\Expression("GREATEST(0, `sales` - {$num})"),
                'virtual_sales' => new \yii\db\Expression("GREATEST(0, `virtual_sales` - {$num})")
            ], ['id' => $goods->id]);
        }
    }

    /**
     * ============================================================================
     * 第三部分：通用逻辑辅助方法
     * ============================================================================
     */
    public function getMchId(Goods $goods, array $data)
    {
        if (isset($data['mch_id']) && $data['mch_id'] > 0) {
            return (int)$data['mch_id'];
        }
        if ($goods->mch_id) {
            return $goods->mch_id;
        }
        return 0;
    }


    /**
     * 静态缓存商户对象，避免单次请求重复查询
     * @var array
     */
    protected static $mchCache = [];

    /**
     * 静态缓存"订单使用的优惠券是否平台券"，按 order_id 缓存
     * @var array<int,bool>
     */
    protected static $platformCouponCache = [];

    /**
     * 获取商户对象 (带缓存)
     * @param int $mchId
     * @return Mch|null
     */
    protected function getMch($mchId)
    {
        if ($mchId <= 0) {
            return null;
        }
        if (!isset(self::$mchCache[$mchId])) {
            self::$mchCache[$mchId] = Mch::findOne($mchId);
        }
        return self::$mchCache[$mchId];
    }

    /**
     * 判断订单使用的优惠券是否为平台券（出资方=平台）
     * 平台券：UserCoupon.mch_id == 0，由平台承担让利，不从商家收入扣
     * 商家券：UserCoupon.mch_id > 0，由该商家承担让利，从商家收入扣
     *
     * @param OrderDetail $detail
     * @return bool
     */
    protected function isPlatformCoupon(OrderDetail $detail): bool
    {
        $orderId = (int)$detail->order_id;
        if ($orderId <= 0) {
            return false;
        }
        if (isset(self::$platformCouponCache[$orderId])) {
            return self::$platformCouponCache[$orderId];
        }

        $isPlatform = false;
        $order = Order::findOne($orderId);
        if ($order && $order->user_coupon_id > 0) {
            $userCoupon = UserCoupon::findOne($order->user_coupon_id);
            if ($userCoupon && (int)$userCoupon->mch_id === 0) {
                $isPlatform = true;
            }
        }

        return self::$platformCouponCache[$orderId] = $isPlatform;
    }

    /**
     * 计算商户/平台预计收入 (旗舰完整版)
     * 剥洋葱模型
     */
    protected function calculateMchIncome(OrderDetail $detail, Goods $goods)
    {
        $breakdownList = [];
        $platformProfitAmount = '0.00';

        // ==========================================================
        // A. 确立基数
        // ==========================================================
        $memberCut = isset($detail->member_discount_price) ? (float)$detail->member_discount_price : 0;
        $currentValue = (float)$detail->total_price;
        $originTotalValue = bcadd((string)$currentValue, (string)$memberCut, 2);

        $breakdownList['start'] = [
            'label' => '商品原销售额',
            'value' => sprintf('%.2f', $originTotalValue),
            'is_bold' => false,
            'color' => '#909399'
        ];

        // ==========================================================
        // B. 逐项扣减优惠 (商家承担的部分)
        // ==========================================================
        if ($memberCut > 0) {
            $breakdownList['member_discount'] = [
                'label' => '会员权益让利',
                'value' => '-' . sprintf('%.2f', $memberCut),
                'color' => '#E6A23C'
            ];
        }

        // 优惠券：商家券从商家收入扣，平台券由平台承担（不扣商家）
        $couponCut = (float)$detail->coupon_cut_price;
        if ($couponCut > 0) {
            if ($this->isPlatformCoupon($detail)) {
                $breakdownList['platform_coupon'] = [
                    'label' => '平台券抵扣(平台承担)',
                    'value' => '0.00',
                    'note' => '平台为用户减免 ¥' . sprintf('%.2f', $couponCut),
                    'color' => '#67C23A'
                ];
            } else {
                $currentValue = max('0.00', bcsub((string)$currentValue, (string)$couponCut, 2));
                $breakdownList['coupon'] = ['label' => '优惠券抵扣(商家)', 'value' => '-' . sprintf('%.2f', $couponCut)];
            }
        }

        $firstOrderCut = isset($detail->first_order_discount_price) ? (float)$detail->first_order_discount_price : 0;
        if ($firstOrderCut > 0) {
            $currentValue = max('0.00', bcsub((string)$currentValue, (string)$firstOrderCut, 2));
            $breakdownList['first_order'] = ['label' => '首单立减(商家)', 'value' => '-' . sprintf('%.2f', $firstOrderCut), 'color' => '#409EFF'];
        }

        $integralCut = isset($detail->integral_cut_price) ? (float)$detail->integral_cut_price : 0;
        if ($integralCut > 0) {
            $currentValue = max('0.00', bcsub((string)$currentValue, (string)$integralCut, 2));
            $breakdownList['integral'] = ['label' => '积分抵扣', 'value' => '-' . sprintf('%.2f', $integralCut)];
        }

        $pickupCut = isset($detail->pickup_discount_price) ? (float)$detail->pickup_discount_price : 0;
        if ($pickupCut > 0) {
            $currentValue = max('0.00', bcsub((string)$currentValue, (string)$pickupCut, 2));
            $breakdownList['pickup'] = ['label' => '自提优惠', 'value' => '-' . sprintf('%.2f', $pickupCut)];
        }

        // ==========================================================
        // C. 平台补贴与用户实付
        // ==========================================================
        $settlementBase = max(0, (float)$currentValue);

        // 支付随机减：平台承担，不扣商家收入，仅用于计算用户实付
        $randomMinus = isset($detail->platform_subsidy_random_minus) ? (float)$detail->platform_subsidy_random_minus : 0;
        if ($randomMinus > 0) {
            $breakdownList['random_minus'] = [
                'label' => '支付随机减(平台承担)',
                'value' => '0.00',
                'note' => '平台为用户减免 ¥' . sprintf('%.2f', $randomMinus),
                'color' => '#67C23A'
            ];
        }

        // 平台券的让利金额：B 段没从 currentValue 扣过(平台承担不影响商家收入)，
        // 但用户实付要扣这一项，否则 detail.pay_price 会大于真实支付金额
        $platformCouponDeduction = ($couponCut > 0 && $this->isPlatformCoupon($detail))
            ? (float) $couponCut
            : 0;

        $realPayPrice = max(0, bcsub(
            bcsub((string)$settlementBase, (string)$randomMinus, 2),
            (string)$platformCouponDeduction,
            2
        ));
        $detail->pay_price = $realPayPrice;

        $breakdownList['real_pay'] = [
            'label' => '用户实付',
            'value' => sprintf('%.2f', $realPayPrice),
            'is_bold' => true,
            'color' => '#303133'
        ];

        // ==========================================================
        // D. 资金分配 (区分自营与第三方商家)
        // ==========================================================
        if ($goods->mch_id > 0) {
            $rate = 0;
            $rateSource = '未设置';

            // [优化] 使用静态缓存获取商户对象
            $mch = $this->getMch($goods->mch_id);

            if ($mch) {
                if (isset($goods->concession_rate) && $goods->concession_rate >= 0) {
                    $rate = floatval($goods->concession_rate);
                    $rateSource = '商品独立设置';
                } else {
                    $rate = floatval($mch->concession_rate);
                    $rateSource = '店铺统一设置';
                }
            } else {
                $rateSource = '商户不存在(免佣)';
                \Yii::warning("商品ID:{$goods->id} 关联的商户ID:{$goods->mch_id} 不存在", 'order.income');
            }

            if ($rate > 100)
                $rate = 100;
            if ($rate < 0)
                $rate = 0;

            if ($rate > 0) {
                $platformProfitAmount = bcmul((string)$settlementBase, (string)($rate / 100), 2);
                $finalIncome = bcsub((string)$settlementBase, $platformProfitAmount, 2);

                $breakdownList['platform_fee'] = [
                    'label' => "平台服务费({$rate}%)",
                    'value' => '-' . sprintf('%.2f', $platformProfitAmount),
                    'note' => "费率来源: {$rateSource}",
                    'color' => '#E6A23C'
                ];
            } else {
                $finalIncome = $settlementBase;
            }

            $detail->concession_amount = $platformProfitAmount;
        } else {
            $finalIncome = $settlementBase;
            $detail->concession_amount = 0;
        }

        // ==========================================================
        // E. 最终结果落库
        // ==========================================================
        $finalIncome = max(0, (float)$finalIncome);
        $finalLabel = ($goods->mch_id > 0) ? '商家最终入账' : '平台直营结转';

        $breakdownList['final'] = [
            'label' => $finalLabel,
            'value' => sprintf('%.2f', $finalIncome),
            'is_bold' => true,
            'color' => '#F56C6C'
        ];

        $detail->mch_income_price = $finalIncome;
        $detail->mch_income_breakdown = Json::encode(array_values($breakdownList), JSON_UNESCAPED_UNICODE);
    }

    /**
     * [钩子] 订单【分包裹发货】后的通知逻辑
     */
    public function sendPackageShippedNotification(Order $order, $expressLogId = null)
    {
        $title = '您的订单包裹已发出';
        $content = "您的订单({$order->order_no})中的部分或全部商品已发出。";

        if ($expressLogId) {
            $log = \app\models\ExpressLog::findOne($expressLogId);
            if ($log) {
                $content = "您的订单({$order->order_no})中的商品已通过 {$log->express_name} 发出，单号：{$log->express_no}。";
            }
        } else {
            $content = "您的订单({$order->order_no})中的虚拟或无需物流商品已处理。";
        }

        $this->sendNotification($order->user_id, $title, $content, 'order_package_shipped', $order->id);
    }

    /**
     * [钩子] 订单整单发货后的通知逻辑
     * 子类可重写此方法，实现个性化发货通知（例如虚拟商品发卡密、酒店确认房源）
     */
    public function sendShippedNotification(Order $order)
    {
        $title = '您的订单已全部发货';
        $content = "您的订单({$order->order_no})已全部发货，请留意查收或凭核销码到店提取。";

        $this->sendNotification($order->user_id, $title, $content, 'order_all_shipped', $order->id);
    }

    /**
     * [钩子] 订单确认收货(履约完成)后的通知逻辑
     */
    public function sendConfirmedNotification(Order $order)
    {
        // 1. 通知用户
        $this->sendNotification(
            $order->user_id,
            '交易完成通知',
            "您的订单（{$order->order_no}）已确认收货，交易完成。感谢您的惠顾！",
            'order_confirmed_to_user',
            $order->id
        );

        // 2. 通知商家
        if ($order->mch_id > 0) {
            $mch = $this->getMch($order->mch_id);
            if ($mch && $mch->user_id) {
                $customerName = $order->user ? $order->user->nickname : '顾客';
                $this->sendNotification(
                    $mch->user_id,
                    '订单确认收货',
                    "{$customerName}的订单（{$order->order_no}）已确认收货。款项将在维权期后进入您的可提现账户。",
                    'order_confirmed_to_mch',
                    $order->id
                );
            }
        }
    }

    /**
     * [通用] 发送消息通知 (封装 try-catch)
     */
    protected function sendNotification($userId, $title, $content, $type, $orderId = 0)
    {
        try {
            \app\helpers\MessageHelper::sendToUser(
                $userId,
                $title,
                $content,
                $type
            );
        } catch (\Exception $e) {
            \Yii::error("消息发送失败 [Type:{$type} OrderID:{$orderId}] - " . $e->getMessage());
        }
    }
}
