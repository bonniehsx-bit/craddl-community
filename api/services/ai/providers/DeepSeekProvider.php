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

use app\services\ai\AiProviderInterface;
use Yii;

/**
 * DeepSeek AI 提供商
 *
 * 文档：https://platform.deepseek.com/api-docs/
 * 接口兼容 OpenAI Chat Completions 格式。
 *
 * 留桩：接口结构已完整，填入 apiKey 后即可直接使用。
 */
class DeepSeekProvider implements AiProviderInterface
{
    private const BASE_URL      = 'https://api.deepseek.com';
    private const API_PATH      = '/v1/chat/completions';
    private const DEFAULT_MODEL = 'deepseek-chat';
    private const TIMEOUT       = 30;

    private string $apiKey;
    private string $baseUrl;
    private string $model;

    public function __construct(string $apiKey, string $baseUrl = '', string $model = '')
    {
        $this->apiKey  = $apiKey;
        $this->baseUrl = rtrim($baseUrl ?: self::BASE_URL, '/');
        $this->model   = $model ?: self::DEFAULT_MODEL;
    }

    public function chat(string $systemPrompt, string $userMessage, array $options = []): string
    {
        $payload = $this->buildPayload($systemPrompt, $userMessage, $options, false);
        $resp    = $this->doPost($this->baseUrl . self::API_PATH, $payload);
        return $resp['choices'][0]['message']['content'] ?? '';
    }

    public function streamChat(
        string $systemPrompt,
        string $userMessage,
        array $options = [],
        ?callable $onChunk = null
    ): string {
        // TODO P4.1: 实装 SSE 流式读取（curl CURLOPT_WRITEFUNCTION）
        // 当前降级为普通调用
        $result = $this->chat($systemPrompt, $userMessage, $options);
        if ($onChunk !== null) {
            $onChunk($result);
        }
        return $result;
    }

    public function analyzeImage(
        string $systemPrompt,
        string $userMessage,
        string $base64Image,
        string $mimeType = 'image/jpeg',
        array $options = []
    ): string {
        // DeepSeek-V3 支持 OpenAI 兼容视觉格式（需使用支持 vision 的模型）
        $payload = [
            'model'    => $options['model'] ?? $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type'      => 'image_url',
                            'image_url' => ['url' => "data:{$mimeType};base64,{$base64Image}"],
                        ],
                        ['type' => 'text', 'text' => $userMessage],
                    ],
                ],
            ],
            'temperature' => $options['temperature'] ?? 0.3,
            'max_tokens'  => $options['max_tokens']  ?? 512,
        ];

        $resp = $this->doPost($this->baseUrl . self::API_PATH, $payload);
        return $resp['choices'][0]['message']['content'] ?? '';
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey) && str_starts_with($this->apiKey, 'sk-');
    }

    public function getName(): string
    {
        return 'deepseek';
    }

    // ─────────────────────── private ────────────────────────────

    private function buildPayload(
        string $system,
        string $user,
        array $options,
        bool $stream
    ): array {
        return [
            'model'       => $options['model'] ?? $this->model,
            'messages'    => [
                ['role' => 'system',  'content' => $system],
                ['role' => 'user',    'content' => $user],
            ],
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens'  => $options['max_tokens']  ?? 1024,
            'stream'      => $stream,
        ];
    }

    private function doPost(string $url, array $payload): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
        ]);

        $body  = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            Yii::error("DeepSeekProvider curl error: {$error}");
            throw new \RuntimeException("DeepSeek 请求失败：{$error}");
        }
        if ($code !== 200) {
            Yii::error("DeepSeekProvider HTTP {$code}: {$body}");
            throw new \RuntimeException("DeepSeek 返回异常（HTTP {$code}）");
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('DeepSeek 返回非 JSON 格式');
        }
        return $data;
    }
}
