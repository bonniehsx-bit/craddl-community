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
use app\payments\contracts\QuickPaymentInterface;
use Yii;
use yii\helpers\Json;

/**
 * 快付(FastPay) 快捷支付(银行卡)网关
 *
 * 下单/退款/回调/查询走 BasePayment 契约;
 * 绑卡/短信确认等"银行卡特有"动作走 QuickPaymentInterface 契约,
 * 未来新增汇付/杉德/连连等第三方快捷支付网关只需同样 implements QuickPaymentInterface,
 * 前端与 bankcard Form 层零改动。
 */
class FastPayQuickPayment extends BasePayment implements QuickPaymentInterface
{
    private const API_URL = 'https://payapi.yunfastpay.com';

    // 交易码常量定义
    private const TRANS_CODE_CARD_APPLY  = 'KJ_CARD_APPLY';  // 绑卡申请
    private const TRANS_CODE_CARD_VERIFY = 'KJ_CARD_VERIFY'; // 绑卡确认
    private const TRANS_CODE_CARD_UNBIND = 'KJ_CARD_UNBIND'; // 解绑
    private const TRANS_CODE_PAY_APPLY   = 'KJ_TRANS_APPLY'; // 支付申请 (对应 placeOrder)
    private const TRANS_CODE_PAY_VERIFY  = 'KJ_TRANS_VERIFY';// 支付确认
    private const TRANS_CODE_REFUND      = 'NORMAL_ORDER_REFUND'; // 退款
    private const TRANS_CODE_QUERY       = 'NORMAL_ORDER_QUERY';  // 查询
    private const TRANS_CODE_SETTLE      = 'SETTLE_ORDER_APPLY';  // 结算

    public function __construct($mall_id)
    {
        $this->mall_id = $mall_id;
        $payment = OptionHelper::get('payments', $mall_id);

        if (!$payment || !isset($payment[self::class])) {
            throw new \InvalidArgumentException('快付快捷支付[全局配置]未找到');
        }
        $this->config = $payment[self::class];
        if (!$this->config['status']) {
            throw new \InvalidArgumentException('快付快捷支付[全局配置]未开启');
        }

        $this->logo = $this->config['logo'] ?? '';
        $this->name = $this->config['name'] ?? '银行卡快捷支付';
        $this->desc = $this->config['desc'] ?? '';
        $this->payment_type = self::class;
    }

    // =========================================================================
    //                        实现 BasePayment 抽象方法
    // =========================================================================

    /**
     * 下单 —— 对应快捷支付的 KJ_TRANS_APPLY
     *
     * @param array $orderData 必须包含 user_id, bind_card_id, order_no, pay_price, body
     * @return mixed
     * @throws \Exception
     */
    public function placeOrder($orderData)
    {
        $this->_validateConfig($this->config);

        if (empty($orderData['bind_card_id'])) {
            throw new \Exception('快捷支付下单失败:缺少 bind_card_id (未选择银行卡)');
        }
        if (empty($orderData['user_id'])) {
            throw new \Exception('快捷支付下单失败:缺少 user_id');
        }

        $bizContent = [
            'applicationId' => $this->config['applicationId'],
            'userId'        => $orderData['user_id'],
            'bindCardId'    => $orderData['bind_card_id'],
            'merOrderNo'    => $orderData['order_no'],
            'orderAmount'   => (string)sprintf("%.2f", $orderData['pay_price']),
            'merOrderTime'  => date('YmdHis'),
            'orderTitle'    => mb_substr($orderData['body'], 0, 12),
            'orderDesc'     => $orderData['body'],
            // 复用通用快付回调入口, FastController::actionPay 会按 $order->payment 反射到本类 handleCallback
            'notifyUrl'     => ServerHelper::getHost() . '/notify/fastpay.php',
            'splitType'     => $orderData['splitType'] ?? 'NO'
        ];

        // 记录支付配置快照 (关键步骤, 用于后续验签和退款)
        CommonPayOrder::updateAll([
            'payment'        => self::class,
            'payment_config' => Json::encode($this->config)
        ], ['order_no' => $orderData['order_no']]);

        $result = $this->_request(self::TRANS_CODE_PAY_APPLY, $bizContent, $this->config);
        return $result['data'];
    }

