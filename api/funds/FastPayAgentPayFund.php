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

namespace app\funds;

use app\helpers\OptionHelper;
use app\helpers\ServerHelper;
use app\models\BranchBank;
use app\models\PayoutLog;
use app\models\UserSettlementInfo;
use Yii;
use yii\helpers\Json;

/**
 * 快付 (FastPay) 代付渠道
 * [V3.1 优化版 - 本地进件 + 二要素代付]
 *
 * 核心功能：
 * 1. 提供进件表单字段 (getOnboardingFields)
 * 2. 处理进件数据保存 (onboardPerson)
 * 3. 提供账户信息回显 (getUserAccountInfo) [本次核心新增]
 * 4. 执行二要素代付 (payout)
 */
class FastPayAgentPayFund extends BaseFund
{
    /** @var array 从数据库加载的配置 */
    private $config;

    /** @var string API接口地址 */
    private const API_URL = 'https://payapi.yunfastpay.com';

    /** @var string 交易码常量 */
    private const TRANS_CODE_AGENT_PAY = 'AGENT_ORDER_APPLY';
    private const TRANS_CODE_BANK_QUERY = 'BANK_INFO_QUERY';
    private const TRANS_CODE_NORMAL_ORDER_QUERY = 'NORMAL_ORDER_QUERY';

    public function __construct($mall_id)
    {
        $this->mall_id = $mall_id;
        $fundSettings = OptionHelper::get('funds', $mall_id);

        // 允许配置不存在时不抛出致命异常，方便后台列表展示，但在关键方法中会再次检查
        $this->config = $fundSettings[self::class] ?? [];
        $this->name = $this->config['name'] ?? '快付代付';
        $this->fund_type = self::class;
    }

    /**
     * 检查配置是否有效
     * @throws \Exception
     */
    private function checkConfig()
    {
        if (empty($this->config) || empty($this->config['status'])) {
            throw new \Exception('快付代付渠道未开启或配置无效');
        }
        if (empty($this->config['merchantPrivateKey']) || empty($this->config['platformPublicKey'])) {
            throw new \Exception('快付密钥配置不完整');
        }
    }

