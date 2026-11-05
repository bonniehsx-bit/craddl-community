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

use app\helpers\CacheHelper;
use app\helpers\ConstantHelper;
use app\helpers\OptionHelper;
use app\helpers\ServerHelper;
use app\models\CommonPayOrder;
use Yii;
use yii\helpers\Json;
use app\payments\contracts\MerchantApplicationPaymentInterface;

/**
 * [最终修复版] 快付(FastPay) 微信小程序网关 (公众号/微信内浏览器)
 *
 * @desc 修复了签名生成逻辑，严格遵循官方 Demo 对空值和参数的拼接规则。
 */
class FastPayMpwxPayment extends BasePayment implements MerchantApplicationPaymentInterface
{

    private const API_URL = 'https://payapi.yunfastpay.com';
    private const PAY_TRANS_CODE = 'AT_TRANS_PAY';

    public function __construct($mall_id)
    {
        // [恢复] 构造函数改回原样，加载全局配置作为默认值
        $this->mall_id = $mall_id;
        $payment = OptionHelper::get('payments', $mall_id);

        if (!$payment || !isset($payment[self::class])) {
            throw new \InvalidArgumentException('快付微信小程序[全局配置]未找到');
        }
        $this->config = $payment[self::class];
        if (!$this->config['status']) {
            throw new \InvalidArgumentException('快付微信小程序[全局配置]未开启');
        }
        $this->logo = $this->config['logo'] ?? '';
        $this->name = $this->config['name'] ?? '微信支付';
        $this->desc = $this->config['desc'] ?? '';
        $this->payment_type = self::class;
    }