    public function applySettleOrder($settleOrderData, $applicationId = null)
    {
        if (!$applicationId) {
            $applicationId = $this->config['applicationId'];
        }
        $this->_validateConfig($this->config);

        $bizContent = [
            'applicationId' => $applicationId,
            'merOrderNo'    => 'SETTLE_KJ_' . $settleOrderData['order_no'],
            'merOrderTime'  => date('YmdHis', $settleOrderData['time_at']),
            'settleAmount'  => (string)sprintf("%.2f", $settleOrderData['amount']),
            'settleType'    => 'MER',
            'notifyUrl'     => ServerHelper::getHost() . '/settle/fastpay.php',
            'remark'        => '快捷支付结算,订单号:' . $settleOrderData['order_no'],
            'extendOrderNo' => $settleOrderData['order_no'],
        ];

        $result = $this->_request(self::TRANS_CODE_SETTLE, $bizContent, $this->config);
        $resData = $result['data'];

        if (($resData['status'] ?? '') === 'FAIL') {
            throw new \Exception('结算申请失败:' . ($resData['desc'] ?? '未知错误'));
        }

        return ['code' => 0, 'msg' => '结算申请已受理', 'data' => $resData];
    }

    /**
     * 退款具体实现 —— 金额校验由 BasePayment::refundOrder 模板方法统一处理
     */
    protected function doRefund(array $refundData)
    {
        $commonPayOrder = CommonPayOrder::findOne(['order_no' => $refundData['order_no'], 'mall_id' => $this->mall_id]);
        if (!$commonPayOrder || !$commonPayOrder->payment_config) {
            throw new \Exception('原支付订单记录或配置不存在');
        }
        $pay_config = Json::decode($commonPayOrder->payment_config);

        $data = [
            'applicationId' => $pay_config['applicationId'],
            'oglMerOrderNo' => $commonPayOrder->order_no,
            'oglOrderDate'  => date('Ymd', strtotime($commonPayOrder->created_time)),
            'oglTransCode'  => self::TRANS_CODE_PAY_APPLY,
            'merOrderNo'    => $refundData['refund_order_no'],
            'merOrderTime'  => date('YmdHis'),
            'refundAmount'  => (string)sprintf("%.2f", $refundData['refund_price']),
            'notifyUrl'     => ServerHelper::getHost() . '/refund/fastpay.php',
            'remark'        => '快捷支付退款',
        ];

        $result = $this->_request(self::TRANS_CODE_REFUND, $data, $pay_config);
        $resData = $result['data'];

        if (($resData['status'] ?? '') === 'FAIL') {
            throw new \Exception('退款申请失败:' . ($resData['desc'] ?? '未知错误'));
        }

        return ['code' => 0, 'msg' => '退款申请已受理'];
    }

