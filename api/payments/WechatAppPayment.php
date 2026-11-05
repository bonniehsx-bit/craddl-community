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

/**
 * 微信 App 端「原生」支付(trade_type=APP)。
 *
 * 与 WechatPayment(公众号 JSAPI/H5)的区别:
 *  - platforms() = [APP],只在 App 端展示;
 *  - 下单走 v3/pay/transactions/app(不需要 openid);
 *  - appid 用【微信开放平台·移动应用 appid】(与公众号/小程序 appid 不同,须在商户平台绑定并开通「APP支付」);
 *  - 返回值用 EasyWeChat 官方 buildAppConfig() 生成 App 支付二次签名参数,前端 uni.requestPayment(provider:wxpay)。
 * 商户号/APIv3密钥/证书可与现有微信支付共用同一个商户(证书按 mch_id 存,复用同一份)。
 */
class WechatAppPayment extends BasePayment
{
    /** @var \EasyWeChat\Pay\Application */
    public $payment;

    public function getConfig()
    {
        return $this->config;
    }

    public function __construct($mall_id)
    {
        $this->mall_id = $mall_id;
        $payments = OptionHelper::get('payments', $mall_id);
        if (!$payments) {
            throw new InvalidArgumentException('App微信支付未开启');
        }
        $config = $payments[self::class] ?? null;
        if (!$config || empty($config['status'])) {
            throw new InvalidArgumentException('App微信支付未开启');
        }
        $this->config = $config;
        $this->logo = $config['logo'] ?? '';
        $this->name = $config['name'] ?? '微信支付';
        $this->desc = $config['desc'] ?? '';
        $this->payment_type = self::class;

        // EasyWeChat Pay\Application 只需商户凭证(mch_id/密钥/证书);appid 在下单请求 + buildAppConfig 时按 App appid 传。
        // 与 WechatPayment 同用 wechatPaymentConfig 这个 params key;WechatComponent::getPayment 按 config 的 md5 缓存,
        // 本渠道 appid 不同 → hash 不同 → 自动重建正确实例,不会与公众号支付串。
        \Yii::$app->params['wechatPaymentConfig'] = [
            'app_id' => $config['app_id'], // 开放平台移动应用 appid
            'mch_id' => $config['mch_id'],
            'secret_key' => !empty($config['api_v3_key']) ? $config['api_v3_key'] : ($config['api_v2_key'] ?? ''),
            'v2_secret_key' => $config['api_v2_key'] ?? '',
            'certificate' => WechatPayHelper::getApiclientCertPemPath($config['mch_id']),
            'private_key' => WechatPayHelper::getApiclientKeyPemPath($config['mch_id']),
            'notify_url' => ServerHelper::getHost() . '/notify/wechat-app-pay.php/' . $this->mall_id,
        ];

        $this->payment = \Yii::$app->wechat->payment;
    }