    /**
     * @inheritdoc
     * 定义前端渲染结算表单所需的字段
     */
    public function getOnboardingFields(): array
    {
        return [
            [
                'key' => 'real_name',
                'label' => '真实姓名',
                'type' => 'text',
                'required' => true,
                'placeholder' => '请输入与银行卡开户名一致的姓名'
            ],
            [
                'key' => 'account_name',
                'label' => '开户名',
                'type' => 'text',
                'required' => false,
                'note' => '对公账户必填公司名，对私账户可不填(默认同真实姓名)',
                'placeholder' => '对公必填，对私可留空'
            ],
            [
                'key' => 'identity_no',
                'label' => '身份证号',
                'type' => 'id_card',
                'required' => true,
                'placeholder' => '请输入18位身份证号码'
            ],
            [
                'key' => 'account_type',
                'label' => '账户类型',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => 'PRIVATE', 'label' => '对私'],
                    ['value' => 'PUBLIC', 'label' => '对公'],
                ]
            ],
            [
                'key' => 'bank_account_no',
                'label' => '银行卡号',
                'type' => 'number',
                'required' => true,
                'placeholder' => '请输入银行卡号',
                // 快付代付进件不做卡校验(快付无鉴权接口),卡真伪在提现打款时才判定 → 前端明确提示,
                // 避免用户以为填了就一定能提现。打款失败已能安全原路退款(不资损)。
                'note' => '请务必填写本人真实有效的银行卡；卡信息将在提现打款时校验,若有误将导致打款失败、款项原路退回。'
            ],
            [
                'key' => 'bank_name',
                'label' => '开户银行',
                'type' => 'bank_picker', // 前端组件支持
                'required' => true,
                'placeholder' => '请搜索并选择开户银行'
            ],
            [
                'key' => 'bank_code',
                'label' => '银行编码',
                'type' => 'text',
                'required' => true,
                'readonly' => true,
                'placeholder' => '选择开户银行后自动填充',
            ],
            [
                'key' => 'bank_branch_no',
                'label' => '支行联行号',
                'type' => 'bank_branch_picker', // 前端组件支持
                'required' => true,
                'placeholder' => '请搜索并选择开户银行联行号'
            ],
            [
                'key' => 'mobile',
                'label' => '预留手机号',
                'type' => 'text',
                'required' => true,
                'placeholder' => '请输入银行预留的手机号'
            ],
            [
                'key' => 'use_desc',
                'label' => '代付用途',
                'type' => 'select',
                'required' => false,
                'note' => '对公(PUBLIC)账户此项必填',
                'options' => [
                    ['value' => '201', 'label' => '工资资金'],
                    ['value' => '202', 'label' => '活动经费'],
                    ['value' => '203', 'label' => '养老金'],
                    ['value' => '204', 'label' => '货款'],
                    ['value' => '205', 'label' => '劳务费'],
                    ['value' => '206', 'label' => '保险理财'],
                    ['value' => '207', 'label' => '资金下发'],
                    ['value' => '208', 'label' => '营业退款'],
                    ['value' => '210', 'label' => '退回款项'],
                    ['value' => '211', 'label' => '消费款项'],
                ]
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function getSupportedChannels(): array
    {
        return ['bank'];
    }

    /**
     * [核心新增] 获取用户账户信息
     * 供 WithdrawalApplyForm 调用，解决回显和报错问题
     */
    public function getUserAccountInfo($settlementInfo): array
    {
        if (!$settlementInfo) {
            return [];
        }

        // 复用提取逻辑
        $data = $this->extractAccountData($settlementInfo);

        return [
            'type' => 'bank',
            'bank_name' => $data['bank_name'] ?? '',
            'account' => $data['account_no'] ?? '', // 统一返回卡号
            'real_name' => $data['account_name'] ?? $settlementInfo->real_name,
            'mobile' => $data['mobile'] ?? $settlementInfo->mobile,
            // 还可以返回更多内部需要的字段
            'bank_code' => $data['bank_code'] ?? '',
        ];
    }

    /**
     * @inheritdoc
     * 执行代付打款
     */
    public function payout(array $payoutData): array
    {
        $this->checkConfig();

        $channel = $payoutData['channel'];
        $userId = $payoutData['user_id'] ?? null;

        if ($channel !== 'bank') {
            throw new \Exception("FastPay 渠道不支持 '{$channel}' 提现方式。");
        }
        if (!$userId) {
            throw new \InvalidArgumentException('打款缺少 user_id');
        }

        // 1. 获取结算信息
        $settlementInfo = UserSettlementInfo::findOne(['user_id' => $userId, 'mall_id' => $this->mall_id, 'is_delete' => 0]);
        if (!$settlementInfo) {
            throw new \Exception("未找到用户 {$userId} 的结算信息");
        }

        // 2. 提取并校验数据
        $accountInfo = $this->extractAccountData($settlementInfo);

        if (empty($accountInfo['account_name']) || empty($accountInfo['account_no']) || empty($accountInfo['bank_code'])) {
            throw new \Exception('结算信息不完整(缺少户名、卡号 or 银行编码)，请用户重新填写。');
        }

        // 对公账户特殊校验
        if ($accountInfo['account_type'] === 'PUBLIC') {
            // 默认用途
            if (empty($accountInfo['use_desc'])) $accountInfo['use_desc'] = '204';
        }

        $bizContent = [];
        try {
            Yii::warning("[FastPay::payout] 开始打款 Order:{$payoutData['order_no']} User:{$userId}");

            // 3. 加密敏感信息
            $encryptedAccountName = $this->_encrypt($accountInfo['account_name'], $this->config['platformPublicKey']);
            $encryptedAccountNo = $this->_encrypt($accountInfo['account_no'], $this->config['platformPublicKey']);

            // 4. 组装请求参数
            $bizContent = [
                'applicationId' => $this->config['applicationId'],
                'merOrderNo' => $payoutData['order_no'],
                'merOrderTime' => date('YmdHis'),
                'agentAmount' => (string)sprintf("%.2f", $payoutData['amount']),
                'accountType' => $accountInfo['account_type'],
                'bankBranchNo' => $accountInfo['bank_branch_no'] ?? null,
                'accountName' => $encryptedAccountName,
                'accountNo' => $encryptedAccountNo,
                'bankCode' => $accountInfo['bank_code'],
                'notifyUrl' => ServerHelper::getHost() . '/fund/fastpay.php', // 确保此文件存在
                'useDesc' => $accountInfo['use_desc'],
                'remark' => $payoutData['remark'] ?? '提现代付',
            ];

            // 5. 发起请求
            // [诊断日志] bizContent 里 accountName/accountNo 已加密,可安全打印;这里能看到发给快付的原文
            Yii::warning("[FastPay代付-诊断] 打款请求 order={$payoutData['order_no']} biz=" . json_encode($bizContent, JSON_UNESCAPED_UNICODE), 'fastpay');
            $response = $this->_request(self::TRANS_CODE_AGENT_PAY, $bizContent);
            // [诊断日志] 快付原始返回全量打印 —— 卡真伪/成功失败在这里才见分晓
            Yii::warning("[FastPay代付-诊断] 快付原始返回 order={$payoutData['order_no']} resp=" . json_encode($response, JSON_UNESCAPED_UNICODE), 'fastpay');
            $resData = $response['data'];

            // 6. 状态判断
            $statusMap = ['SUCCESS' => 'success', 'DOING' => 'doing', 'FAIL' => 'failed'];
            $internalStatus = $statusMap[$resData['status']] ?? 'doing';

            if ($internalStatus === 'failed') {
                throw new \Exception('上游返回失败：' . ($resData['desc'] ?? '未知错误'));
            }

            // 7. 记录日志
            $this->createPayoutLog($payoutData, $bizContent, $response, $internalStatus);

            return [
                'status' => $internalStatus,
                'message' => $resData['desc'] ?? '请求已受理',
                'payout_no' => $resData['sysOrderNo'] ?? ''
            ];

        } catch (\Exception $e) {
            Yii::error("[FastPay::payout] 异常: " . $e->getMessage());
            // 记录失败日志
            $this->createPayoutLog($payoutData, $bizContent, ['error' => $e->getMessage()], 'failed');
            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * [辅助] 从 UserSettlementInfo 中提取并标准化本渠道的数据
     */
    private function extractAccountData(UserSettlementInfo $settlementInfo): array
    {
        $allChannelData = $settlementInfo->getChannelData();
        $myData = $allChannelData[self::class] ?? [];

        $accountType = $myData['account_type'] ?? 'PRIVATE';

        // 户名处理：对公取 account_name，对私如果没填 account_name 则取 real_name
        $accountName = $myData['account_name'] ?? '';
        if ($accountType === 'PRIVATE' && empty($accountName)) {
            $accountName = $settlementInfo->real_name;
        }

        return [
            'account_type' => $accountType,
            'account_name' => $accountName,
            'account_no' => isset($myData['bank_account_no']) ? str_replace(' ', '', $myData['bank_account_no']) : '',
            'bank_name' => $myData['bank_name'] ?? '',
            'bank_code' => $myData['bank_code'] ?? '',
            'bank_branch_no' => $myData['bank_branch_no'] ?? '',
            'use_desc' => $myData['use_desc'] ?? null,
            'mobile' => $settlementInfo->mobile, // 优先用物理表的手机号
        ];
    }

    /**
     * @inheritdoc
     * 将表单数据保存到本地
     */
    public function onboardPerson(array $userData): array
    {
        $userId = $userData['user_id'] ?? null;
        if (!$userId) return ['status' => 'failed', 'message' => '缺少用户ID'];

        // 1. 调用通用校验逻辑 (可根据需要在 BaseFund 中实现或此处保留)
        // 这里简化为直接校验必填项
        if (empty($userData['bank_account_no'])) return ['status' => 'failed', 'message' => '银行卡号不能为空'];

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $model = UserSettlementInfo::findOne(['user_id' => $userId, 'mall_id' => $this->mall_id]);
            if (!$model) {
                $model = new UserSettlementInfo();
                $model->mall_id = $this->mall_id;
                $model->user_id = $userId;
            }

            // 2. 更新基础信息
            $model->real_name = $userData['real_name'];
            $model->identity_no = $userData['identity_no'];
            $model->mobile = $userData['mobile'];

            // 3. 更新渠道专属数据
            $allChannelData = $model->getChannelData();

            // 智能处理户名：如果是对私且没填开户名，自动填写真实姓名
            $accountName = $userData['account_name'] ?? '';
            if ($userData['account_type'] === 'PRIVATE' && empty($accountName)) {
                $accountName = $userData['real_name'];
            }

            $currentData = [
                'account_type' => $userData['account_type'],
                'account_name' => $accountName,
                'bank_name' => $userData['bank_name'],
                'bank_code' => $userData['bank_code'],
                'bank_account_no' => $userData['bank_account_no'],
                'bank_branch_no' => $userData['bank_branch_no'] ?? '',
                'use_desc' => $userData['use_desc'] ?? null,
            ];

            $allChannelData[self::class] = $currentData;
            $model->setChannelData($allChannelData);

            // 4. 更新状态为成功 (本地进件无需审核)
            $model->updateOnboardingStatus(self::class, [
                'status' => UserSettlementInfo::FUND_STATUS_SUCCESS,
                'message' => '信息保存成功'
            ]);

            if (!$model->save()) {
                throw new \Exception($this->getErrorMsg($model));
            }

            $transaction->commit();

            // [诊断日志] 代付进件=纯本地保存,不调快付、无条件 success —— 这就是"随便填卡都成功"的原因。
            // 卡真伪要到打款(payout)时才由快付判定。卡号脱敏只留尾4位。
            $rawCard = (string)($userData['bank_account_no'] ?? '');
            $maskCard = strlen($rawCard) > 4 ? ('****' . substr($rawCard, -4)) : '****';
            Yii::warning("[FastPay代付-诊断] onboardPerson=本地保存直接success(未调用快付,卡真伪打款时才校验)"
                . " user={$userId} type={$userData['account_type']} 卡尾号={$maskCard} 户名={$accountName} bank_code=" . ($userData['bank_code'] ?? ''), 'fastpay');

            return ['status' => 'success', 'message' => '保存成功'];

        } catch (\Exception $e) {
            $transaction->rollBack();
            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * @inheritdoc
     * 检查资格
     */
    public function checkUserEligibility(array $userData): array
    {
        $userId = $userData['user_id'] ?? null;
        if (!$userId) return ['is_eligible' => false, 'message' => '用户ID缺失'];

        $statusInfo = $this->checkOnboardingStatus($userId);

        if ($statusInfo['status'] === UserSettlementInfo::FUND_STATUS_SUCCESS) {
            return ['is_eligible' => true, 'message' => '资格校验通过', 'result' => $statusInfo];
        }

        return [
            'is_eligible' => false,
            'message' => '请先填写银行卡信息',
            'require_onboarding' => true
        ];
    }

    /**
     * @inheritdoc
     */
    public function checkOnboardingStatus(int $userId): array
    {
        $model = UserSettlementInfo::findOne(['user_id' => $userId, 'mall_id' => $this->mall_id]);
        if (!$model) {
            return ['status' => UserSettlementInfo::FUND_STATUS_NONE, 'message' => '未填写信息'];
        }

        $statusInfo = $model->getOnboardingStatus(self::class);
        if ($statusInfo['status'] !== UserSettlementInfo::FUND_STATUS_SUCCESS) {
            return $statusInfo;
        }

        // 校验数据完整性 (防止脏数据)
        $allChannelData = $model->getChannelData();
        $myData = $allChannelData[self::class] ?? [];
        if (empty($myData['bank_account_no']) || empty($myData['bank_code'])) {
            return ['status' => UserSettlementInfo::FUND_STATUS_FAILED, 'message' => '数据不完整，请重新填写'];
        }

        $statusInfo['settlement_data'] = $myData;
        $statusInfo['settlement_data']['real_name'] = $model->real_name; // 补充显示字段

        return $statusInfo;
    }

    /**
     * 订单状态查询
     */
    public function orderQuery($params)
    {
        $order_no = $params['order_no'];
        $time = $params['payout_at'];

        $bizContent = [
            'applicationId' => $this->config['applicationId'],
            'oglMerOrderNo' => $order_no,
            'oglOrderDate' => date('Ymd', $time),
            'oglTransCode' => self::TRANS_CODE_AGENT_PAY,
        ];

        try {
            $result = $this->_request(self::TRANS_CODE_NORMAL_ORDER_QUERY, $bizContent);
            $resData = $result['data'];
            return ['code' => 0, 'msg' => $resData['desc'] ?? '查询成功', 'data' => $resData];
        } catch (\Exception $e) {
            return ['code' => 1, 'msg' => '查询异常: ' . $e->getMessage()];
        }
    }

    // ======================= [银行查询相关] =======================

    public function queryBankInfo(string $bankName, string $channelCode = 'dinpay')
    {
        $bizContent = [
            'current' => "1",
            'size' => "50",
            'bankName' => $bankName,
            'channelCode' => $this->config['channelCode'] ?? $channelCode,
        ];
        $result = $this->_request(self::TRANS_CODE_BANK_QUERY, $bizContent);
        return $result['data'];
    }

    public function queryBankInfoBranch(string $bankName)
    {
        if (empty($bankName)) return ['branchList' => []];

        $list = BranchBank::find()
            ->select(['branch_name', 'branch_code', 'province_code', 'city_code'])
            ->where(['like', 'branch_name', $bankName])
            ->limit(100)
            ->asArray()
            ->all();

        // 格式化为前端组件需要的字段
        $formattedList = array_map(function ($item) {
            return [
                'bankBranchName' => $item['branch_name'],
                'bankBranchCode' => $item['branch_code'],
                'provinceCode' => $item['province_code'],
                'cityCode' => $item['city_code'],
            ];
        }, $list);

        return ['branchList' => $formattedList];
    }

    // ======================= [私有辅助方法] =======================

    private function createPayoutLog(array $payoutData, array $request, array $response, string $status)
    {
        $statusMap = [
            'success' => PayoutLog::STATUS_SUCCESS,
            'doing' => PayoutLog::STATUS_PENDING,
            'failed' => PayoutLog::STATUS_FAIL,
        ];

        $payoutLog = new PayoutLog();
        $payoutLog->mall_id = $this->mall_id;
        $payoutLog->user_id = $payoutData['user_id'];
        $payoutLog->source_id = $payoutData['source_id'];
        $payoutLog->source_type = $payoutData['source_type'];
        $payoutLog->fund_class = self::class;
        $payoutLog->channel = $payoutData['channel'];
        $payoutLog->amount = $payoutData['amount'];
        $payoutLog->status = $statusMap[$status] ?? PayoutLog::STATUS_PENDING;

        $payoutLog->request_data = Json::encode($request);
        $payoutLog->response_data = Json::encode($response);
        $payoutLog->payout_no = $response['data']['sysOrderNo'] ?? ($response['payout_no'] ?? '');

        if ($status === 'success') {
            $payoutLog->completed_at = time();
        }
        $payoutLog->save();
    }

    private function _request(string $transCode, array $bizContent): array
    {
        $this->checkConfig();

        // 过滤空值参数
        $data = array_filter($bizContent, function ($item) {
            return $item !== null && $item !== '' && $item !== [];
        });

        $reqParams = [
            'transCode' => $transCode,
            'platformCode' => $this->config['platformCode'],
            'timeStamp' => (string)time(),
            'randStr' => Yii::$app->security->generateRandomString(32),
            'signType' => 'RSA2',
            'data' => $data,
        ];

        $reqParams['sign'] = $this->_generateSign($reqParams);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::API_URL);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, Json::encode($reqParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 40); // 增加超时时间
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) throw new \Exception('API请求失败: ' . $error);

        $result = Json::decode($response);
        if (!$result || !isset($result['code'])) {
            throw new \Exception('API返回格式异常');
        }

        if ($result['code'] !== '0000') {
            throw new \Exception($result['message'] ?? 'API返回错误代码: ' . $result['code']);
        }

        return $result;
    }

    private function _generateSign(array $params): string
    {
        $stringToBeSigned = $this->_getStringToBeSigned($params);
        $privateKey = $this->_formatKey($this->config['merchantPrivateKey'], 'RSA PRIVATE KEY');

        $pkey = openssl_pkey_get_private($privateKey);
        if ($pkey === false) throw new \Exception('商户私钥格式无效');

        openssl_sign($stringToBeSigned, $signature, $pkey, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    /**
     * 验证快付异步通知的签名(RSA2)。
     *
     * ⚠ 这是代付回调唯一的身份闸门 —— 回调只凭 merOrderNo 就能把一笔提现改成「已到账」,
     *    不验签等于任何人 POST 一个 {"data":{"status":"SUCCESS","merOrderNo":"WD..."}}
     *    就能把别人的提现刷成已打款。
     *
     * 算法严格对齐官方 DEMO 的 SignUtil::verify:
     *   待验签串 = 去掉 sign 后,对整份报文递归按键升序 + 递归剔除空值,数组值 json_encode
     *   (UNESCAPED_UNICODE|UNESCAPED_SLASHES),再以 & 拼 k=v。
     *
     * @param array $message 通知报文全量(含 sign / signType / data …),不是只有 data
     */
    public function verifyNotifySign(array $message): bool
    {
        $sign = (string)($message['sign'] ?? '');
        if ($sign === '') {
            return false;
        }
        if (($message['signType'] ?? 'RSA2') !== 'RSA2') {
            // 官方目前仅 RSA2;出现其它算法一律当验签不通过,不放行
            return false;
        }
        $publicKey = (string)($this->config['platformPublicKey'] ?? '');
        if ($publicKey === '') {
            throw new \Exception('未配置快付平台公钥,无法验签代付回调');
        }

        unset($message['sign']);
        $signSource = $this->_getStringToBeSigned($message);

        $pkey = openssl_pkey_get_public($this->_formatKey($publicKey, 'PUBLIC KEY'));
        if ($pkey === false) {
            throw new \Exception('快付平台公钥格式无效');
        }
        return openssl_verify($signSource, base64_decode($sign), $pkey, OPENSSL_ALGO_SHA256) === 1;
    }

    private function _encrypt(string $data, string $publicKey): string
    {
        $publicKeyFormatted = $this->_formatKey($publicKey, 'PUBLIC KEY');
        $pkey = openssl_pkey_get_public($publicKeyFormatted);
        if ($pkey === false) throw new \Exception('平台公钥格式无效');

        openssl_public_encrypt($data, $encrypted, $pkey);
        return base64_encode($encrypted);
    }

    /**
     * 格式化密钥，增加头尾
     */
    private function _formatKey($key, $type)
    {
        if (strpos($key, '-----BEGIN') !== false) return $key;
        return "-----BEGIN {$type}-----\n" .
            wordwrap($key, 64, "\n", true) .
            "\n-----END {$type}-----";
    }

    /**
     * 生成待签名/验签串 —— 与官方 DEMO SignUtil::getStringToBeSigned 逐条对齐。
     *
     * 注意数组值那一支:必须**先递归剔除子数组里的空值**再 json_encode,
     * 全空时降级 JSON_FORCE_OBJECT 输出 {}。下单时我们自己构造的 bizContent 不含空值,
     * 所以原实现一直没出问题;但**验签的是对方发来的报文**,data 里带一个空字段就会
     * 拼出不同的串导致验签必败 —— 那会让所有回调被当成伪造丢弃。
     */
    private function _getStringToBeSigned(array $params): string
    {
        $this->sortArray($params);
        $parts = [];
        foreach ($params as $k => $v) {
            if ($this->_isEmptyValue($v)) {
                continue;
            }
            if (is_array($v)) {
                $this->filterEmptyValues($v);
                $json = $this->_isEmptyValue($v)
                    ? json_encode($v, JSON_FORCE_OBJECT)
                    : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $parts[] = "$k=$json";
            } else {
                $parts[] = "$k=$v";
            }
        }
        return implode('&', $parts);
    }

    /** 与 DEMO SignUtil::isEmpty 同口径(注意 0 / "0" 不算空) */
    private function _isEmptyValue($value): bool
    {
        if (!isset($value)) return true;
        if (is_string($value) && trim($value) === '') return true;
        if (is_array($value) && empty($value)) return true;
        return false;
    }

    /** 递归剔除数组里的空值(对齐 DEMO SignUtil::filterEmptyValues) */
    private function filterEmptyValues(array &$array): void
    {
        foreach ($array as $key => &$value) {
            if ($this->_isEmptyValue($value)) {
                unset($array[$key]);
                continue;
            }
            if (is_array($value)) {
                $this->filterEmptyValues($value);
            }
        }
    }

    private function sortArray(array &$array)
    {
        ksort($array);
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $this->sortArray($value);
            }
        }
    }

    public function getDisplay()
    {
        return ['name' => $this->name, 'fund' => self::class];
    }
}