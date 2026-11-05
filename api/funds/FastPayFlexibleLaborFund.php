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
use app\models\PayoutLog;
use app\models\UserSettlementInfo;
use Yii;
use yii\helpers\Json;

/**
 * 快付 (FastPay) 灵活用工渠道
 * [V3.1 完整版]
 * 包含：图片上传、签约、打款、余额查询、状态查询及所有加密签名辅助方法
 */
class FastPayFlexibleLaborFund extends BaseFlexibleLaborFund
{
    /** @var array 从数据库加载的配置 */
    private $config;

    /** @var string API接口地址 */
    private const API_URL = 'https://payapi.yunfastpay.com';

    /** @var string 文件上传接口地址 */
    private const UPLOAD_URL = 'https://payfile.yunfastpay.com/upload';

    /** @var string 交易码常量 */
    private const TRANS_CODE_LG_SIGN_APPLY = 'LG_SIGN_APPLY';
    private const TRANS_CODE_LG_SIGN_QUERY = 'LG_SIGN_QUERY';
    private const TRANS_CODE_LG_GRANT = 'LG_GRANT';
    private const TRANS_CODE_LG_BALANCE_QUERY = 'NORMAL_BALANCE_QUERY';
    private const TRANS_CODE_NORMAL_ORDER_QUERY = 'NORMAL_ORDER_QUERY';

    public function __construct($mall_id)
    {
        $this->mall_id = $mall_id;
        $fundSettings = OptionHelper::get('flexible_labor_funds', $mall_id);

        // 允许只初始化用于列表展示，关键操作再检查配置
        $this->config = $fundSettings[self::class] ?? [];
        $this->name = $this->config['name'] ?? '快付灵活用工';
        $this->fund_type = self::class;
    }

    private function checkConfig()
    {
        if (empty($this->config['status'])) {
            throw new \Exception('快付灵活用工渠道未开启');
        }
        // channelCode 是文件上传必须的，通常邮件里提供
        $requiredKeys = ['applicationId', 'mainstayNo', 'merchantPrivateKey', 'platformPublicKey', 'platformCode', 'channelCode'];
        foreach ($requiredKeys as $key) {
            if (empty($this->config[$key])) {
                throw new \Exception("快付灵活用工配置不完整: 缺少 {$key}");
            }
        }
    }

    // ======================= [1. 表单字段配置] =======================

