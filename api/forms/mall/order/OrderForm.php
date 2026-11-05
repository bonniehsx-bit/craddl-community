<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-02
 * Time: 22:18
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

namespace app\forms\mall\order;


use app\core\ApiCode;
use app\helpers\AddressHelper;
use app\helpers\JsonHelper;
use app\helpers\LockHelper;
use app\helpers\OptionHelper;
use app\helpers\ResponseHelper;
use app\models\BaseModel;
use app\models\CommonPayOrder;
use app\models\Order;
use app\models\OrderDetail;
use app\models\OrderRefund;
use app\services\OrderService;
use yii\helpers\Json;

class OrderForm extends BaseModel
{

    public $order_id;
    public $remarks;

    public function rules()
    {
        return [
            [['order_id'], 'required'],
            [['remarks'], 'string'],
            [['order_id'], 'integer']
        ];
    }

    /**
     * 获取订单详情 (search 方法的别名)
     * @return array
     */
    public function search()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // 【性能优化】使用 with() 预加载
        $order = Order::find()->where([
            'is_delete' => 0,
            'id' => $this->order_id,
            'mall_id' => \Yii::$app->mall->id
        ])->with([
            'orderDetails.goods',
            'user',
            'mch',
            'expressLog',
            'expressList',
        ])->asArray()->one();

