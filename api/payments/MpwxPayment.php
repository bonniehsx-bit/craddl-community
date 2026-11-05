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
use app\helpers\ResponseHelper;
use app\helpers\ServerHelper;
use app\helpers\WechatPayHelper;
use app\models\CommonPayOrder;
use EasyWeChat\Kernel\Exceptions\InvalidArgumentException;
use Yii;
use yii\helpers\Json;
use yii\log\Logger;

class MpwxPayment extends BasePayment
{
    // ================== 【修改点在这里】 ==================
    /**
     * @var \EasyWeChat\Pay\Application
     */
    public $payment; // 显式声明该属性，解决 PHP 8.2+ 报错问题

    // ====================================================

    public function getConfig()
    {
        return $this->config;
    }

    public function __construct($mall_id)
    {
        //拿到支付参数之后
        $this->mall_id = $mall_id;
        //初始化支付参数
        $payments = OptionHelper::get('payments', $mall_id);
        if (!$payments) {
            throw new InvalidArgumentException('微信支付未开启');
        }
        $config = $payments[self::class] ?? null;
        if (!$config || empty($config['status'])) {
            throw new InvalidArgumentException('微信支付未开启');
        }
        $this->config = $config;
        // 显示字段一律兜底:历史配置(在这些字段加进后台表单之前保存的)库里根本没有对应的键,
        // PHP 8 下裸下标取值会抛 Warning,被 Yii 错误处理器转成 ErrorException ——
        // 而这个构造函数在支付回调入口就被调用,一炸整条回调链断掉,订单永远不会变成已支付。
        // (2026-09-02 线上事故:某商城配置缺 desc,微信回调持续报 Undefined array key "desc")
        $this->logo = $config['logo'] ?? '';
        $this->name = $config['name'] ?? '微信支付';
        $this->desc = $config['desc'] ?? '';
        $this->payment_type = self::class;
        \Yii::$app->params['wechatPaymentConfig'] = [
            'app_id' => $config['app_id'],
            'mch_id' => $config['mch_id'],
            'secret_key' => !empty($config['api_v3_key']) ? $config['api_v3_key'] : ($config['api_v2_key'] ?? ''), // 优先 v3;只配了 v3 的商城可能没有 v2 这个键
            'v2_secret_key' => $config['api_v2_key'] ?? '', // API V2 密钥(老配置可能没这个键)
            'certificate' => WechatPayHelper::getApiclientCertPemPath($config['mch_id']), // XXX: 绝对路径！！！！
            'private_key' => WechatPayHelper::getApiclientKeyPemPath($config['mch_id']), // XXX: 绝对路径！！！！
            'notify_url' => ServerHelper::getHost() . '/notify/mpwx-pay.php/' . $this->mall_id, // 独立多商户回调入口 (RESTful Path)
        ];


        // 之前报错就是因为 payment 属性没有在上面声明
        $this->payment = \Yii::$app->wechat->payment;
    }

    public function placeOrder($orderData)
    {
        if (empty($orderData['openid'])) {
            throw new \Exception('微信JSAPI支付下单失败：缺少必要的 openid 参数');
        }

        try {
            // V6 采用 API v3 下单
            $client = $this->payment->getClient();
            $response = $client->postJson('v3/pay/transactions/jsapi', [
                'mchid' => $this->config['mch_id'],
                'out_trade_no' => (string) $orderData['out_trade_no'],
                'appid' => $this->config['app_id'],
                'description' => $orderData['body'],
                'notify_url' => ServerHelper::getHost() . '/notify/mpwx-pay.php/' . $this->mall_id, // 独立多商户回调入口 (RESTful Path)
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

                // V6 生成前端 JSAPI 支付所需参数
                $utils = $this->payment->getUtils();
                $config = $utils->buildMiniAppConfig($prepayId, $this->config['app_id']);

                $config['is_pay'] = 0;
                return $config;
            } else {
                Yii::error("微信原生支付下单失败: " . json_encode($result));
                throw new \Exception($result['message'] ?? '微信支付下单失败');
            }
        } catch (\Exception $e) {
            // 微信 V3 在 4xx(401/403等)时 Symfony HttpClient 会直接抛异常，响应体不在上面的 $result 里，
            // 必须从异常携带的 response 把微信返回的业务 message 掏出来
            // (如 NO_AUTH「此商家收款功能已被限制」、SIGN_ERROR「证书序列号有误」)，否则前端只会看到 "HTTP/1.1 401..."。
            $wxBody = '';
            if (method_exists($e, 'getResponse')) {
                try {
                    $resp = $e->getResponse();
                    if ($resp && method_exists($resp, 'getContent')) {
                        $wxBody = $resp->getContent(false); // false = 不因 4xx/5xx 再抛
                    }
                } catch (\Throwable $ignore) {
                }
            }
            Yii::error("微信原生支付下单异常: " . $e->getMessage() . ' | 微信响应体: ' . $wxBody);

            // 解析出微信的人话 message，抛给上层(PayForm)原样回前端；解析不到再退化用原始异常文案
            $wxMsg = '';
            if ($wxBody) {
                $decoded = json_decode($wxBody, true);
                $wxMsg = is_array($decoded) ? ($decoded['message'] ?? '') : '';
            }
            throw new \Exception($wxMsg !== '' ? $wxMsg : ('微信支付下单失败：' . $e->getMessage()));
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
                Yii::error("微信原生支付退款失败: " . json_encode($result));
                throw new \Exception('微信支付退款失败：' . ($result['message'] ?? '未知错误'));
            }
        } catch (\Exception $e) {
            Yii::warning("微信原生支付退款异常: " . $e->getMessage());
            throw $e;
        }
    }


