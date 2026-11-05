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
 * 字节跳动豆包（火山引擎 ARK）AI 提供商
 *
 * 文档：https://www.volcengine.com/docs/82379/1302008
 * 接口兼容 OpenAI Chat Completions 格式，需使用火山引擎的 API Key 和 Endpoint ID（即 model）。
 *
 * 留桩：接口结构已完整，配置 apiKey + baseUrl（endpoint URL）后即可使用。
 * baseUrl 示例：https://ark.cn-beijing.volces.com/api/v3
 * model 填写推理接入点 ID，如：ep-20250101xxxxx-xxxxx
 */
class DouBaoProvider implements AiProviderInterface
{
    private const DEFAULT_BASE_URL = 'https://ark.cn-beijing.volces.com/api/v3';
    private const API_PATH         = '/chat/completions';
    // 2025 在线公共模型;旧的 doubao-pro-32k 已下线。
    // 实际生产环境推荐用户在后台自填:
    //   - 推理接入点 ID(自建,如 ep-2025xxx-xxxxx)
    //   - 公共模型 ID(如 doubao-1-5-pro-32k-250115 / doubao-1-5-vision-pro-250328)
    private const DEFAULT_MODEL    = 'doubao-1-5-lite-32k-250115';
    private const TIMEOUT          = 30;

    private string $apiKey;
    private string $baseUrl;
    private string $model;

    public function __construct(string $apiKey, string $baseUrl = '', string $model = '')
    {
        $this->apiKey  = $apiKey;
        $this->baseUrl = self::normalizeBaseUrl($baseUrl);
        $this->model   = $model ?: self::DEFAULT_MODEL;
    }

    /**
     * 容错处理 baseUrl — 用户常误填完整 endpoint 路径(如 .../api/v3/responses),
     * 在这里统一剥离已知 endpoint 后缀,只保留到 /api/v3
     */
    private static function normalizeBaseUrl(string $url): string
    {
        $url = rtrim($url ?: self::DEFAULT_BASE_URL, '/');
        // 已知 endpoint 后缀(从最长到最短匹配,避免误剥)
        $suffixes = [
            '/chat/completions',
            '/responses',
            '/embeddings',
            '/images/generations',
        ];
        foreach ($suffixes as $sfx) {
            if (str_ends_with($url, $sfx)) {
                $url = substr($url, 0, -strlen($sfx));
                break;
            }
        }
        return rtrim($url, '/');
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
        // TODO P4.1: 实装 SSE 流式读取
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
        // 豆包多模态兼容 OpenAI vision 格式
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
        return !empty($this->apiKey);
    }

    public function getName(): string
    {
        return 'doubao';
    }

    // ─────────────────────── private ────────────────────────────

    private function buildPayload(string $system, string $user, array $options, bool $stream): array
    {
        return [
            'model'       => $options['model'] ?? $this->model,
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
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
            Yii::error("DouBaoProvider curl error: {$error}");
            throw new \RuntimeException("豆包请求失败：{$error}");
        }
        if ($code !== 200) {
            Yii::error("DouBaoProvider HTTP {$code}: {$body}");
            throw new \RuntimeException("豆包返回异常（HTTP {$code}）");
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('豆包返回非 JSON 格式');
        }
        return $data;
    }
}
