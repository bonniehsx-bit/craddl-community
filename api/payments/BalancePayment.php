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

use app\core\ApiCode;
use app\helpers\BalanceLogHelper;
use app\helpers\ConstantHelper;
use app\helpers\Helper;
use app\helpers\OptionHelper;
use app\helpers\ResponseHelper;
use app\models\CommonPayOrder;
use EasyWeChat\Kernel\Exceptions\InvalidArgumentException;
use Yii;
use yii\base\Exception;
use yii\helpers\Json;
use yii\log\Logger;

class BalancePayment extends BasePayment
{

    public function __construct($mall_id = 1)
    {
        //拿到支付参数之后
        //初始化支付参数
        $payments = OptionHelper::get('payments', $mall_id);
        if (!$payments) {
            throw new InvalidArgumentException('余额支付未开启');
        }
        $config = $payments[self::class] ?? null;
        if (!$config || empty($config['status'])) {
            throw new InvalidArgumentException('余额支付未开启');
        }
        // 同 MpwxPayment:历史配置可能缺这几个显示字段,裸取会抛 ErrorException
        $this->logo = $config['logo'] ?? '';
        $this->name = $config['name'] ?? '余额支付';
        $this->desc = $config['desc'] ?? '';
        $this->payment_type = self::class;
    }

    public function placeOrder($orderData)
    {
        $commonPayOrder = CommonPayOrder::findOne($orderData['pay_order_id']);
        if (!$commonPayOrder) {
            throw new Exception('支付订单不存在');
        }
        // 幂等：已支付直接返回
        if ($commonPayOrder->is_pay) {
            return ['is_pay' => 1];
        }
        // 已取消则阻断
        if ($commonPayOrder->is_cancel) {
            throw new Exception('订单已取消');
        }

        CommonPayOrder::updateAll(['payment' => self::class], ['id' => $orderData['pay_order_id']]);

        $pay_price = $orderData['pay_price'];
        $orderNo = $commonPayOrder->order_no ?: $orderData['out_trade_no'];
        $transactionId = Helper::createOrderNo('B');

        // 使用事务：扣款 + paySuccess 原子执行
        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!BalanceLogHelper::sub($orderData['user_id'], $pay_price, "支付订单：{$orderNo}", 0, 0, $orderData['body'])) {
                throw new Exception('余额不足');
            }

            if (!$commonPayOrder->paySuccess($transactionId, static::class)) {
                throw new Exception('订单状态更新失败');
            }

            $transaction->commit();
            return ['is_pay' => 1];
        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::error("[BalancePayment] 余额支付失败: " . $e->getMessage());
            return ['is_pay' => 0, 'msg' => $e->getMessage()];
        }
    }

    protected function doRefund($order)
    {
        $commonPayOrder = CommonPayOrder::findOne($order['pay_order_id']);
        $orderNo = $commonPayOrder ? $commonPayOrder->order_no : $order['out_trade_no'];

        BalanceLogHelper::add($order['user_id'], $order['refund_price'], "退款订单：{$orderNo}", 0, 0, '订单退款');
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '退款成功');
    }


    public function handleCallback($callbackData)
    {
        $order = CommonPayOrder::findOne($callbackData['pay_order_id']);
        if (!$order) {
            return true;
        }
        if ($order->is_pay) {
            return true;
        }
        $order->paySuccess(Helper::createOrderNo('B'), static::class);
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '订单已支付');
    }

    public function queryOrder($queryData)
    {
        // 三级匹配(当前号→历史号→order_no),详见 CommonPayOrder::findByTradeNo
        $order = CommonPayOrder::findByTradeNo($queryData['out_trade_no'], ['is_delete' => 0]);
        if ($order && $order->is_pay == 1) {
            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '已支付');
        }
        return null;
    }

    public function getDisplay()
    {
        // TODO: Implement getDisplay() method.
        return ['logo' => $this->logo, 'name' => $this->name, 'desc' => $this->desc, 'payment_type' => self::class];
    }

    public static function supportedPlatforms(): array
    {
        // TODO: Implement platforms() method.
        return [
            ConstantHelper::PLATFORM_MPWX,
            ConstantHelper::PLATFORM_H5,
            ConstantHelper::PLATFORM_APP,
            ConstantHelper::PLATFORM_WECHAT,
        ];
    }

    /**
     * 即时扣款:placeOrder 里扣款与 paySuccess 是同一个事务,调用即完成支付,
     * 没有收银台确认环节。企微中转页这类「只取支付参数」的场景必须排除它,
     * 否则用户点一下链接余额就没了。
     */
    public static function isInstantDebit(): bool
    {
        return true;
    }

    public function getConfig()
    {
        // TODO: Implement getConfig() method.
    }

    public function applySettleOrder($settleOrderData, $extra = null)
    {
        // TODO: Implement applySettleOrder() method.
    }
}