    public function handleCallback($callbackData)
    {
        try {
            $merOrderNo = $callbackData['data']['merOrderNo'] ?? null;
            if (!$merOrderNo) throw new \Exception('缺少商户订单号');

            $transCode = $callbackData['transCode'] ?? '';

            if (in_array($transCode, [self::TRANS_CODE_PAY_APPLY, self::TRANS_CODE_PAY_VERIFY])) {
                $order = CommonPayOrder::findOne(['order_no' => $merOrderNo]);
            } else {
                $order = CommonPayOrder::findOne(['refund_order_no' => $merOrderNo]);
            }

            if (!$order || !$order->payment_config) {
                throw new \Exception('订单不存在或缺少支付配置');
            }

            $pay_config = Json::decode($order->payment_config);

            if (!$this->_verifySign(Json::encode($callbackData), $callbackData['sign'], $pay_config['platformPublicKey'])) {
                throw new \Exception('签名验证失败');
            }

            $data = $callbackData['data'];

            if (in_array($transCode, [self::TRANS_CODE_PAY_APPLY, self::TRANS_CODE_PAY_VERIFY])) {
                if (($data['status'] ?? '') == 'SUCCESS') {
                    // ⚠ 这里原来是「先 $order->is_pay = 1 并保存,再 $order->pay()」。
                    // 那样是**空转**:pay() 转调 paySuccess(),而 paySuccess 第一句就是
                    // `if ($this->is_pay == 1) return true;` —— 刚把 is_pay 存成 1,
                    // 立刻命中幂等返回,**业务回调(发货/分润/事件)一次都不执行**。
                    // (已在事务内实测复现,带不带验证的 save 都一样。)
                    // 同时还绕过了 paySuccess 的另外两道保护:并发锁、is_cancel 检查。
                    // 与 OrderPayCheckTask 里那段注释说的是同一起事故:
                    //「只标已支付、不发货不分润」。
                    //
                    // 正确写法就是直接交给 paySuccess:它自带幂等 + 加锁 + 拒绝已取消 +
                    // 业务回调成功后才置 is_pay=1(顺序不能反,反了就会出现
                    // "标了已支付但业务没跑完"的中间态)。
                    //
                    // 副作用:pay_at 由 paySuccess 取 time(),不再是网关回传的
                    // transSuccessTime —— 差几秒,且与其余所有网关口径一致(它们本来
                    // 就都走 paySuccess),换来的是上面那三道保护,值得。
                    $transactionId = $data['atuOrderNo'] ?? ($data['channelFlowNo'] ?? '');
                    // 第四参:回调实付金额,paySuccess 内与 pay_price 比对,不等拒绝并留痕(快捷支付单号=order_no,不传付款号)
                    if ($order && !$order->paySuccess((string) $transactionId, static::class, null, isset($data['transAmount']) ? (string) $data['transAmount'] : null)) {
                        Yii::warning('[FastPayQuickPayment] 支付回调置已支付失败(可能已取消/并发锁),'
                            . "pay_order_id={$order->id}");
                    }
                }
            } elseif ($transCode == self::TRANS_CODE_REFUND) {
                if (($data['status'] ?? '') == 'SUCCESS') {
                    if ($order && $order->is_refund != 1) {
                        $order->is_refund = 1;
                        $order->refund_time = date('Y-m-d H:i:s', strtotime($data['transSuccessTime'] ?? 'now'));
                        if ($order->save()) {
                            $order->notify();
                        }
                    }
                }
            }

            \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            return ['respCode' => 'SUCCESS', 'respDesc' => '成功'];

        } catch (\Exception $e) {
            Yii::error('快付快捷支付回调异常: ' . $e->getMessage());
            \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            return ['respCode' => 'FAIL', 'respDesc' => $e->getMessage()];
        }
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function queryOrder($queryData)
    {
        $commonPayOrder = CommonPayOrder::findOne(['order_no' => $queryData['order_no'], 'mall_id' => $this->mall_id]);
        if (!$commonPayOrder || !$commonPayOrder->payment_config) {
            throw new \Exception('原支付订单记录或配置不存在');
        }
        $pay_config = Json::decode($commonPayOrder->payment_config);

        $data = [
            'applicationId' => $pay_config['applicationId'],
            'oglMerOrderNo' => $commonPayOrder->order_no,
            'oglOrderDate'  => date('Ymd', strtotime($commonPayOrder->created_time)),
            'oglTransCode'  => self::TRANS_CODE_PAY_APPLY,
        ];

        try {
            $result = $this->_request(self::TRANS_CODE_QUERY, $data, $pay_config);
            if (($result['data']['status'] ?? '') == 'SUCCESS') {
                return [
                    'code' => \app\core\ApiCode::CODE_SUCCESS,
                    'msg' => '已支付',
                    // 把平台流水号带出去:掉单补偿要拿它写 transaction_id,否则那批订单
                    // 补回来是没有支付凭证的(对账、退款都缺依据)。取法与 handleCallback 同源。
                    'transaction_id' => $result['data']['atuOrderNo'] ?? ($result['data']['channelFlowNo'] ?? ''),
                ];
            }
        } catch (\Exception $e) {
            Yii::error('快付快捷支付查询失败: ' . $e->getMessage());
        }

        throw new \Exception('未查询到订单或订单未支付');
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
            ConstantHelper::PLATFORM_WECHAT,
            ConstantHelper::PLATFORM_APP
        ];
    }

