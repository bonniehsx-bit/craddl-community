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

namespace app\split;

use app\helpers\OptionHelper;
use app\helpers\ServerHelper;
use app\models\SplitOnboardingLog;
use Yii;
use yii\helpers\Json;

/**
 * 快付分账渠道实现
 */
class FastPaySplit extends BaseSplit
{
    private $config;
    private const API_URL = 'https://payapi.yunfastpay.com';
    private const TRANS_CODE_MERCHANT_H5_REGISTER = 'MERCHANT_H5_REGISTER';
    private const TRANS_CODE_MERCHANT_REGISTER_QUERY = 'MERCHANT_REGISTER_QUERY';
    private const TRANS_CODE_MERCHANT_INFO_QUERY = 'MERCHANT_INFO_QUERY';
    private const TRANS_CODE_SPLIT_ORDER_APPLY = 'SPLIT_ORDER_APPLY';
    private const TRANS_CODE_SPLIT_ORDER_QUERY = 'NORMAL_ORDER_QUERY';
    private const TRANS_CODE_MERCHANT_SPLIT_ADD = 'MERCHANT_SPLIT_ADD';
    private const DECRYPT_FIELDS = ["legalIdDocNumber", "legalMobile", "contactIdDocNumber", "contactMobile", "uboIdDocNumber", "settleAccountNumber"];


    public function __construct($mall_id)
    {
        $this->mall_id = $mall_id;
        // [核心修改] 从独立的 'splits' 配置项中读取
        $splitSettings = OptionHelper::get('splits', $mall_id, []);
        $configClass = self::class; // 使用当前类名作为键
        if (!isset($splitSettings[$configClass])) {
            throw new \InvalidArgumentException('快付分账渠道配置未找到');
        }
        $this->config = $splitSettings[$configClass];
        if (empty($this->config['status'])) {
            throw new \InvalidArgumentException('快付分账渠道未开启');
        }
        $this->name = ($this->config['name'] ?? '快付') . ' (分账)';
        $this->split_type = self::class;
    }

    /**
     * 实现H5进件接口
     * @param array $params ['outMerCode' => '...']
     * @return array
     * @throws \Exception
     */
    public function merchantH5Register(array $params)
    {
        Yii::warning("[FastPaySplit] 开始处理H5分账进件，外部商户号: " . $params['outMerCode']);
        if (empty($this->config['channelCode'])) {
            throw new \InvalidArgumentException('快付分账渠道未配置渠道码');
        }
        $bizContent = [
            'channelCode' => $this->config['channelCode'],
            'outMerCode' => $params['outMerCode'],
            'businessRole' => 'ACQUIRING', // 固定为分账接收方
            'notifyUrl' => ServerHelper::getHost() . '/register/fastpay.php',
        ];
        $result = $this->_request(self::TRANS_CODE_MERCHANT_H5_REGISTER, $bizContent);
        Yii::warning("[FastPaySplit] H5分账进件链接生成成功，外部商户号: {$params['outMerCode']}");
        return $result['data'];
    }

    /**
     * 实现进件结果查询接口
     * @param array $params ['h5RegisterFlow' => '...']
     * @return array
     * @throws \Exception
     */
    public function merchantRegisterQuery(array $params)
    {
        Yii::warning("[FastPaySplit] 开始查询进件结果，H5流水号: " . $params['h5RegisterFlow']);
        $bizContent = [
            'h5RegisterFlow' => $params['h5RegisterFlow']
        ];
        $result = $this->_request(self::TRANS_CODE_MERCHANT_REGISTER_QUERY, $bizContent);
        Yii::warning($result);
        Yii::warning("[FastPaySplit] 进件结果查询成功，H5流水号: {$params['h5RegisterFlow']}");
        return $result['data'];
    }

    /**
     * 商户信息查询接口
     * @param array $params ['h5RegisterFlow' => '...']
     * @return array
     * @throws \Exception
     */
    public function merchantInfoQuery(array $params)
    {
        Yii::warning("[FastPaySplit] 开始查询商户详细信息，H5流水号: " . $params['h5RegisterFlow']);

        $bizContent = [
            'h5RegisterFlow' => $params['h5RegisterFlow']
        ];

        // 发送请求，注意这里接收的是完整的响应体，因为需要解密
        $response = $this->_request(self::TRANS_CODE_MERCHANT_INFO_QUERY, $bizContent);

        // 使用私钥递归解密响应中的敏感字段
        $decrypted_data = $this->_decryptIteration($response, self::DECRYPT_FIELDS);

        Yii::warning("[FastPaySplit] 商户详细信息查询并解密成功，H5流水号: {$params['h5RegisterFlow']}");
        return $decrypted_data['data']; // 只返回核心的data部分
    }

