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
use app\helpers\ServerHelper;
use app\models\CommonPayOrder;
use Yii;
use yii\helpers\Json;
use app\payments\contracts\MerchantApplicationPaymentInterface;

/**
 * [最终修复版] 快付(FastPay) 支付宝JSAPI/SCAN支付网关
 *
 * @desc 修复了签名生成逻辑，并增加了对多商户配置的支持，结构与微信支付方式保持一致。
 */
class FastPayAliJsPayment extends BasePayment implements MerchantApplicationPaymentInterface
{

    private const API_URL = 'https://payapi.yunfastpay.com';
    private const PAY_TRANS_CODE = 'AT_TRANS_PAY';
    const TRANS_CODE_SETTLE_ORDER_APPLY = 'SETTLE_ORDER_APPLY';

    public function __construct($mall_id)
    {
        // [修改] 构造函数与微信支付对齐，加载全局配置作为默认值
        $this->mall_id = $mall_id;
        $payment = OptionHelper::get('payments', $mall_id);

        if (!$payment || !isset($payment[self::class])) {
            throw new \InvalidArgumentException('快付支付宝支付[全局配置]未找到');
        }
        $this->config = $payment[self::class];
        if (!$this->config['status']) {
            throw new \InvalidArgumentException('快付支付宝支付[全局配置]未开启');
        }
        $this->logo = $this->config['logo'] ?? '';
        $this->name = $this->config['name'] ?? '支付宝';
        $this->desc = $this->config['desc'] ?? '';
        $this->payment_type = self::class;
    }

    /**
     * 把收款主体切换为商户的进件应用（MerchantApplicationPaymentInterface）。
     * 派生出的配置直接作为 placeOrder() 的第二形参 $mchConfig 传入。
     */
    public function withMerchantApplication(array $config, string $applicationId): array
    {
        if (trim($applicationId) === '') {
            throw new \InvalidArgumentException('该商户尚未完成分账进件，无法作为收款主体');
        }
        $config['applicationId'] = $applicationId;
        return $config;
    }

    /**
     * 统一下单
     * @param array $orderData
     * @param array|null $mchConfig (可选) 传入商户独立配置
     * @return mixed
     * @throws \Exception
     */
    public function placeOrder($orderData, $mchConfig = null)
    {

        // 步骤 1: 决定使用哪个配置。如果传入了 $mchConfig，则用它；否则，用类自身的 $this->config (全局配置)。
        $activeConfig = $mchConfig ?? $this->config;
        // 步骤 2: 验证最终生效的配置
        $this->_validateConfig($activeConfig);

        // 步骤 3: 使用生效的配置 ($activeConfig) 准备业务参数
        $bizContent = [
            'applicationId' => $activeConfig['applicationId'],
            'sceneCode' => 'ALI_SCAN', // [核心] 使用支付公司指定的场景码
            'merOrderNo' => $orderData['out_trade_no'],
            'orderAmount' => (string) sprintf("%.2f", $orderData['pay_price']),
            'merOrderTime' => date('YmdHis'),
            'orderTitle' => mb_substr($orderData['body'], 0, 12),
            'orderDesc' => $orderData['body'],
            'notifyUrl' => ServerHelper::getHost() . '/notify/fastpay.php',
            'clientIp' => Yii::$app->request->userIP,
            'splitType' => $orderData['splitType'] ?? 'NO'
        ];
        // [修改] 保存支付方式和所使用的具体配置到订单记录
        CommonPayOrder::updateAll(['payment' => self::class, 'payment_config' => Json::encode($activeConfig)], ['id' => $orderData['pay_order_id']]);

        // 过滤掉业务参数中的空值，以匹配签名逻辑
        $bizContent = array_filter($bizContent, function ($item) {
            return !$this->_isEmpty($item);
        });

        // [修改] 调用请求时传入生效的配置
        $result = $this->_request(self::PAY_TRANS_CODE, $bizContent, $activeConfig);
        $resData = $result['data'];

        if ($resData['status'] !== 'SUCCESS' && $resData['status'] !== 'DOING') {
            throw new \Exception('支付宝SCAN支付下单失败：' . $resData['desc']);
        }

        // 返回支付宝支付链接等信息，由前端处理
        return $resData;
    }