    // =========================================================================
    //                实现 QuickPaymentInterface (绑卡/短信确认)
    // =========================================================================

    public function applyBindCard(array $params)
    {
        $this->_validateConfig($this->config);

        // 快付要求 RSA 公钥加密的敏感字段
        // validDate 格式 MMYY (月月年年), 例: 1228 = 2028-12
        $sensitiveFields = [
            'identityNo' => $params['identityNo'] ?? '',
            'bankCardNo' => $params['bankCardNo'] ?? '',
            'userName'   => $params['userName'] ?? '',
            'mobile'     => $params['mobile'] ?? '',
        ];

        if (($params['cDFlag'] ?? '') === 'C') {
            if (!empty($params['validDate'])) $sensitiveFields['validDate'] = $params['validDate'];
            if (!empty($params['cvv2']))      $sensitiveFields['cvv2']      = $params['cvv2'];
        }

        foreach ($sensitiveFields as $key => $value) {
            if (!empty($value)) {
                $sensitiveFields[$key] = $this->_encrypt($value, $this->config['platformPublicKey']);
            }
        }

        $bizContent = array_merge([
            'applicationId' => $this->config['applicationId'],
            'userId'        => $params['user_id'],
            'identityType'  => $params['identityType'] ?? '00',
            'cDFlag'        => $params['cDFlag'] ?? 'D',
        ], $sensitiveFields);

        // 证件有效期 (明文上送, 格式 yyyyMMdd, 长期固定 29991231)
        if (!empty($params['idDocPeriodBegin'])) {
            $bizContent['idDocPeriodBegin'] = $params['idDocPeriodBegin'];
        }
        if (!empty($params['idDocPeriodEnd'])) {
            $bizContent['idDocPeriodEnd'] = $params['idDocPeriodEnd'];
        }

        $bizContent = array_filter($bizContent, function ($item) {
            return !$this->_isEmpty($item);
        });

        $result = $this->_request(self::TRANS_CODE_CARD_APPLY, $bizContent, $this->config);

        Yii::warning('[FastPayQuick applyBindCard] ' . Json::encode($result));

        return $result['data'];
    }

    public function verifyBindCard($bindCardId, $smsCode)
    {
        $this->_validateConfig($this->config);

        $bizContent = [
            'applicationId' => $this->config['applicationId'],
            'bindCardId'    => $bindCardId,
            'smsCode'       => $smsCode
        ];

        $result = $this->_request(self::TRANS_CODE_CARD_VERIFY, $bizContent, $this->config);
        return $result['data'];
    }

    public function unbindCard($bindCardId)
    {
        $this->_validateConfig($this->config);

        $bizContent = [
            'applicationId' => $this->config['applicationId'],
            'bindCardId'    => $bindCardId
        ];

        $result = $this->_request(self::TRANS_CODE_CARD_UNBIND, $bizContent, $this->config);
        return $result['data'];
    }

    public function verifyPayment($orderNo, $smsCode)
    {
        $order = CommonPayOrder::findOne(['order_no' => $orderNo]);
        if (!$order || !$order->payment_config) {
            throw new \Exception('订单不存在或缺少配置');
        }
        $activeConfig = Json::decode($order->payment_config);

        $bizContent = [
            'applicationId' => $activeConfig['applicationId'],
            'merOrderNo'    => $orderNo,
            'smsCode'       => $smsCode
        ];

        $result = $this->_request(self::TRANS_CODE_PAY_VERIFY, $bizContent, $activeConfig);
        return $result['data'];
    }