    /**
     * 订单分账接口
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function splitOrderApply(array $params)
    {
        Yii::warning("[FastPaySplit] 开始处理订单分账，原订单号: " . $params['original_order_no']);
        // 构造分账明细
        $splitDetail = [];
        foreach ($params['details'] as $detail) {
            $splitDetail[] = [
                'splitPayeeUserId' => $detail['user_id'],
                'splitAmount' => (string)sprintf("%.2f", $detail['amount']),
                'splitDesc' => $detail['description'] ?? '订单分账',
            ];
        }
        Yii::warning("[FastPaySplit] <UNK>: " . Json::encode($splitDetail));
        if (isset($params['application_id'])) {
            $this->config['applicationId'] = $params['application_id'];
        }
        $bizContent = [
            'applicationId' => $this->config['applicationId'],
            'oglMerOrderNo' => $params['original_order_no'],
            'oglMerOrderDate' => $params['original_order_date'],
            'merOrderNo' => $params['split_order_no'],
            'splitTotalAmount' => (string)sprintf("%.2f", $params['total_amount']),
            'merSplitTime' => date('YmdHis'),
            'notifyUrl' => ServerHelper::getHost() . '/split/fastpay.php',
            'splitDetail' => $splitDetail,
        ];
        $result = $this->_request(self::TRANS_CODE_SPLIT_ORDER_APPLY, $bizContent);
        $data = $result['data'];
        if ($data && isset($data['status'])) {
            $data['status'] = self::transStatus($data['status']);
        }
        return $data;
    }

    /**
     * 订单分账查询接口
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function splitOrderQuery(array $params)
    {
        Yii::warning("[FastPaySplit] 开始查询订单分账结果，原订单号: " . $params['original_order_no']);

        $bizContent = [
            'applicationId' => $this->config['applicationId'],
            'oglMerOrderNo' => $params['original_order_no'],
            'oglOrderDate' => $params['original_order_date'], // 格式: YYYYMMDD
            'oglTransCode' => self::TRANS_CODE_SPLIT_ORDER_APPLY, // 原交易码固定为分账申请接口的交易码
        ];

        $result = $this->_request(self::TRANS_CODE_SPLIT_ORDER_QUERY, $bizContent);

        Yii::warning("[FastPaySplit] 订单分账结果查询成功，原订单号: {$params['original_order_no']}");
        return $result['data'];
    }

    public function splitBindReceiveUserId(array $params)
    {
        Yii::warning("[FastPaySplit] 绑定接收方 " . $params['userId']);
        $bizContent = [
            'applicationId' => $params['applicationId'],
            'userId' => $params['userId'],
        ];
        $result = $this->_request(self::TRANS_CODE_MERCHANT_SPLIT_ADD, $bizContent);
        Yii::warning($result);
        return $result;
    }

    /**
     * 验证异步通知签名
     * @param string $jsonString 接收到的原始POST数据
     * @return bool
     * @throws \Exception
     */
    public function verifyNotify(string $jsonString): bool
    {
        $params = json_decode($jsonString, true);
        if (!$params) {
            throw new \Exception('通知报文JSON格式不正确');
        }
        if (empty($params['sign'])) {
            throw new \Exception('通知报文中缺少签名');
        }
        return $this->_verifySign($params, $params['sign']);
    }