    /**
     * [新增] 通用的配置校验方法
     * @param array $config
     */
    private function _validateConfig(array $config)
    {
        $requiredKeys = ['applicationId', 'platformCode', 'merchantPrivateKey', 'status'];
        foreach ($requiredKeys as $key) {
            if ($this->_isEmpty($config[$key] ?? null)) {
                throw new \InvalidArgumentException("支付配置缺少关键参数：{$key}");
            }
        }
        if (empty($config['status'])) {
            throw new \InvalidArgumentException('该支付配置未开启');
        }
    }

    /**
     * 封装统一请求
     * @param string $transCode
     * @param array $bizContent
     * @param array $config [修改] 接收具体配置
     * @return mixed
     * @throws \Exception
     */
    private function _request($transCode, $bizContent, array $config)
    {
        // 准备顶层参数
        $reqParams = [
            'transCode' => $transCode,
            'platformCode' => $config['platformCode'], // [修改] 使用传入配置的 platformCode
            'timeStamp' => (string) time(),
            'randStr' => Yii::$app->security->generateRandomString(32),
            'signType' => 'RSA2',
            'data' => $bizContent,
        ];

        // [修改] 生成签名时传入具体配置
        $reqParams['sign'] = $this->_generateSign($reqParams, $config);

        // 发送 cURL 请求
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::API_URL);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, Json::encode($reqParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error)
            throw new \RuntimeException('请求失败: ' . $error);   // 网络层失败=请求可能已到网关、结果未知;用 RuntimeException 与「网关明确拒绝」区分(RefundTask 据此决定能否换号重发)
        $result = Json::decode($response);

