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
use app\helpers\ConstantHelper;
use app\helpers\OptionHelper;
use app\helpers\ServerHelper;
use app\helpers\WechatPayHelper;
use app\models\CommonPayOrder;
use EasyWeChat\Kernel\Exceptions\InvalidArgumentException;
use Yii;
use yii\helpers\Json;
use yii\log\Logger;

class WechatPayment extends BasePayment
{
    /**
     * @var \EasyWeChat\Pay\Application
     */
    public $payment; // 显式声明该属性，解决 PHP 8.2+ 报错问题

    public function getConfig()
    {
        return $this->config;
    }

    public function __construct($mall_id)
    {
        $this->mall_id = $mall_id;
        /*
         * 注：如果系统原来没有独立的 WechatPaymentConfig，
         * 这里获取的逻辑和 MpwxPayment 保持高度一致。
         * 如果在前端商户后台新增了【公众号支付】的网关开关，请确保保存进 'payments' 中！
         */
        $payments = OptionHelper::get('payments', $mall_id);
        if (!$payments) {
            throw new InvalidArgumentException('微信公众号支付未开启');
        }
        $config = $payments[self::class] ?? null;
        if (!$config || empty($config['status'])) {
            throw new InvalidArgumentException('微信公众号支付未开启');
        }
        $this->config = $config;
        $this->logo = $config['logo'] ?? '';
        $this->name = $config['name'] ?? '微信公众号支付';
        $this->desc = $config['desc'] ?? '';
        $this->payment_type = self::class;

        \Yii::$app->params['wechatPaymentConfig'] = [
            'app_id' => $config['app_id'],
            'mch_id' => $config['mch_id'],
            'secret_key' => !empty($config['api_v3_key']) ? $config['api_v3_key'] : ($config['api_v2_key'] ?? ''), // 优先 v3;只配了 v3 的商城可能没有 v2 这个键
            'v2_secret_key' => $config['api_v2_key'] ?? '', // API V2 密钥(老配置/只配 v3 的商城没有这个键)
            'certificate' => WechatPayHelper::getApiclientCertPemPath($config['mch_id']), // 绝对路径
            'private_key' => WechatPayHelper::getApiclientKeyPemPath($config['mch_id']), // 绝对路径
            // 将专用回调接口独立指向 wechat-pay.php 并附加 path ID
            'notify_url' => ServerHelper::getHost() . '/notify/wechat-pay.php/' . $this->mall_id,
        ];

        // 复用 WechatComponent V6 统一下单实例
        $this->payment = \Yii::$app->wechat->payment;
    }

    public function placeOrder($orderData)
    {
        if (empty($orderData['openid'])) {
            throw new \Exception('微信JSAPI支付下单失败：缺少必要的 openid 参数');
        }

        try {
            $client = $this->payment->getClient();
            $response = $client->postJson('v3/pay/transactions/jsapi', [
                'mchid' => $this->config['mch_id'],
                'out_trade_no' => (string) $orderData['out_trade_no'],
                'appid' => $this->config['app_id'],
                'description' => $orderData['body'],
                'notify_url' => ServerHelper::getHost() . '/notify/wechat-pay.php/' . $this->mall_id,
                'amount' => [
                    'total' => intval(bcmul($orderData['pay_price'], 100)),
                    'currency' => 'CNY',
                ],
                'payer' => [
                    'openid' => $orderData['openid'],
                ],
                'attach' => Json::encode(['class' => self::class, 'mall_id' => $this->mall_id]),
            ]);

            $result = $response->toArray();

            CommonPayOrder::updateAll(['payment' => self::class, 'pay_openid' => $orderData['openid'], 'payment_config' => Json::encode(\Yii::$app->params['wechatPaymentConfig'])], ['id' => $orderData['pay_order_id']]);

            if ($response->getStatusCode() === 200 && isset($result['prepay_id'])) {
                $prepayId = $result['prepay_id'];

                $utils = $this->payment->getUtils();
                // ✅ 公众号 H5 对应官方的 JSAPI 支付配置签发
                $config = $utils->buildSdkConfig($prepayId, $this->config['app_id']);

                $config['is_pay'] = 0;
                return $config;
            } else {
                Yii::error("公众号微信原生支付下单失败: " . json_encode($result));
                throw new \Exception($result['message'] ?? '微信公众号支付下单失败');
            }
        } catch (\Exception $e) {
            Yii::error("公众号微信原生支付下单异常: " . $e->getMessage());
            throw $e;
        }
    }