    /**
     * App 统一下单(trade_type=APP)+ 二次签名,返回 uni.requestPayment(provider:wxpay) 所需 orderInfo。
     */
    public function placeOrder($orderData)
    {
        try {
            $client = $this->payment->getClient();
            $response = $client->postJson('v3/pay/transactions/app', [
                'mchid' => $this->config['mch_id'],
                'out_trade_no' => (string) $orderData['out_trade_no'],
                'appid' => $this->config['app_id'], // 开放平台移动应用 appid
                'description' => $orderData['body'],
                'notify_url' => ServerHelper::getHost() . '/notify/wechat-app-pay.php/' . $this->mall_id,
                'amount' => [
                    'total' => intval(bcmul($orderData['pay_price'], 100)),
                    'currency' => 'CNY',
                ],
                'attach' => Json::encode(['class' => self::class, 'mall_id' => $this->mall_id]),
            ]);

            $result = $response->toArray();

            CommonPayOrder::updateAll(
                ['payment' => self::class, 'payment_config' => Json::encode(\Yii::$app->params['wechatPaymentConfig'])],
                ['id' => $orderData['pay_order_id']]
            );

            if ($response->getStatusCode() === 200 && isset($result['prepay_id'])) {
                // 官方工具:拼 App 支付参数 + 商户私钥 RSA-SHA256 二次签名
                $appConfig = $this->payment->getUtils()->buildAppConfig($result['prepay_id'], $this->config['app_id']);
                $appConfig['is_pay'] = 0;
                $appConfig['payment'] = self::class; // 与其它渠道一致,供前端派发兜底
                return $appConfig;
            }

            Yii::error('App微信支付下单失败: ' . json_encode($result));
            throw new \Exception($result['message'] ?? 'App微信支付下单失败');
        } catch (\Throwable $e) {
            // 微信 4xx(如 401)的真实原因在响应体里(SIGN_ERROR/NO_AUTH/商户号不存在/证书序列号不匹配等),
            // EasyWeChat 抛出的异常默认只带 HTTP 状态行,这里把响应体抓出来,便于定位是哪项商户凭证/权限配置错。
            $wxBody = '';
            if (method_exists($e, 'getResponse')) {
                try {
                    $wxBody = $e->getResponse()->getContent(false);
                } catch (\Throwable $ignore) {
                }
            }
            Yii::error('App微信支付下单异常: ' . $e->getMessage() . ($wxBody !== '' ? ' | 微信返回: ' . $wxBody : ''));
            throw new \Exception('App微信支付下单失败:' . ($wxBody !== '' ? $wxBody : $e->getMessage()));
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
            }
            Yii::error('App微信支付退款失败: ' . json_encode($result));
            throw new \Exception('App微信支付退款失败：' . ($result['message'] ?? '未知错误'));
        } catch (\Exception $e) {
            Yii::warning('App微信支付退款异常: ' . $e->getMessage());
            throw $e;
        }
    }

    public function handleCallback($callbackData)
    {
        try {
            $server = $this->payment->getServer();
            $server->handlePaid(function ($message) {
                $out_trade_no = $message['out_trade_no'];
                // 三级匹配(当前号→历史号→order_no),详见 CommonPayOrder::findByTradeNo
                $order = CommonPayOrder::findByTradeNo($out_trade_no, ['is_delete' => 0]);
                if (!$order) {
                    return;
                }
                if ($order->is_pay) {
                    \Yii::warning('App微信订单已支付，不要再通知我');
                    return;
                }
                // 第四参:V3 回调 amount.total 是分,换算成元与 pay_price 比对;字段缺失则跳过比对
                $paidAmount = isset($message['amount']['total']) ? bcdiv((string) $message['amount']['total'], '100', 2) : null;
                $order->paySuccess($message['transaction_id'], self::class, null, $paidAmount);
            });

            $response = $server->serve();
            \Yii::$app->response->statusCode = $response->getStatusCode();
            \Yii::$app->response->content = $response->getBody()->getContents();
            \Yii::$app->response->send();
            return true;
        } catch (\Exception $e) {
            Yii::getLogger()->log('App微信支付回调处理异常：' . $e->getMessage(), Logger::LEVEL_ERROR);
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
            }
            throw new \Exception('App微信支付订单查询失败：' . ($result['trade_state_desc'] ?? $result['message'] ?? '查询失败'));
        } catch (\Exception $e) {
            Yii::getLogger()->log('App微信支付订单查询异常：' . $e->getMessage(), Logger::LEVEL_ERROR);
            throw $e;
        }
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
                throw new \Exception('App微信支付退款查询未成功：' . $result['status']);
            }
            throw new \Exception('App微信支付退款查询失败：' . ($result['message'] ?? '查询失败'));
        } catch (\Exception $e) {
            Yii::getLogger()->log('App微信支付退款查询异常：' . $e->getMessage(), Logger::LEVEL_ERROR);
            throw $e;
        }
    }

    public function getDisplay()
    {
        return [
            'logo' => $this->logo,
            'name' => $this->name,
            'desc' => $this->desc,
            'payment_type' => self::class,
        ];
    }

    public static function supportedPlatforms(): array
    {
        return [ConstantHelper::PLATFORM_APP];
    }

    public function applySettleOrder($settleOrderData, $extra = null) {}
}