        if (!isset($result['code']) || $result['code'] !== '0000') {
            throw new \Exception($result['message'] ?? '接口返回错误');
        }
        return $result;
    }

    // ======================= 签名与验签逻辑 (与微信支付完全一致，无需修改) =======================

    private function _isEmpty($value): bool
    {
        if (!isset($value))
            return true;
        if (is_string($value) && trim($value) === "")
            return true;
        if (is_array($value) && empty($value))
            return true;
        return false;
    }

    private static function sortArray(array &$array)
    {
        ksort($array);
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                self::sortArray($array[$key]);
            }
        }
    }

    private function _getStringToBeSigned(array $params): string
    {
        self::sortArray($params);
        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                if (false === $this->_isEmpty($v)) {
                    $v_filtered = array_filter($v, function ($item) {
                        return !$this->_isEmpty($item);
                    });
                    $json = json_encode($v_filtered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if ($i > 0)
                        $stringToBeSigned .= "&";
                    $stringToBeSigned .= "$k" . "=" . "$json";
                    $i++;
                }
            } else {
                if (false === $this->_isEmpty($v)) {
                    if ($i > 0)
                        $stringToBeSigned .= "&";
                    $stringToBeSigned .= "$k" . "=" . "$v";
                    $i++;
                }
            }
        }
        return $stringToBeSigned;
    }

    /**
     * [修改] 生成签名，接收具体配置以获取私钥
     */
    private function _generateSign($params, array $config)
    {
        $stringA = $this->_getStringToBeSigned($params);

        $privateKey = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap($config['merchantPrivateKey'], 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";

        $pkey = openssl_pkey_get_private($privateKey);
        if ($pkey === false) {
            throw new \Exception('商户私钥格式不正确');
        }

        $signature = '';
        openssl_sign($stringA, $signature, $pkey, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    /**
     * [修改] 验证签名，需要加载正确的平台公钥
     * 注意：回调验签时，需要根据回调报文中的商户订单号，查出原订单，再获取其 payment_config 来验签
     */
    /**
     * 对外开放:退款回调也要验签(见 FastController::actionPay 的退款分支),
     * 而那里没有支付类实例的上下文,只能由控制器实例化后调用。
     * 算法与其余三个快付类逐字一致,不另写一份免得两处实现漂移。
     */
    public function _verifySign($jsonDataString, $signature, $platformPublicKey)
    {
        $params = Json::decode($jsonDataString);
        unset($params['sign']);
        $stringToVerify = $this->_getStringToBeSigned($params);

        $publicKey = "-----BEGIN PUBLIC KEY-----\n" .
            wordwrap($platformPublicKey, 64, "\n", true) .
            "\n-----END PUBLIC KEY-----";

        $pkey = openssl_pkey_get_public($publicKey);
        if ($pkey === false) {
            throw new \Exception('快付平台公钥格式不正确');
        }
        $result = openssl_verify($stringToVerify, base64_decode($signature), $pkey, OPENSSL_ALGO_SHA256);
        return $result === 1;
    }

    /**
     * 订单退款
     */
    protected function doRefund($order)
    {
        // 三级匹配(当前号→历史号→order_no),详见 CommonPayOrder::findByTradeNo
        $commonPayOrder = CommonPayOrder::findByTradeNo($order['out_trade_no'], ['mall_id' => $this->mall_id]);
        if (!$commonPayOrder) {
            throw new \Exception('原支付订单记录不存在');
        }
        // [核心修改] 从订单记录中加载当时使用的支付配置
        if (!$commonPayOrder->payment_config) {
            throw new \Exception('支付配置不存在');
        }
        $pay_config = Json::decode($commonPayOrder->payment_config);

        $transCode = 'NORMAL_ORDER_REFUND';
        // 必须与下单时的 merOrderNo 一致(=out_trade_no,支付成功时已写回付款那次的号);老单没有该字段,回落 order_no
        $oglNo = $commonPayOrder->out_trade_no ?: $commonPayOrder->order_no;
        $data = [
            'applicationId' => $pay_config['applicationId'],
            'oglMerOrderNo' => $oglNo,
            'oglOrderDate' => $commonPayOrder->tradeNoDate($oglNo),   // 那次**发起**的日期,不是支付单创建日(见 tradeNoDate)
            'oglTransCode' => self::PAY_TRANS_CODE,
            'merOrderNo' => $order['refund_order_no'],
            'merOrderTime' => date('YmdHis'),
            'refundAmount' => (string) sprintf("%.2f", $order['refund_price']),
            'notifyUrl' => ServerHelper::getHost() . '/refund/fastpay.php', // [修改] 与微信支付保持一致
            'remark' => '用户申请退款',
        ];

        // [修改] 使用加载的配置进行请求
        $result = $this->_request($transCode, $data, $pay_config);
        $resData = $result['data'];

        if ($resData['status'] === 'FAIL') {
            throw new \Exception('退款申请失败：' . $resData['desc']);
        }
        return ['code' => 0, 'msg' => '退款申请已受理，等待处理!'];
    }

    /**
     * 异步回调处理
     */
    public function handleCallback($callbackData)
    {
        try {
            // [核心修改] 回调验签时必须找到原订单，使用其配置中的平台公钥
            $merOrderNo = $callbackData['data']['merOrderNo'] ?? null;
            if (!$merOrderNo) {
                throw new \Exception('回调数据缺少商户订单号');
            }

            // 根据支付或退款订单号查找记录
            if ($callbackData['transCode'] == self::PAY_TRANS_CODE) {
                $order = CommonPayOrder::findByTradeNo($merOrderNo);
            } else {
                $order = CommonPayOrder::findOne(['refund_order_no' => $merOrderNo]);
            }

            if (!$order || !$order->payment_config) {
                throw new \Exception('无法找到原始订单或其支付配置');
            }
            $pay_config = Json::decode($order->payment_config);

            if (!$this->_verifySign(Json::encode($callbackData), $callbackData['sign'], $pay_config['platformPublicKey'])) {
                throw new \Exception('签名验证失败');
            }

            $data = $callbackData['data'];
            if ($callbackData['transCode'] == self::PAY_TRANS_CODE) { // 支付回调
                if ($data['status'] == 'SUCCESS') {
                    if ($order && !$order->is_pay) {
                        $transactionId = $data['atuOrderNo'] ?? ($data['channelFlowNo'] ?? '');
                        // 第三参:把**付款的那个号**写回 out_trade_no。下面 queryOrder/doRefund
                        // 问网关用的都是 out_trade_no,不写回就会拿「最后一次发起」的号去查/退,
                        // 而钱付在更早的号上(57289/57311 都是这种形态)→ 网关查无此单、退不出去。
                        // 第四参:回调实付金额,paySuccess 内与 pay_price 比对,不等拒绝并留痕
                        $order->paySuccess($transactionId, static::class, $merOrderNo, isset($data['transAmount']) ? (string) $data['transAmount'] : null);
                    }
                }
            }
            \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            return ['respCode' => 'SUCCESS', 'respDesc' => '成功'];
        } catch (\Exception $e) {
            Yii::error('快付支付宝回调处理异常: ' . $e->getMessage());
            \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            return ['respCode' => 'FAIL', 'respDesc' => $e->getMessage()];
        }
    }

    /**
     * 订单查询
     */
    public function queryOrder($queryData)
    {
        $commonPayOrder = CommonPayOrder::findByTradeNo($queryData['out_trade_no'], ['mall_id' => $this->mall_id]);
        if (!$commonPayOrder) {
            throw new \Exception('原支付订单记录不存在');
        }
        // [核心修改] 从订单记录中加载当时使用的支付配置
        if (!$commonPayOrder->payment_config) {
            throw new \Exception('支付配置不存在');
        }
        $pay_config = Json::decode($commonPayOrder->payment_config);

        $transCode = 'NORMAL_ORDER_QUERY';
        // 问网关的必须是**调用方指定的那个号**,不是订单当前号:掉单补偿会逐个历史号来查,
        // 原来这里一律换成 $commonPayOrder->out_trade_no,遍历多少个号问的都是最后那个 → 空转。
        // 老单没有商户单号时回落 order_no(改造前网关拿的就是它)。
        $oglNo = ($queryData['out_trade_no'] ?? '') ?: ($commonPayOrder->out_trade_no ?: $commonPayOrder->order_no);
        $data = [
            'applicationId' => $pay_config['applicationId'],
            'oglMerOrderNo' => $oglNo,
            'oglOrderDate' => $commonPayOrder->tradeNoDate($oglNo),   // 那次**发起**的日期,不是支付单创建日(见 tradeNoDate)
            'oglTransCode' => self::PAY_TRANS_CODE,
        ];

        try {
            // [修改] 使用加载的配置进行请求
            $result = $this->_request($transCode, $data, $pay_config);
            if ($result['data']['status'] == 'SUCCESS') {
                return [
                    'code' => \app\core\ApiCode::CODE_SUCCESS,
                    'msg' => '已支付',
                    // 把平台流水号带出去:掉单补偿要拿它写 transaction_id,否则那批订单
                    // 补回来是没有支付凭证的(对账、退款都缺依据)。取法与 handleCallback 同源。
                    'transaction_id' => $result['data']['atuOrderNo'] ?? ($result['data']['channelFlowNo'] ?? ''),
                ];
            }
        } catch (\Exception $e) {
            Yii::error('快付支付宝订单查询失败: ' . $e->getMessage());
        }
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
        return [
            ConstantHelper::PLATFORM_H5,
            ConstantHelper::PLATFORM_APP
        ];
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function applySettleOrder($params, $applicationId = null)
    {
        if (!$applicationId) {
            $applicationId = $this->config['applicationId'];
        }
        // 使用类自身的全局配置
        $this->_validateConfig($this->config);
        // 构造业务参数
        $bizContent = [
            'applicationId' => $applicationId,
            'merOrderNo' => 'SETTLE' . $params['order_no'],
            'merOrderTime' => date('YmdHis', $params['time_at']),
            'settleAmount' => (string) sprintf("%.2f", $params['amount']),
            'settleType' => 'MER',
            // 新增一个回调地址用于接收结算通知
            // 前导斜杠不能少:getHost() 会 rtrim 掉尾部 '/',少了就拼成 https://域名settle/fastpay.php,
            // 结算通知永远送不到(同文件其它回调与 FastPayQuickPayment 都是带斜杠的写法)。
            'notifyUrl' => ServerHelper::getHost() . '/settle/fastpay.php',
            'remark' => '订单结算,订单号:' . $params['order_no'],
            'extendOrderNo' => $params['order_no'],
        ];
        Yii::warning($bizContent);
        // 如果有附加数据，则添加
        if (!empty($params['reserved'])) {
            $bizContent['reserved'] = $params['reserved'];
        }
        $bizContent = array_filter($bizContent, function ($item) {
            return !$this->_isEmpty($item);
        });
        // 发起请求
        $result = $this->_request(self::TRANS_CODE_SETTLE_ORDER_APPLY, $bizContent, $this->config);
        $resData = $result['data'];
        Yii::warning($resData);
        // 检查返回状态
        if ($resData['status'] === 'FAIL') {
            throw new \Exception('结算申请失败：' . ($resData['desc'] ?? '未知错误'));
        }
        // 返回受理成功的结果
        return ['code' => 0, 'msg' => '结算申请已受理', 'data' => $resData];
    }
}