    protected function doRefund($params)
    {
        try {
            $client = $this->payment->getClient();
            $response = $client->postJson('v3/refund/domestic/refunds', [
                'out_trade_no' => (string) $params['out_trade_no'],
                'out_refund_no' => (string) $params['refund_order_no'],
                'amount' => [
                    'refund' => intval(bcmul($params['refund_price'], 100)),
                    'total' => intval(bcmul($params['pay_price'], 100)),
                    'currency' => 'CNY',
                ],
            ]);

            $result = $response->toArray();
            if ($response->getStatusCode() === 200 && isset($result['refund_id'])) {
                return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '退款成功!'];
            } else {
                Yii::error("公众号原生微信支付退款失败: " . json_encode($result));
                throw new \Exception('微信公众号支付退款失败：' . ($result['message'] ?? '未知错误'));
            }
        } catch (\Exception $e) {
            Yii::warning("公众号原生微信支付退款异常: " . $e->getMessage());
            throw $e;
        }
    }


    public function handleCallback($callbackData)
    {
        try {
            $server = $this->payment->getServer();
            $server->handlePaid(function ($message) {

                $out_trade_no = $message['out_trade_no'];
                // 三级匹配(当前号→历史号→order_no):重复发起支付会覆盖 out_trade_no,
                // 用户在旧收银台付款时回调带回的是旧号,不认历史就会掉单。
                $order = CommonPayOrder::findByTradeNo($out_trade_no, ['is_delete' => 0]);

                if (!$order) {
                    return;
                }
                if ($order->is_pay) {
                    \Yii::warning('公众号订单已支付，不要再通知我');
                    return;
                }

                // 第四参:V3 回调 amount.total 是分,换算成元与 pay_price 比对;字段缺失则跳过比对
                $paidAmount = isset($message['amount']['total']) ? bcdiv((string) $message['amount']['total'], '100', 2) : null;
                $order->paySuccess($message['transaction_id'], WechatPayment::class, null, $paidAmount);
            });

            $response = $server->serve();
            \Yii::$app->response->statusCode = $response->getStatusCode();
            \Yii::$app->response->content = $response->getBody()->getContents();
            \Yii::$app->response->send();
            return true;
        } catch (\Exception $e) {
            Yii::getLogger()->log("公众号微信支付回调处理异常：" . $e->getMessage(), Logger::LEVEL_ERROR);
            throw $e;
        }
    }

    public function queryOrder($queryData)
    {
        try {
            $order_no = $queryData['out_trade_no'];
            $client = $this->payment->getClient();
            $response = $client->get("v3/pay/transactions/out-trade-no/{$order_no}?mchid={$this->config['mch_id']}");

            $result = $response->toArray();
            if ($response->getStatusCode() === 200 && isset($result['trade_state']) && $result['trade_state'] === 'SUCCESS') {
                return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '已支付'];
            } else {
                throw new \Exception('微信公众号支付订单查询失败：' . ($result['trade_state_desc'] ?? $result['message'] ?? '查询失败'));
            }
        } catch (\Exception $e) {
            Yii::getLogger()->log("微信公众号支付订单查询异常：" . $e->getMessage(), Logger::LEVEL_ERROR);
            throw $e;
        }
        return null;
    }

    public function queryRefund($queryData)
    {
        try {
            $out_refund_no = $queryData['out_refund_no'];
            $client = $this->payment->getClient();
            $response = $client->get("v3/refund/domestic/refunds/{$out_refund_no}");

            $result = $response->toArray();
            if ($response->getStatusCode() === 200 && isset($result['status'])) {
                if ($result['status'] === 'SUCCESS') {
                    return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '退款已成功'];
                }
                throw new \Exception('微信公众号支付退款查询未成功：' . $result['status']);
            } else {
                throw new \Exception('微信公众号支付退款查询失败：' . ($result['message'] ?? '查询失败'));
            }
        } catch (\Exception $e) {
            Yii::getLogger()->log("微信公众号支付退款查询异常：" . $e->getMessage(), Logger::LEVEL_ERROR);
            throw $e;
        }
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
        // 绑定所属平台类型：公众号 / H5
        return [ConstantHelper::PLATFORM_WECHAT];
    }

    public function applySettleOrder($settleOrderData, $extra = null) {}
}