    /**
     * 处理分账进件异步通知
     * @param string $rawPostData
     * @return SplitOnboardingLog
     * @throws \Exception
     */
    public function handleSplitNotify(string $rawPostData): SplitOnboardingLog
    {
        // 1. 验签 (如果失败会直接抛出异常)
        $this->verifyNotify($rawPostData);

        // 2. 解析数据
        $notifyData = json_decode($rawPostData, true);
        $data = $notifyData['data'];
        $outMerCode = $data['outMerCode'] ?? null;
        if (!$outMerCode) {
            throw new \Exception("通知报文中缺少外部商户号 outMerCode");
        }

        // 3. 查找日志记录
        $log = SplitOnboardingLog::findOne(['out_mer_code' => $outMerCode, 'split_class' => self::class]);
        if (!$log) {
            throw new \Exception("找不到对应的进件记录，外部商户号: {$outMerCode}");
        }

        // 4. 防止重复处理
        if ($log->status !== SplitOnboardingLog::STATUS_WAIT && $log->status !== SplitOnboardingLog::STATUS_DOING) {
            Yii::warning("[FastPaySplit::handleNotify] 进件记录 #{$log->id} 已处理，状态为: {$log->status}，跳过。");
            return $log; // 直接返回已处理的日志
        }

        // 5. 更新日志状态
        $log->notify_data = $rawPostData;
        if ($data['status'] === 'SUCCESS') {
            $log->status = SplitOnboardingLog::STATUS_SUCCESS;
            $log->remark = '进件成功 (异步通知)';
            // [核心修改] 同时保存 platform_user_id 和 application_id
            $log->platform_user_id = $data['userId'] ?? null;
            $log->application_id = $data['applicationId'] ?? null;
        } else {
            $log->status = SplitOnboardingLog::STATUS_FAILED;
            $log->remark = '进件失败 (异步通知): ' . ($data['desc'] ?? '未知原因');
        }

        if (!$log->save()) {
            throw new \Exception("更新进件日志 #{$log->id} 失败: " . json_encode($log->getErrors()));
        }

        Yii::warning("[FastPaySplit::handleNotify] 进件记录 #{$log->id} 状态已通过异步通知更新为: {$log->status}");
        return $log;
    }


    // --- 签名、请求与加密辅助方法 ---
    private function _request(string $transCode, array $bizContent): array
    {
        $reqParams = [
            'transCode' => $transCode,
            'platformCode' => $this->config['platformCode'],
            'timeStamp' => (string)time(),
            'randStr' => Yii::$app->security->generateRandomString(32),
            'signType' => 'RSA2',
            'data' => array_filter($bizContent, function ($item) {
                return !$this->_isEmpty($item);
            }),
        ];
        $reqParams['sign'] = $this->_generateSign($reqParams);

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

        if ($error) throw new \Exception('请求快付接口失败: ' . $error);
        $result = Json::decode($response);
        if (!$result) throw new \Exception('快付接口返回数据格式错误');
        if (!isset($result['code']) || $result['code'] !== '0000') {
            throw new \Exception($result['message'] ?? '快付接口返回未知错误');
        }
        return $result;
    }

    private function _generateSign(array $params): string
    {
        $stringToBeSigned = $this->_getStringToBeSigned($params);
        $privateKey = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap($this->config['merchantPrivateKey'], 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";
        $pkey = openssl_pkey_get_private($privateKey);
        if ($pkey === false) throw new \Exception('商户私钥格式不正确');
        openssl_sign($stringToBeSigned, $signature, $pkey, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    private function _verifySign(array $params, string $signature): bool
    {
        unset($params['sign']);
        $stringToVerify = $this->_getStringToBeSigned($params);
        $publicKey = "-----BEGIN PUBLIC KEY-----\n" .
            wordwrap($this->config['platformPublicKey'], 64, "\n", true) .
            "\n-----END PUBLIC KEY-----";
        $pkey = openssl_pkey_get_public($publicKey);
        if ($pkey === false) throw new \Exception('快付平台公钥格式不正确');
        return openssl_verify($stringToVerify, base64_decode($signature), $pkey, OPENSSL_ALGO_SHA256) === 1;
    }

    private function _decrypt(string $data): string
    {
        $privateKey = "-----BEGIN RSA PRIVATE KEY-----\n" . wordwrap($this->config['merchantPrivateKey'], 64, "\n", true) . "\n-----END RSA PRIVATE KEY-----";
        $pkey = openssl_pkey_get_private($privateKey);
        if ($pkey === false) throw new \Exception("您使用的私钥错误，请检查 merchantPrivateKey 配置！");
        openssl_private_decrypt(base64_decode($data), $decrypted, $pkey);
        return $decrypted;
    }

    private function _decryptIteration(array $data, array $decryptFields): array
    {
        if (empty($decryptFields)) return $data;
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $value = $this->_decryptIteration($value, $decryptFields);
            } elseif (in_array($key, $decryptFields) && is_string($value) && !empty($value)) {
                $value = $this->_decrypt($value);
            }
        }
        unset($value);
        return $data;
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

    private static function sortArray(array &$array)
    {
        ksort($array);
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                self::sortArray($array[$key]);
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