    public function handleCallback($callbackData)
    {
        try {
            $server = $this->payment->getServer();
            $server->handlePaid(function ($message) {

                // V6 的支付回调解密后的真实数据在 $message 内部（根据实际调试 $message 即为 payload）
                $out_trade_no = $message['out_trade_no'];
                // 三级匹配(当前号→历史号→order_no):重复发起支付会覆盖 out_trade_no,
                // 用户在旧收银台付款时回调带回旧号,不认历史就会掉单。
                $order = CommonPayOrder::findByTradeNo($out_trade_no, ['is_delete' => 0]);

                if (!$order) {
                    return; // V6 server handlePaid 返回 null/void 则被视为成功，抛异常视为失败
                }
                if ($order->is_pay) {
                    \Yii::warning('订单已支付，不要再通知我');
                    return;
                }

                // 第四参:V3 回调 amount.total 是分,换算成元与 pay_price 比对;字段缺失则跳过比对
                $paidAmount = isset($message['amount']['total']) ? bcdiv((string) $message['amount']['total'], '100', 2) : null;
                $order->paySuccess($message['transaction_id'], MpwxPayment::class, null, $paidAmount);
            });

            // V6 Serve 直接返回 Psr Response 对象，转换为 Yii2 Response
            $response = $server->serve();
            \Yii::$app->response->statusCode = $response->getStatusCode();
            \Yii::$app->response->content = $response->getBody()->getContents();
            \Yii::$app->response->send();
            return true;
        } catch (\Exception $e) {
            Yii::getLogger()->log("微信支付回调处理异常：" . $e->getMessage(), Logger::LEVEL_ERROR);
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
                throw new \Exception('微信支付订单查询失败：' . ($result['trade_state_desc'] ?? $result['message'] ?? '查询失败'));
            }
        } catch (\Exception $e) {
            Yii::getLogger()->log("微信支付订单查询异常：" . $e->getMessage(), Logger::LEVEL_ERROR);
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
                throw new \Exception('微信原生支付退款查询未成功：' . $result['status']);
            } else {
                throw new \Exception('微信原生支付退款查询失败：' . ($result['message'] ?? '查询失败'));
            }
        } catch (\Exception $e) {
            Yii::getLogger()->log("微信原生支付退款查询异常：" . $e->getMessage(), Logger::LEVEL_ERROR);
            throw $e; // 原样抛出异常给外层，让 RefundTask 生成新单号重试
        }
    }

    public function getDisplay()
    {
        // TODO: Implement getDisplay() method.
        return [
            'logo' => $this->logo,
            'name' => $this->name,
            'desc' => $this->desc,
            'payment_type' => self::class
        ];
    }

    public static function supportedPlatforms(): array
    {
        // TODO: Implement platforms() method.
        return [ConstantHelper::PLATFORM_MPWX];
    }

    public function applySettleOrder($settleOrderData, $extra = null)
    {
        // TODO: Implement applySettleOrder() method.
    }
}