    /**
     * @inheritdoc
     */
    public function getOnboardingFields(): array
    {
        return [
            [
                'key' => 'real_name',
                'label' => '真实姓名',
                'type' => 'text',
                'required' => true,
                'placeholder' => '请输入您的真实姓名'
            ],
            [
                'key' => 'identity_no',
                'label' => '身份证号',
                'type' => 'id_card',
                'required' => true,
                'placeholder' => '请输入18位身份证号码'
            ],
            [
                'key' => 'bank_account_no',
                'label' => '结算银行卡号',
                'type' => 'number',
                'required' => true,
                'placeholder' => '必须为本人名下的储蓄卡'
            ],
            [
                'key' => 'mobile',
                'label' => '银行预留手机号',
                'type' => 'number',
                'required' => true,
                'placeholder' => '请输入银行预留的手机号码'
            ],
            [
                'key' => 'idDocFrontImg',
                'label' => '证件国徽面照片',
                'type' => 'image',
                'required' => true,
                'placeholder' => '请上传身份证国徽面照片'
            ],
            [
                'key' => 'idDocBackImg',
                'label' => '证件人像面照片',
                'type' => 'image',
                'required' => true,
                'placeholder' => '请上传身份证件人像面照片'
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function getSupportedChannels(): array
    {
        // 灵活用工通常通过银行卡下发，但也可能支持支付宝/微信
        return ['bank', 'alipay', 'wechat'];
    }

    // ======================= [核心功能实现] =======================

    /**
     * 执行灵工签约
     * 流程：下载图片 -> 上传到快付获取FileId -> 发起签约请求 -> 保存本地
     */
    public function onboardPerson(array $userData): array
    {
        $this->checkConfig();
        $userId = $userData['user_id'] ?? null;

        Yii::warning("[FastPayLgFund::onboardPerson] 开始签约 User:{$userId}");

        // 基础校验
        list($isValid, $errorMessage) = $this->_validateOnboardingFields($userData);
        if (!$isValid) {
            throw new \InvalidArgumentException($errorMessage);
        }

        try {
            // Step 1: 上传身份证图片获取 fileId
            // 注意：需确保前端传来的 idDocFrontImg 是可访问的 URL
            $frontFileId = $this->uploadToFastPay($userData['idDocFrontImg'], 'id_card_front.jpg');
            $backFileId = $this->uploadToFastPay($userData['idDocBackImg'], 'id_card_back.jpg');

            if (!$frontFileId || !$backFileId) {
                throw new \Exception("身份证照片上传至渠道方失败，请重试");
            }

            // Step 2: 发起签约请求 (敏感数据需加密)
            $encryptedIdentityNo = $this->_encrypt($userData['identity_no']);
            $encryptedUserName = $this->_encrypt($userData['real_name']);
            $encryptedMobile = $this->_encrypt($userData['mobile']);
            $accountNo= $this->_encrypt($userData['bank_account_no']);
            $bizContent = [
                'applicationId' => $this->config['applicationId'],
                'userId' => (string)$userId,
                'mainstayNo' => $this->config['mainstayNo'],
                'identityNo' => $encryptedIdentityNo,
                'userName' => $encryptedUserName,
                'mobile' => $encryptedMobile,
                'accountNo' => $accountNo,
                // 这里必须传 fileId
                'idDocFrontImg' => $frontFileId,
                'idDocBackImg' => $backFileId,
                'sendMessage' => false,
            ];
            $result = $this->_request(self::TRANS_CODE_LG_SIGN_APPLY, $bizContent);
            Yii::warning($result);
            $resData = $result['data'];
            // Step 3: 处理结果 —— 映射远程签约态 → 本地态
            $remoteStatus = $resData['status'] ?? '';
            if ($remoteStatus !== 'SUCCESS' && $remoteStatus !== 'DOING') {
                return ['status' => 'failed', 'message' => $resData['desc'] ?? '签约申请失败'];
            }

            // SUCCESS=签约终态完成,可放行打款;DOING=处理中(未终态),落 pending。
            // 🔴 DOING 绝不能当 success:否则用户在签约真正完成前就通过资格闸,打款时才失败;
            //    且远程若最终拒签,本地假 success 无回退路径,用户每次提现都走到打款才被拒。
            //    pending 态由 checkUserEligibility 远程复核 / cron 轮询转成终态。
            $localStatus = $remoteStatus === 'SUCCESS'
                ? UserSettlementInfo::FUND_STATUS_SUCCESS
                : UserSettlementInfo::FUND_STATUS_PENDING;

            // Step 4: 保存账户数据到本地(打款时用),状态按远程实际态落库
            $this->saveLocalData($userId, $userData, $localStatus);

            return [
                'status'  => $localStatus,
                'message' => $resData['desc'] ?? ($localStatus === UserSettlementInfo::FUND_STATUS_SUCCESS ? '签约成功' : '签约处理中,请稍后查看结果'),
            ];

        } catch (\Exception $e) {
            Yii::error("[FastPayLgFund::onboardPerson] 签约异常: " . $e->getMessage());
            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * 上传文件到快付服务器
     * 注意：fileBase64 字段不参与签名
     * @param string $fileUrl 图片URL
     * @param string $fileName 虚拟文件名
     * @return string|null FileId
     * @throws \Exception
     */
    private function uploadToFastPay(string $fileUrl, string $fileName): ?string
    {
        // 1. 读取文件并转 Base64
        // 处理相对路径
        if (strpos($fileUrl, 'http') !== 0) {
            $fileUrl = ServerHelper::getHost() . $fileUrl;
        }

        $fileContent = @file_get_contents($fileUrl);
        if (!$fileContent) {
            Yii::error("无法读取图片文件: {$fileUrl}");
            throw new \Exception("无法读取身份证图片，请确保上传成功");
        }

        // 检查大小 (文档限制2MB)
        if (strlen($fileContent) > 2 * 1024 * 1024) {
            throw new \Exception("身份证图片大小不能超过2MB");
        }

        $base64Data = base64_encode($fileContent);

        // 2. 构造参与签名的参数 (注意：fileBase64 被排除)
        $signParams = [
            'applicationId' => $this->config['applicationId'],
            'timeStamp' => (string)time(),
            'fileName' => $fileName,
            'randStr' => Yii::$app->security->generateRandomString(16),
            'signType' => 'RSA2',
            'platformCode' => $this->config['platformCode'],
            'channelCode' => $this->config['channelCode'] ?? '', // 必填
        ];

        // 3. 生成签名
        $sign = $this->_generateSign($signParams);

        // 4. 构造完整请求体 (合并 签名参数 + fileBase64 + sign)
        $requestBody = array_merge($signParams, [
            'fileBase64' => $base64Data,
            'sign' => $sign
        ]);

        // 5. 发送请求
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::UPLOAD_URL);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, Json::encode($requestBody));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 上传较大文件增加超时时间
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('快付文件上传网络错误: ' . $error);
        }

        $result = Json::decode($response);
        Yii::warning('===========================');
        Yii::warning($result);
        Yii::warning('============================');

        // 校验返回
        if (isset($result['code']) && $result['code'] === '0000' && isset($result['data']['fileId'])) {
            return $result['data']['fileId'];
        }

        $msg = $result['message'] ?? '上传失败';
        Yii::error("快付图片上传失败: " . Json::encode($result));
        throw new \Exception("身份证件上传失败: {$msg}");
    }

    /**
     * 保存签约数据到本地数据库
     */
    private function saveLocalData($userId, $userData, $status = UserSettlementInfo::FUND_STATUS_SUCCESS)
    {
        $model = UserSettlementInfo::findOne(['user_id' => $userId, 'mall_id' => $this->mall_id]);
        if (!$model) {
            $model = new UserSettlementInfo();
            $model->mall_id = $this->mall_id;
            $model->user_id = $userId;
        }

        // 更新物理字段
        $model->real_name = $userData['real_name'];
        $model->identity_no = $userData['identity_no'];
        $model->mobile = $userData['mobile'];

        // 更新 JSON 数据 (保存所有渠道特定信息)
        $allChannelData = $model->getChannelData();
        $allChannelData[self::class] = [
            'real_name' => $userData['real_name'],
            'identity_no' => $userData['identity_no'],
            'mobile' => $userData['mobile'],
            'bank_account_no' => $userData['bank_account_no'],
            'idDocFrontImg' => $userData['idDocFrontImg'],
            'idDocBackImg' => $userData['idDocBackImg'],
        ];
        $model->setChannelData($allChannelData);

        // 更新状态(按远程实际态,默认 success 向后兼容)
        $model->updateOnboardingStatus(self::class, [
            'status' => $status,
            'message' => $status === UserSettlementInfo::FUND_STATUS_SUCCESS ? '签约成功' : '签约处理中',
        ]);

        $model->save();
    }

    // ======================= [3. 核心：打款与查询] =======================

    /**
     * 获取用户账户信息 (用于前端回显或打款准备)
     */
    public function getUserAccountInfo($settlementInfo): array
    {
        if (!$settlementInfo) {
            return [];
        }

        // 提取本地保存的数据
        $allChannelData = $settlementInfo->getChannelData();
        $myData = $allChannelData[self::class] ?? [];

        return [
            'type' => 'bank',
            'bank_name' => '灵活用工账户',
            'account' => $myData['bank_account_no'] ?? '', // 收款卡号
            'real_name' => $myData['real_name'] ?? $settlementInfo->real_name,
            'mobile' => $myData['mobile'] ?? $settlementInfo->mobile,
            'identity_no' => $myData['identity_no'] ?? $settlementInfo->identity_no,
            'idDocFrontImg' => $myData['idDocFrontImg'] ?? '',
            'idDocBackImg' => $myData['idDocBackImg'] ?? '',
        ];
    }

    /**
     * 执行灵工下发
     */
    public function payout(array $payoutData): array
    {
        $this->checkConfig();
        $channel = $payoutData['channel']; // bank, alipay, wechat
        $userId = $payoutData['user_id'];

        // 映射支付方式
        $agentWaysMap = ['bank' => 'BANK_CARD', 'alipay' => 'ALIPAY', 'wechat' => 'WX'];
        if (!isset($agentWaysMap[$channel])) {
            throw new \Exception("快付灵工渠道不支持 '{$channel}' 提现方式。");
        }

        // 获取本地签约信息
        $settlementInfo = UserSettlementInfo::findOne(['user_id' => $userId, 'mall_id' => $this->mall_id]);
        if (!$settlementInfo) {
            throw new \Exception("未找到用户 {$userId} 的签约信息");
        }

        // 提取数据
        $accountInfo = $this->getUserAccountInfo($settlementInfo);

        if (empty($accountInfo['real_name']) || empty($accountInfo['identity_no']) || empty($accountInfo['mobile'])) {
            throw new \InvalidArgumentException('灵工下发缺少必要的签约信息(姓名/身份证/手机号)');
        }

        // 确定收款账号 (目前仅收集了银行卡号，如果是ALIPAY/WX需要额外逻辑或假设使用手机号/OpenID)
        $targetAccountNo = $accountInfo['account'];
        if (empty($targetAccountNo)) {
            throw new \InvalidArgumentException('灵工下发缺少收款账号');
        }

        try {
            Yii::warning("[FastPayLgFund::payout] 开始下发 Order:{$payoutData['order_no']}");

            // 构造打款参数 (敏感数据加密)
            $bizContent = [
                'applicationId' => $this->config['applicationId'],
                'mainstayNo' => $this->config['mainstayNo'],
                'merOrderNo' => $payoutData['order_no'],
                'merOrderTime' => date('YmdHis'),
                'agentWays' => $agentWaysMap[$channel],
                'agentAmount' => (string)sprintf("%.2f", $payoutData['amount']),
                // 加密字段
                'accountName' => $this->_encrypt($accountInfo['real_name']),
                'accountNo' => $this->_encrypt(str_replace(' ', '', $targetAccountNo)),
                'identityNo' => $this->_encrypt($accountInfo['identity_no']),
                'mobile' => $this->_encrypt($accountInfo['mobile']),
                'notifyUrl' => ServerHelper::getHost() . '/lg/fastpay.php',
                'remark' => $payoutData['remark'] ?? '灵工任务下发',
            ];

            // 发起请求
            $response = $this->_request(self::TRANS_CODE_LG_GRANT, $bizContent);
            $resData = $response['data'];

            // 状态判断
            $statusMap = ['SUCCESS' => 'success', 'DOING' => 'doing', 'FAIL' => 'failed'];
            $internalStatus = $statusMap[$resData['status']] ?? 'doing';

            if ($internalStatus === 'failed') {
                throw new \Exception('快付灵工下发失败：' . ($resData['desc'] ?? '未知原因'));
            }

            // 记录日志
            $this->createPayoutLog($payoutData, $bizContent, $response, $internalStatus);

            return [
                'status' => $internalStatus,
                'message' => $resData['desc'] ?? '请求已受理',
                'payout_no' => $resData['sysOrderNo'] ?? ''
            ];

        } catch (\Exception $e) {
            Yii::error("[FastPayLgFund::payout] 下发异常: " . $e->getMessage());
            $this->createPayoutLog($payoutData, $bizContent ?? [], ['error' => $e->getMessage()], 'failed');
            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * 检查本地是否有签约记录
     */
    public function checkUserEligibility($params): array
    {
        $userId = $params['user_id'] ?? 0;
        if ($userId) {
            // 🔴 必须带 mall_id:否则多商城下会读到别的 mall 的签约记录 → 误判资格(跨租户泄漏)
            $set = UserSettlementInfo::findOne(['user_id' => $userId, 'mall_id' => $this->mall_id]);
            if ($set) {
                $statusInfo = $set->getOnboardingStatus(self::class);
                $status = $statusInfo['status'] ?? UserSettlementInfo::FUND_STATUS_NONE;

                // 本地已终态成功 → 直接放行
                if ($status === UserSettlementInfo::FUND_STATUS_SUCCESS) {
                    return [
                        'is_eligible' => true,
                        'message' => '信息校验通过',
                        'require_onboarding' => false,
                        'result' => $statusInfo,
                    ];
                }

                // 本地"处理中"(签约 DOING 未终态):远程复核一次,真终态成功才放行并落本地 success。
                // 这样既能在签约完成后自动放行,又不会因本地假 success 提前放行未完成的签约。
                if ($status === UserSettlementInfo::FUND_STATUS_PENDING) {
                    if ($this->remoteSignConfirmed((int)$userId)) {
                        $set->updateOnboardingStatus(self::class, [
                            'status' => UserSettlementInfo::FUND_STATUS_SUCCESS,
                            'message' => '签约已完成',
                        ]);
                        $set->save(false);
                        return [
                            'is_eligible' => true,
                            'message' => '签约已完成',
                            'require_onboarding' => false,
                            'result' => ['status' => UserSettlementInfo::FUND_STATUS_SUCCESS, 'message' => '签约已完成'],
                        ];
                    }
                    // 远程仍未终态:不放行,但也不是"未签约"(无需再引导重新进件,前端显示"处理中")
                    return [
                        'is_eligible' => false,
                        'message' => '灵工签约处理中，请稍后再试',
                        'require_onboarding' => false,
                    ];
                }
            }
        }
        return [
            'is_eligible' => false,
            'message' => '请先完成灵工签约',
            'require_onboarding' => true,
        ];
    }

    /**
     * 远程查询灵工签约是否已终态成功。
     * 独立方法、只打远程不读本地——避免与 checkUserEligibility / checkOnboardingStatus 互相递归。
     */
    private function remoteSignConfirmed(int $userId): bool
    {
        try {
            $result = $this->_request(self::TRANS_CODE_LG_SIGN_QUERY, [
                'applicationId' => $this->config['applicationId'],
                'userId'        => (string)$userId,
                'mainstayNo'    => $this->config['mainstayNo'],
            ]);
            return (($result['data']['status'] ?? '') === 'SUCCESS');
        } catch (\Throwable $e) {
            Yii::warning("[FastPayLgFund] 远程签约查询失败 user={$userId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 查询远程签约状态 (同步)
     */
    public function checkOnboardingStatus(int $userId): array
    {
        // 先查本地
        $localStatus = $this->checkUserEligibility(['user_id' => $userId]);
        if ($localStatus['is_eligible']) {
            return $localStatus['result'];
        }

        // 远程查询
        $bizContent = [
            'applicationId' => $this->config['applicationId'],
            'userId' => (string)$userId,
            'mainstayNo' => $this->config['mainstayNo'],
        ];
        try {
            $result = $this->_request(self::TRANS_CODE_LG_SIGN_QUERY, $bizContent);
            $resData = $result['data'];

            if (($resData['status'] ?? '') === 'SUCCESS') {
                return ['status' => 'success', 'message' => '已签约'];
            }
            return ['status' => 'none', 'message' => '未签约'];

        } catch (\Exception $e) {
            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * 查询平台商户余额
     */
    public function queryPlatformBalance(string $agentWay): array
    {
        $agentWaysMap = ['bank' => 'BANK_CARD', 'alipay' => 'ALIPAY', 'wechat' => 'WX'];
        if (!isset($agentWaysMap[$agentWay])) {
            throw new \InvalidArgumentException("不支持的余额查询方式: {$agentWay}");
        }
        $bizContent = [
            'applicationId' => $this->config['applicationId'],
            'queryType' => 'MER',
            'mainstayNo' => $this->config['mainstayNo'],
            'agentWays' => $agentWaysMap[$agentWay],
        ];
        $result = $this->_request(self::TRANS_CODE_LG_BALANCE_QUERY, $bizContent);
        return $result['data'];
    }

    /**
     * 查询订单状态
     */
    public function orderQuery($params)
    {
        $order_no = $params['order_no'];
        $time = $params['payout_at'];

        $bizContent = [
            'applicationId' => $this->config['applicationId'],
            'oglMerOrderNo' => $order_no,
            'oglOrderDate' => date('Ymd', $time),
            'oglTransCode' => 'LG_GRANT',
        ];
        try {
            $result = $this->_request(self::TRANS_CODE_NORMAL_ORDER_QUERY, $bizContent);
            $resData = $result['data'];
            return ['code' => 0, 'msg' => $resData['desc'] ?? '查询成功', 'data' => $resData];
        } catch (\Exception $e) {
            return ['code' => 1, 'msg' => '状态查询接口异常: ' . $e->getMessage()];
        }
    }

    public function getDisplay()
    {
        return ['name' => $this->name, 'fund' => self::class];
    }

    // ======================= [4. 辅助：日志、网络请求、加密] =======================

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

    /**
     * 通用 API 请求方法 (用于业务接口)
     */
    private function _request(string $transCode, array $bizContent): array
    {
        $reqParams = [
            'transCode' => $transCode,
            'platformCode' => $this->config['platformCode'],
            'timeStamp' => (string)time(),
            'randStr' => Yii::$app->security->generateRandomString(32),
            'signType' => 'RSA2',
            // 过滤空值
            'data' => array_filter($bizContent, function ($item) {
                return $item !== null && $item !== '';
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

    /**
     * 生成 RSA2 签名
     */
    private function _generateSign(array $params): string
    {
        $stringToBeSigned = $this->_getStringToBeSigned($params);
        $privateKey = "-----BEGIN RSA PRIVATE KEY-----\n" . wordwrap($this->config['merchantPrivateKey'], 64, "\n", true) . "\n-----END RSA PRIVATE KEY-----";
        $pkey = openssl_pkey_get_private($privateKey);
        if ($pkey === false) throw new \Exception('商户私钥格式不正确');
        openssl_sign($stringToBeSigned, $signature, $pkey, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    /**
     * 公钥加密 (用于敏感信息)
     */
    private function _encrypt(string $data): string
    {
        $publicKey = $this->config['platformPublicKey'];
        $res = "-----BEGIN PUBLIC KEY-----\n" . wordwrap($publicKey, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
        $pkey = openssl_pkey_get_public($res);
        if ($pkey === false) throw new \Exception('快付平台公钥格式不正确');
        openssl_public_encrypt($data, $encrypted, $pkey);
        return base64_encode($encrypted);
    }

    /**
     * 验证快付异步通知签名(RSA2)。
     *
     * ⚠ 灵工下发回调是能把提现改成「已到账」的写入口,不验签等于任何人 POST 一份
     *    {"data":{"status":"SUCCESS","merOrderNo":"WD..."}} 就能把别人的提现刷成已打款。
     *
     * 算法对齐官方 DEMO SignUtil::verify:去掉 sign 后对**整份报文**递归排序 + 递归剔空,
     * 数组值 json_encode,再以 & 拼 k=v。
     *
     * @param array $message 通知报文全量(含 sign/signType/data …),不是只有 data
     */
    public function verifyNotifySign(array $message): bool
    {
        $sign = (string)($message['sign'] ?? '');
        if ($sign === '') {
            return false;
        }
        if (($message['signType'] ?? 'RSA2') !== 'RSA2') {
            return false;   // 官方目前仅 RSA2,出现其它算法一律不放行
        }
        $publicKey = (string)($this->config['platformPublicKey'] ?? '');
        if ($publicKey === '') {
            throw new \Exception('未配置快付平台公钥,无法验签灵工下发回调');
        }

        unset($message['sign']);
        $signSource = $this->_getStringToBeSigned($message);

        $pkey = openssl_pkey_get_public(
            "-----BEGIN PUBLIC KEY-----\n" . wordwrap($publicKey, 64, "\n", true) . "\n-----END PUBLIC KEY-----"
        );
        if ($pkey === false) {
            throw new \Exception('快付平台公钥格式无效');
        }
        return openssl_verify($signSource, base64_decode($sign), $pkey, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * 构造待签名/验签字符串 —— 与官方 DEMO SignUtil::getStringToBeSigned 逐条对齐。
     *
     * 原实现两处与 DEMO 不符,下单时因我们自造的报文不含空值/不含嵌套数组而未暴露,
     * 但**验签验的是对方发来的报文**,一旦不符就必然验签失败、把真回调当伪造丢弃:
     *   · 空值判定:DEMO 用 trim() 判空串(" " 也算空),原实现只比 ''
     *   · 数组值:DEMO 递归剔除子数组空值,且全空时降级 JSON_FORCE_OBJECT 输出 {};
     *     原实现 array_filter 只过滤一层且丢弃整个空数组键
     */
    private function _getStringToBeSigned(array $params): string
    {
        self::sortArray($params);
        $parts = [];
        foreach ($params as $k => $v) {
            if ($this->_isEmptyValue($v)) {
                continue;
            }
            if (is_array($v)) {
                $this->filterEmptyValues($v);
                $parts[] = "$k=" . ($this->_isEmptyValue($v)
                    ? json_encode($v, JSON_FORCE_OBJECT)
                    : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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

    /** 递归剔除数组空值(对齐 DEMO SignUtil::filterEmptyValues) */
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

    /**
     * 递归按键名排序数组
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
}