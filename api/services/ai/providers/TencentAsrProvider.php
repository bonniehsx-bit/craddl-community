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

namespace app\services\ai\providers;

use app\services\ai\AsrProviderInterface;
use app\services\ai\TencentCloudSigner;

/**
 * 腾讯云语音识别驱动 —— 录音文件识别（CreateRecTask + DescribeTaskStatus）。
 *
 * 用「录音文件识别」而不是「一句话识别」：后者单条最长 60 秒，门店实际服务录音
 * 可达数十分钟，只有文件识别类接口覆盖得了。submit()/query() 都是单次 HTTP 请求，
 * 立即返回，真正的等待由调用方（Job）分多次派发完成，见 AsrProviderInterface 注释。
 */
class TencentAsrProvider implements AsrProviderInterface
{
    private $secretId;
    private $secretKey;

    private const HOST    = 'asr.tencentcloudapi.com';
    private const SERVICE = 'asr';
    private const VERSION = '2019-06-14';

    /** 任务状态码（腾讯云约定）：0=等待中 1=执行中 2=成功 3=失败 */
    private const STATUS_SUCCESS = 2;
    private const STATUS_FAILED  = 3;

    public function __construct(string $secretId = '', string $secretKey = '')
    {
        $this->secretId  = $secretId;
        $this->secretKey = $secretKey;
    }

    public function submit(string $audioUrl, array $options = []): string
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException('腾讯云 ASR 未配置 SecretId/SecretKey');
        }

        $payload = json_encode([
            'EngineModelType' => (string)($options['engine_model_type'] ?? '16k_zh'),
            'ChannelNum'      => 1,
            'ResTextFormat'   => 0,
            'SourceType'      => 0, // 0 = URL 方式
            'Url'             => $audioUrl,
        ], JSON_UNESCAPED_UNICODE);

        $resp   = $this->call('CreateRecTask', $payload);
        $taskId = (int)($resp['Data']['TaskId'] ?? 0);
        if ($taskId <= 0) {
            throw new \RuntimeException('腾讯云 ASR 任务提交失败：未返回 TaskId');
        }

        return (string)$taskId;
    }

    public function query(string $taskId): array
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException('腾讯云 ASR 未配置 SecretId/SecretKey');
        }

        $payload = json_encode(['TaskId' => (int)$taskId], JSON_UNESCAPED_UNICODE);
        $resp    = $this->call('DescribeTaskStatus', $payload);
        $data    = $resp['Data'] ?? [];
        $status  = (int)($data['Status'] ?? 0);

        if ($status === self::STATUS_SUCCESS) {
            return ['status' => 'done', 'text' => trim((string)($data['Result'] ?? '')), 'error_msg' => ''];
        }
        if ($status === self::STATUS_FAILED) {
            return ['status' => 'failed', 'text' => '', 'error_msg' => (string)($data['ErrorMsg'] ?? '识别失败')];
        }

        return ['status' => 'pending', 'text' => '', 'error_msg' => ''];
    }

    public function isAvailable(): bool
    {
        return $this->secretId !== '' && $this->secretKey !== '';
    }

    public function getName(): string
    {
        return 'tencent';
    }

    private function call(string $action, string $payload): array
    {
        $timestamp = time();
        $date      = gmdate('Y-m-d', $timestamp);
        $auth      = TencentCloudSigner::sign(
            $this->secretId, $this->secretKey, self::HOST, self::SERVICE, $timestamp, $date, $payload
        );

        $ch = curl_init('https://' . self::HOST . '/');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json; charset=utf-8',
                'Host: ' . self::HOST,
                "X-TC-Action: {$action}",
                'X-TC-Version: ' . self::VERSION,
                "X-TC-Timestamp: {$timestamp}",
                "Authorization: {$auth}",
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new \RuntimeException("ASR 网络错误: {$curlErr}");
        }
        if ($httpCode !== 200) {
            throw new \RuntimeException("ASR 请求失败: HTTP {$httpCode}");
        }

        $result = json_decode((string)$response, true);
        if (!empty($result['Response']['Error'])) {
            $err = $result['Response']['Error'];
            throw new \RuntimeException("腾讯云 ASR [{$action}] [{$err['Code']}]: {$err['Message']}");
        }

        return $result['Response'] ?? [];
    }
}
