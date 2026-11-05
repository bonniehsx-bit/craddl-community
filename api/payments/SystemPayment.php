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

namespace app\payments;

use app\helpers\ConstantHelper;
use app\helpers\OptionHelper;
use app\models\CommonPayOrder;
use EasyWeChat\Kernel\Exceptions\InvalidArgumentException;
use Yii;

/**
 * Class SystemPayment
 * @package app\payments
 *
 * 这是一个虚拟的支付网关，用于处理后台手动确认收款的场景。
 */
class SystemPayment extends BasePayment
{
    public function __construct($mall_id)
    {
        $this->mall_id = $mall_id;
        $payment = OptionHelper::get('payments', $mall_id);

        if (!$payment || !isset($payment[self::class])) {
            throw new InvalidArgumentException('后台支付方式未配置');
        }
        $config = $payment[self::class];
        $this->config = $config;

        // 构造函数中不再检查 status，由调用方（如OrderForm）来检查
        $this->logo = $config['logo'] ?? '';
        $this->name = $config['name'] ?? '后台支付';
        $this->desc = $config['desc'] ?? '';
        $this->payment_type = self::class;
    }

    /**
     * 后台支付没有“下单”环节，此方法为空实现
     */
    public function placeOrder($orderData)
    {
        // 直接模拟支付成功
        CommonPayOrder::updateAll(
            [
                'payment' => self::class,
            ],
            ['id' => $orderData['pay_order_id']]
        );
        $this->handleCallback($orderData);
        return ['is_pay' => 1];
    }

    /**
     * 后台支付的退款是线下操作，这里只做标记，不调用API
     */
    protected function doRefund($order)
    {
        // 实际的退款操作由财务线下完成
        // 这里可以记录一条系统日志
        Yii::warning("后台支付订单 [{$order['order_no']}] 触发退款，金额: {$order['refund_price']}，需线下处理。");
        return ['code' => 0, 'msg' => '退款请求已记录，请线下处理'];
    }

    /**
     * 核心回调处理，由 OrderForm::pay() 间接触发
     */
    public function handleCallback($callbackData)
    {
        $payOrderId = (int)($callbackData['pay_order_id'] ?? 0);
        if ($payOrderId <= 0) return true;
        $order = CommonPayOrder::findOne($payOrderId);
        if (!$order) return true;
        if ($order->is_pay) return true;

        $transactionId = 'SYS_' . date('YmdHis') . '_' . mt_rand(1000, 9999);
        $order->paySuccess($transactionId, static::class);
    }

    public function queryOrder($queryData)
    {
        return null;
    }

    public function getDisplay()
    {
        return [
            'logo' => $this->logo,
            'name' => $this->name,
            'desc' => $this->desc,
            'payment_type' => self::class
        ];
    }

    public static function supportedPlatforms(): array
    {
        return [];
    } // 不支持任何前端平台

    public function getConfig()
    {

        return $this->config;
    }

    public function applySettleOrder($settleOrderData, $extra = null)
    {
        // TODO: Implement applySettleOrder() method.
    }
}