    /**
     * [新增] 商户独立配置校验方法
     * @param array $config
     */
    private function _validateMchConfig(array $config)
    {
        // 定义一套商户配置中必须存在的关键字段
        $requiredKeys = ['applicationId', 'platformCode', 'merchantPrivateKey', 'appid', 'status'];
        // 注:platformPublicKey 有意不列入此处 —— 它只在回调验签时才用得到,
        // 下单只需私钥。放进来会让「配了私钥、忘了公钥」的商城连下单都做不了,
        // 而那种情况下单其实是成功的(只是回调验不过)。公钥的缺失在 handleCallback
        // 里单独判,给的是「未包含平台公钥,无法验签」这种能直接照着去补配置的话。
        foreach ($requiredKeys as $key) {
            // 使用您自己的 _isEmpty 方法来保持逻辑一致性
            if ($this->_isEmpty($config[$key] ?? null)) {
                throw new \InvalidArgumentException("商户支付配置缺少关键参数：{$key}");
            }
        }
        if (empty($config['status'])) {
            throw new \InvalidArgumentException('该商户的快付微信小程序未开启');
        }
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
     */
    public function placeOrder($orderData, $mchConfig = null)
    {
        // 步骤 1: 判断当前是否为APP平台
        $platform = Yii::$app->platform;
        if ($platform === ConstantHelper::PLATFORM_APP) {
            // 步骤 2: 获取APP的全局配置
            $wxappConfig = OptionHelper::get('platform_wxapp', Yii::$app->mall->id);
            // 步骤 3: 判断APP是否配置了“借用小程序支付” (use_payment == 2)
            if (!empty($wxappConfig) && isset($wxappConfig['use_payment']) && $wxappConfig['use_payment'] == '2') {

                // 步骤 4: 校验必要的配置是否存在
                if (empty($wxappConfig['mp_id'])) {
                    throw new \Exception('APP后台未配置“借用支付的小程序原始ID(mp_id)”');
                }
                if (empty($wxappConfig['wxapp_pay_mpwx_appid'])) {
                    throw new \Exception('APP后台未配置“支付借用小程序appid(wxapp_pay_mpwx_appid)”');
                }
                // 步骤 5: 构建APP拉起小程序所需的数据结构
                // 步骤 1: 生成一个唯一、一次性的 key
                $key = 'mp_pay_key_' . Yii::$app->security->generateRandomString(32);

                // 步骤 2: 准备要缓存的数据
                $cacheData = [
                    'order_no' => $orderData['out_trade_no'],
                    'payment_gateway' => self::class, // 将支付网关类名也存入，更具扩展性
                ];
                // 步骤 3: 将数据存入缓存，有效期5分钟
                CacheHelper::set($key, $cacheData, 3600);
                // 步骤 4: 构建小程序路径，参数为 key
                $path = 'pages/mp-pay/mp-pay?key=' . urlencode($key);
                $appCallMpData = [
                    'is_app_call_mp' => 1, // 关键信号：告诉前端这是APP拉起小程序模式
                    'is_pay' => 0,
                    'app_call_mp_data' => [
                        'userName' => $wxappConfig['mp_id'], // 小程序原始ID
                        'path' => $path, // 要跳转的小程序页面路径及参数
                        'miniprogramType' => (int) ($wxappConfig['mp_type'] ?? '0'), // 小程序版本（0:正式版, 1:开发版, 2:体验版）
                    ],
                ];
                // 步骤 6: 直接返回这个特定结构，中断后续的支付请求流程
                return $appCallMpData;
            }
        }


        // 步骤 1: 决定使用哪个配置。如果传入了 $mchConfig，则用它；否则，用类自身的 $this->config (全局配置)。
        $activeConfig = $mchConfig ?? $this->config;

        // 步骤 2: 验证最终生效的配置
        $this->_validateConfig($activeConfig);

        // [新增] 校验 openid 是否存在
        if (empty($orderData['openid'])) {
            throw new \Exception('微信JSAPI支付下单失败：缺少必要的 openid 参数');
        }
        // 步骤 3: 使用生效的配置 ($activeConfig) 准备业务参数
        $bizContent = [
            'applicationId' => $activeConfig['applicationId'],
            'sceneCode' => 'WX_LITE',
            'merOrderNo' => $orderData['out_trade_no'],
            'orderAmount' => (string) sprintf("%.2f", $orderData['pay_price']),
            'merOrderTime' => date('YmdHis'),
            'orderTitle' => mb_strcut($orderData['body'], 0, 12, 'UTF-8'),
            'orderDesc' => $orderData['body'],
            'notifyUrl' => ServerHelper::getHost() . '/notify/fastpay.php',
            'appid' => $activeConfig['appid'],
            'userid' => $orderData['openid'],
        ];
        CommonPayOrder::updateAll(['payment' => self::class, 'pay_openid' => $orderData['openid'], 'payment_config' => Json::encode($activeConfig)], ['id' => $orderData['pay_order_id']]);
        // 过滤掉业务参数中的空值，以匹配签名逻辑
        $bizContent = array_filter($bizContent, function ($item) {
            return !$this->_isEmpty($item);
        });
        $result = $this->_request(self::PAY_TRANS_CODE, $bizContent, $activeConfig);
        $resData = $result['data'];
        if ($resData['status'] !== 'SUCCESS' && $resData['status'] !== 'DOING') {
            throw new \Exception('微信小程序下单失败：' . $resData['desc']);
        }
        $payInfo = Json::decode($resData['jsApi']);
        $payInfo['is_pay'] = 0;
        $payInfo['payment'] = self::class;
        return $payInfo;
    }

    /**
     * [修改] 通用的配置校验方法
     * @param array $config
     */
    private function _validateConfig(array $config)
    {
        $requiredKeys = ['applicationId', 'platformCode', 'merchantPrivateKey', 'appid', 'status'];
        // 注:platformPublicKey 有意不列入此处 —— 它只在回调验签时才用得到,
        // 下单只需私钥。放进来会让「配了私钥、忘了公钥」的商城连下单都做不了,
        // 而那种情况下单其实是成功的(只是回调验不过)。公钥的缺失在 handleCallback
        // 里单独判,给的是「未包含平台公钥,无法验签」这种能直接照着去补配置的话。
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
     */
    private function _request($transCode, $bizContent, array $config)
    {
        // 准备顶层参数
        $reqParams = [
            'transCode' => $transCode,
            'platformCode' => $config['platformCode'],
            'timeStamp' => (string) time(),
            'randStr' => Yii::$app->security->generateRandomString(32),
            'signType' => 'RSA2',
            'data' => $bizContent, // data 的值是业务参数数组
        ];

        // 生成签名
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

    // ======================= [最终核心修复] 开始 =======================

    /**
     * [新增] 严格复刻官方 Demo 的 isEmpty 逻辑
     */
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

    /**
     * 对数组进行递归排序
     * 该方法用于对数组进行键名升序排序，并递归地对多维数组中的每个子数组进行排序
     *
     * @param array $array 待排序的数组，通过引用传递，意味着内部修改将反映到函数外部
     */
    private static function sortArray(array &$array)
    {
        ksort($array);
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                self::sortArray($array[$key]);
            }
        }
    }



    /**
     * [最终重写] 严格复刻官方 Demo 的 getStringToBeSigned 逻辑
     */
    /**
     * [最终重写] 严格复刻官方 Demo 的 getStringToBeSigned 逻辑
     */
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
     * [最终重写] 生成签名
     */
    /**
     * @param array $config **生效**配置 —— 走商户进件时是商户那套。
     *   原实现这里硬读 $this->config,而报文用的是 _request 传进来的 $config:
     *   商户进件订单于是变成"报文用商户配置、签名用全局私钥",平台按报文里
     *   applicationId 对应的公钥验签,必然「验证签名失败」拒单。
     *   同门 FastPayAliJsPayment / FastPayQuickPayment 早已是这个签名,本类跟上。
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
     * [最终重写] 验证签名 (响应验签时使用)
     * @note 官方 Demo 的响应验签逻辑稍有不同，这里根据 SignUtil 实现
     */
    /**
     * @param string $platformPublicKey 平台公钥。**必须由调用方从订单 payment_config
     *   快照里取**,而不是读全局配置 —— 下单时存进快照的才是这笔单实际生效的那套。
     */
    /**
     * 对外开放:退款回调也要验签(见 FastController::actionPay 的退款分支),
     * 而那里没有支付类实例的上下文,只能由控制器实例化后调用。
     * 算法与其余三个快付类逐字一致,不另写一份免得两处实现漂移。
     */
    public function _verifySign($jsonDataString, $signature, $platformPublicKey)
    {
        // 官方验签工具类中，待验签字符串的生成逻辑
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
     * @note 逻辑与小程序版完全相同，直接复用。
     */
    protected function doRefund($order)
    {
        // 三级匹配(当前号→历史号→order_no),详见 CommonPayOrder::findByTradeNo
        $commonPayOrder = CommonPayOrder::findByTradeNo($order['out_trade_no'], ['mall_id' => $this->mall_id]);
        if (!$commonPayOrder) {
            throw new \Exception('原支付订单记录不存在');
        }
        if (!$commonPayOrder->payment_config) {
            throw new \Exception('支付配置不存在');
        }
        $pay_config = Json::decode($commonPayOrder->payment_config);
        // 使用局部变量，避免覆盖实例 config（并发安全）
        $transCode = 'NORMAL_ORDER_REFUND';
        // 必须与下单时的 merOrderNo 一致(=out_trade_no,支付成功时已写回付款那次的号);老单没有该字段,回落 order_no
        $oglNo = $commonPayOrder->out_trade_no ?: $commonPayOrder->order_no;
        $data = [
            'applicationId' => $pay_config['applicationId'],
            'oglMerOrderNo' => $oglNo,
            'oglOrderDate' => $commonPayOrder->tradeNoDate($oglNo),   // 那次发起的日期,不是支付单创建日(见 CommonPayOrder::tradeNoDate)
            'oglTransCode' => self::PAY_TRANS_CODE,
            'merOrderNo' => $order['refund_order_no'],
            'merOrderTime' => date('YmdHis'),
            'refundAmount' => (string) sprintf("%.2f", $order['refund_price']),
            'notifyUrl' => ServerHelper::getHost() . '/refund/fastpay.php',
            'remark' => '用户申请退款',
        ];
        $result = $this->_request($transCode, $data, $pay_config);
        $resData = $result['data'];
        if ($resData['status'] === 'FAIL') {
            throw new \Exception('退款申请失败：' . $resData['desc']);
        }
        return ['code' => 0, 'msg' => '退款申请已受理，等待处理!'];
    }

    /**
     * 异步回调处理
     * @note 逻辑与小程序版完全相同，直接复用。
     */
    public function handleCallback($callbackData)
    {
        try {
            // 验签公钥必须取自**这笔订单下单时的配置快照**:走商户进件的单,
            // 平台是用该商户应用的密钥对签的,拿全局公钥去验必然不过。
            $merOrderNo = $callbackData['data']['merOrderNo'] ?? null;
            if (!$merOrderNo) {
                throw new \Exception('回调数据缺少商户订单号');
            }
            // findByTradeNo 已含「当前号→历史号→order_no」三级;退款单号是另一套编号,单独兜
            $payOrder = CommonPayOrder::findByTradeNo($merOrderNo)
                ?: CommonPayOrder::findOne(['refund_order_no' => $merOrderNo]);
            if (!$payOrder || !$payOrder->payment_config) {
                throw new \Exception('无法找到原始订单或其支付配置');
            }
            $payConfig = Json::decode($payOrder->payment_config);
            if (empty($payConfig['platformPublicKey'])) {
                throw new \Exception('该订单的支付配置未包含平台公钥,无法验签');
            }
            if (!$this->_verifySign(Json::encode($callbackData), $callbackData['sign'] ?? '', $payConfig['platformPublicKey'])) {
                Yii::error('...........................签名验证失败............................');
                throw new \Exception('签名验证失败');
            }
            Yii::warning('............................签名验证成功............................');
            $data = $callbackData['data'];
            if ($callbackData['transCode'] == self::PAY_TRANS_CODE) { // 支付回调
                if ($data['status'] == 'SUCCESS') {
                    $order = CommonPayOrder::findByTradeNo($data['merOrderNo']);
                    if ($order && !$order->is_pay) {
                        $transactionId = $data['atuOrderNo'] ?? ($data['channelFlowNo'] ?? '');
                        // 第三参:把付款的那个号写回 out_trade_no,queryOrder/doRefund 才会问对号(详见 FastPayAliJsPayment 同处注释)
                        // 第四参:回调实付金额,paySuccess 内与 pay_price 比对,不等拒绝并留痕
                        $order->paySuccess($transactionId, static::class, $data['merOrderNo'], isset($data['transAmount']) ? (string) $data['transAmount'] : null);
                    }
                }
            }
            \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            return ['respCode' => 'SUCCESS', 'respDesc' => '成功'];
        } catch (\Exception $e) {
            Yii::error('微信小程序回调处理异常: ' . $e->getMessage());
            \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            return ['respCode' => 'FAIL', 'respDesc' => $e->getMessage()];
        }
    }

    /**
     * 订单查询
     * @note 逻辑与小程序版完全相同，直接复用。
     */
    public function queryOrder($queryData)
    {

        $order_no = $queryData['out_trade_no'];
        $commonPayOrder = CommonPayOrder::findByTradeNo($order_no, ['mall_id' => $this->mall_id]);
        if (!$commonPayOrder) {
            throw new \Exception('原支付订单记录不存在');
        }
        $transCode = 'NORMAL_ORDER_QUERY';
        // 问调用方指定的号,不是订单当前号(掉单补偿逐个历史号来查;详见 FastPayAliJsPayment::queryOrder)
        $oglNo = ($queryData['out_trade_no'] ?? '') ?: ($commonPayOrder->out_trade_no ?: $commonPayOrder->order_no);
        $data = [
            'applicationId' => $this->config['applicationId'],
            'oglMerOrderNo' => $oglNo,
            'oglOrderDate' => $commonPayOrder->tradeNoDate($oglNo),   // 那次发起的日期,不是支付单创建日(见 CommonPayOrder::tradeNoDate)
            'oglTransCode' => self::PAY_TRANS_CODE,
        ];
        try {
            $result = $this->_request($transCode, $data, $this->config);
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
            Yii::error('微信小程序订单查询失败: ' . $e->getMessage());
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
        // [修改] 声明此支付方式适用于 H5 平台
        return [
            ConstantHelper::PLATFORM_MPWX,
            ConstantHelper::PLATFORM_APP
        ];
    }

    public function getConfig()
    {

        return $this->config;
    }

    public function applySettleOrder($settleOrderData, $extra = null)
    {
        // TODO: Implement applySettleOrder() method.
    }
}