    // =========================================================================
    //                        辅助方法 (加密/签名/请求)
    // =========================================================================

    private function _validateConfig(array $config)
    {
        $requiredKeys = ['applicationId', 'platformCode', 'merchantPrivateKey', 'platformPublicKey', 'status'];
        foreach ($requiredKeys as $key) {
            if ($this->_isEmpty($config[$key] ?? null)) {
                throw new \InvalidArgumentException("快捷支付配置缺少关键参数:{$key}");
            }
        }
    }

    private function _encrypt($data, $publicKey)
    {
        if (empty($data)) return '';

        $pem = "-----BEGIN PUBLIC KEY-----\n" .
            wordwrap($publicKey, 64, "\n", true) .
            "\n-----END PUBLIC KEY-----";

        $keyResource = openssl_pkey_get_public($pem);
        if (!$keyResource) {
            throw new \Exception('快付平台公钥格式错误,无法加密');
        }

        $encrypted = '';
        if (!openssl_public_encrypt($data, $encrypted, $keyResource)) {
            throw new \Exception('敏感信息加密失败');
        }

        return base64_encode($encrypted);
    }

    private function _request($transCode, $bizContent, array $config)
    {
        $reqParams = [
            'transCode'    => $transCode,
            'platformCode' => $config['platformCode'],
            'timeStamp'    => (string)time(),
            'randStr'      => Yii::$app->security->generateRandomString(32),
            'signType'     => 'RSA2',
            'data'         => $bizContent,
        ];

        $reqParams['sign'] = $this->_generateSign($reqParams, $config);

        $jsonBody = Json::encode($reqParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        Yii::warning('========== FastPayQuick REQUEST ==========');
        Yii::warning('[transCode] ' . $transCode);
        Yii::warning('[url] ' . self::API_URL);
        Yii::warning('[body] ' . $jsonBody);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::API_URL);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        Yii::warning('========== FastPayQuick RESPONSE ==========');
        Yii::warning('[transCode] ' . $transCode);
        Yii::warning('[raw] ' . $response);

        if ($error) throw new \RuntimeException('网络请求失败: ' . $error);   // 网络层失败=结果未知,RuntimeException 供 RefundTask 区分(同 FastPayAliJsPayment)

        $result = Json::decode($response);
        if (!isset($result['code']) || $result['code'] !== '0000') {
            throw new \Exception($result['message'] ?? '接口返回错误');
        }
        return $result;
    }

    private function _generateSign($params, array $config)
    {
        $stringA = $this->_getStringToBeSigned($params);
        $privateKey = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap($config['merchantPrivateKey'], 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";
        $pkey = openssl_pkey_get_private($privateKey);
        if (!$pkey) throw new \Exception('商户私钥错误');

        $signature = '';
        openssl_sign($stringA, $signature, $pkey, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

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
        if (!$pkey) return false;

        return openssl_verify($stringToVerify, base64_decode($signature), $pkey, OPENSSL_ALGO_SHA256) === 1;
    }

    private function _getStringToBeSigned(array $params): string
    {
        $this->sortArray($params);
        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                if (false === $this->_isEmpty($v)) {
                    $v_filtered = array_filter($v, function ($item) {
                        return !$this->_isEmpty($item);
                    });
                    $json = json_encode($v_filtered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if ($i > 0) $stringToBeSigned .= "&";
                    $stringToBeSigned .= "$k" . "=" . "$json";
                    $i++;
                }
            } else {
                if (false === $this->_isEmpty($v)) {
                    if ($i > 0) $stringToBeSigned .= "&";
                    $stringToBeSigned .= "$k" . "=" . "$v";
                    $i++;
                }
            }
        }
        return $stringToBeSigned;
    }

    private function sortArray(array &$array)
    {
        ksort($array);
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $this->sortArray($array[$key]);
            }
        }
    }

    private function _isEmpty($value): bool
    {
        if (!isset($value)) return true;
        if (is_string($value) && trim($value) === "") return true;
        if (is_array($value) && empty($value)) return true;
        return false;
    }
}