        if (!$order) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '订单不存在!');
        }

        // --- 处理订单详情列表 ---
        $list = $order['orderDetails'] ?? [];
        foreach ($list as &$item) {
            // 0. 显示下单那一刻的商品(快照),老数据回退商品表;下面售后摘要里的商品名也跟着用快照
            OrderDetail::applySnapshotToRow($item);

            // 1. 解析商品规格 (attr)
            if (!empty($item['attr'])) {
                $item['attr'] = Json::decode($item['attr']);
            }

            // 2. [新增] 解析商家入账明细 (mch_income_breakdown)
            // 数据库存的是 JSON 字符串，前端需要数组来渲染瀑布流
            if (!empty($item['mch_income_breakdown'])) {
                try {
                    $item['mch_income_breakdown'] = Json::decode($item['mch_income_breakdown']);
                } catch (\Exception $e) {
                    $item['mch_income_breakdown'] = [];
                }
            } else {
                $item['mch_income_breakdown'] = [];
            }
        }
        unset($item); // 断开引用，防止意外修改

        // --- 处理用户信息 ---
        $user = $order['user'] ?? null;
        if (!$user) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '用户不存在！');
        }

        // --- 处理地址区域 ---
        $area = AddressHelper::getArea($order['province_code'], $order['city_code'], $order['county_code'], $order['town_code']);
        $order['area'] = $area;

        // --- 处理同城配送信息 ---
        if ($order && $order['city_delivery_info']) {
            $order['city_delivery_info'] = JsonHelper::decode($order['city_delivery_info']);
        }

        // 调用 Handler 获取前端展示面板数据
        $pluginData = null;
        try {
            $realOrder = Order::findOne($order['id']);
            $handler = \app\services\order\factory\OrderHandlerFactory::create($realOrder->plugin, $realOrder->type);
            $panels = $handler->getFrontendDisplayPanels($realOrder);
            $actionConfig = $handler->getActionConfig($realOrder);
            
            $pluginData = [
                'type' => $realOrder->plugin,
                'action_config' => $actionConfig
            ];
            
            if (!empty($panels)) {
                $pluginData['display_panels'] = $panels;
            }
        } catch (\Exception $e) {
            \Yii::error("获取订单插件展示面板失败: " . $e->getMessage());
        }

        // 售后:详情里说清这单有哪些售后、给出去处理的入口。有售后还在处理时关单、发货、核销、强制结算都不行,
        // 按钮的显隐与后端拦截用同一个判断(after_sale_block_reason)
        $order['after_sales'] = $this->afterSalesBrief((int)$order['id'], $list);
        $order['after_sale_block_reason'] = OrderService::afterSaleBlockReason($order);
        // 关单会退多少:只退还没退的部分(售后退过其中几件的单退剩下的),确认框里写明
        $payOrder = ((int)$order['pay_status'] !== Order::PAY_STATUS_UNPAID && (float)$order['pay_price'] > 0)
            ? CommonPayOrder::findOne(['order_id' => (int)$order['id'], 'is_delete' => 0]) : null;
        $order['close_refund_amount'] = $payOrder ? $payOrder->getRefundableAmount() : '0.00';

        // order.orderDetails 是没加工过的原始明细(抽屉 / 发货弹窗都读 list),也盖上快照,两份显示的商品一致
        foreach (array_keys($order['orderDetails'] ?? []) as $i) {
            OrderDetail::applySnapshotToRow($order['orderDetails'][$i]);
        }

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', [
            'user' => $user,
            'order' => $order,
            'list' => $list,
            'plugin_data' => $pluginData, // 通过 Handler 获取的扩展数据
        ]);
    }

    /**
     * 详情里列出的售后单:除了已拒绝、用户撤销的都列 —— 还在处理的,以及已经退过款的。
     * need_action:还等后台动手 —— 审核、确认打款、收到退货后打款;退款中、已完成的只是给看
     */
    private function afterSalesBrief(int $orderId, array $details): array
    {
        $goodsName = [];
        foreach ($details as $d) {
            $goodsName[(int)$d['id']] = $d['goods']['name'] ?? '';
        }
        $out = [];
        $rows = OrderRefund::find()
            ->where(['order_id' => $orderId, 'is_delete' => 0])
            ->andWhere(['not in', 'status', [OrderRefund::STATUS_REJECTED, OrderRefund::STATUS_CLOSED]])
            ->orderBy(['id' => SORT_ASC])
            ->all();
        foreach ($rows as $r) {
            $status = (int)$r->status;
            $out[] = [
                'id' => (int)$r->id,
                'order_no' => (string)$r->order_no,
                'type_text' => OrderRefund::TYPE_TEXT[(int)$r->type] ?? '售后',
                'status' => $status,
                'status_text' => OrderRefund::STATUS_TEXT[$status] ?? '',
                'refund_price' => number_format((float)$r->refund_price, 2, '.', ''),
                'reason' => (string)$r->reason,
                'goods_name' => $goodsName[(int)$r->order_detail_id] ?? '',
                'need_action' => in_array($status, [OrderRefund::STATUS_PENDING, OrderRefund::STATUS_AGREED, OrderRefund::STATUS_USER_SHIPPED], true),
            ];
        }
        return $out;
    }

    /**
     * 确认收货 (调用服务层)
     * @return array
     */
    public function confirm()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $order = $this->getOrder();
        if (!$order) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '订单不存在!');
        }
        if ($order->deliver_type == Order::DELIVER_TYPE_CITY) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '同城配送订单不可后台操作');
        }
        $orderService = new OrderService();
        if ($orderService->confirm($order)) {
            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '确认收货成功');
        } else {
            return $this->responseErrorMsg($orderService);
        }
    }

    /**
     * (后台)完成订单
     * @return array
     */
    public function finish()
    {
        return $this->confirm();
    }

    /**
     * (后台)关闭/取消订单 (调用服务层)
     * @return array
     */
    public function close()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        if (!LockHelper::lock(self::class . '_close_' . $this->order_id, 3)) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '请勿频繁操作');
        }
        $order = $this->getOrder();
        if (!$order) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '订单不存在!');
        }

        $orderService = new OrderService();
        if ($orderService->cancel($order)) {
            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '订单已关闭');
        } else {
            return $this->responseErrorMsg($orderService);
        }
    }

    /**
     * (后台)手动支付
     * @return array
     */
    public function pay()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        try {
            $payments = OptionHelper::get('payments', $this->mall_id);
            $systemPaymentConfig = $payments[\app\payments\SystemPayment::class] ?? [];
            if (empty($systemPaymentConfig['status'])) {
                throw new \Exception('后台支付功能未开启');
            }
            $order = Order::findOne(['is_delete' => 0, 'id' => $this->order_id, 'mall_id' => $this->mall_id, 'status' => Order::STATUS_UNPAID]);
            if (!$order) {
                throw new \Exception('订单不存在或已支付!');
            }
            $payOrder = CommonPayOrder::findOne(['order_no' => $order->order_no, 'is_delete' => 0]);
            if (!$payOrder) {
                throw new \Exception('支付单不存在!');
            }

            $systemPayment = new \app\payments\SystemPayment($this->mall_id);
            $systemPayment->handleCallback(['order_no' => $payOrder->order_no, 'pay_order_id' => $payOrder->id]);

            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '已确认支付!');
        } catch (\Exception $e) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }

    /**
     * (后台)修改备注
     * @return array|\yii\web\Response
     */
    public function remarks()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $order = $this->getOrder();
        if (!$order) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '订单不存在');
        }
        $order->remarks = $this->remarks;
        if (!$order->save()) {
            return $this->responseErrorInfo($order);
        }
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '保存成功');
    }

    /**
     * 获取订单的私有辅助方法
     * @return Order|null
     */
    private function getOrder()
    {
        return Order::findOne(['id' => $this->order_id, 'is_delete' => 0, 'mall_id' => \Yii::$app->mall->id]);
    }
}
