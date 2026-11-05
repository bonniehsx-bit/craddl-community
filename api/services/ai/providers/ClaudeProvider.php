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
 * Anthropic Claude AI 提供商
 *
 * 文档：https://docs.anthropic.com/en/api/messages
 * Claude 使用 Messages API（非 OpenAI 兼容格式），需单独实现。
 *
 * 留桩：接口结构已完整，配置 apiKey 后即可使用。
 * API Key 格式：sk-ant-api03-...
 * 默认模型：claude-3-5-haiku-20241022（速度快、成本低，适合对话场景）
 */
class ClaudeProvider implements AiProviderInterface
{
    private const BASE_URL     = 'https://api.anthropic.com';
    private const API_PATH     = '/v1/messages';
    private const API_VERSION  = '2023-06-01';
    private const DEFAULT_MODEL = 'claude-3-5-haiku-20241022';
    private const TIMEOUT      = 30;

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
        // Claude Messages API: content[0].text
        return $resp['content'][0]['text'] ?? '';
    }

    public function streamChat(
        string $systemPrompt,
        string $userMessage,
        array $options = [],
        ?callable $onChunk = null
    ): string {
        // TODO P4.1: 实装 SSE 流式读取（解析 content_block_delta 事件）
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
        $payload = [
            'model'    => $options['model'] ?? $this->model,
            'system'   => $systemPrompt,
            'messages' => [
                [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type'   => 'image',
                            'source' => [
                                'type'       => 'base64',
                                'media_type' => $mimeType,
                                'data'       => $base64Image,
                            ],
                        ],
                        ['type' => 'text', 'text' => $userMessage],
                    ],
                ],
            ],
            'max_tokens' => $options['max_tokens'] ?? 512,
        ];

        $resp = $this->doPost($this->baseUrl . self::API_PATH, $payload);
        return $resp['content'][0]['text'] ?? '';
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey) && str_starts_with($this->apiKey, 'sk-ant-');
    }

    public function getName(): string
    {
        return 'claude';
    }

    // ─────────────────────── private ────────────────────────────

    private function buildPayload(string $system, string $user, array $options, bool $stream): array
    {
        return [
            'model'      => $options['model'] ?? $this->model,
            'system'     => $system,
            'messages'   => [
                ['role' => 'user', 'content' => $user],
            ],
            'max_tokens' => $options['max_tokens'] ?? 1024,
            'stream'     => $stream,
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
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: ' . self::API_VERSION,
            ],
        ]);

        $body  = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            Yii::error("ClaudeProvider curl error: {$error}");
            throw new \RuntimeException("Claude 请求失败：{$error}");
        }
        if ($code !== 200) {
            Yii::error("ClaudeProvider HTTP {$code}: {$body}");
            throw new \RuntimeException("Claude 返回异常（HTTP {$code}）");
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Claude 返回非 JSON 格式');
        }
        return $data;
    }
}
