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

use app\helpers\HttpHelper;
use app\services\ai\ImageProviderInterface;
use GuzzleHttp\Client;
use Yii;

/**
 * 字节豆包（火山引擎 ARK）文生图驱动。
 *
 * 接口：POST {baseUrl}/images/generations，OpenAI 兼容格式。
 * model 填**生图专用的推理接入点 ID**（ep-xxxxxxxx），与文本接入点不是同一个。
 *
 * ⚠️ 火山返回的图片 URL 有效期很短（小时级），直接存库第二天就是裂图。
 *    所以这里一律转存到本站再返回；转存失败视为整次生成失败，
 *    宁可让上游走失败退款，也不能把一个明天就失效的地址写进用户的绘本里。
 */
class DouBaoImageProvider implements ImageProviderInterface
{
    private const DEFAULT_BASE_URL = 'https://ark.cn-beijing.volces.com/api/v3';
    private const API_PATH = '/images/generations';

    /** 生图比文本慢得多，单张 30~60s 是常态 */
    private const TIMEOUT = 120;

    private string $apiKey;
    private string $baseUrl;
    private string $model;
    private $client;

    public function __construct(string $apiKey = '', string $baseUrl = '', string $model = '')
    {
        $this->apiKey  = trim($apiKey);
        $this->baseUrl = self::normalizeBaseUrl($baseUrl);
        $this->model   = trim($model);
    }

    /**
     * 容错处理 baseUrl —— 用户常把完整 endpoint 路径（.../api/v3/images/generations）
     * 整条粘进来，这里统一削回到 /api/v3。与 DouBaoProvider 同款处理。
     */
    private static function normalizeBaseUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return self::DEFAULT_BASE_URL;
        }
        $url = rtrim($url, '/');
        foreach (['/images/generations', '/chat/completions', '/responses'] as $tail) {
            if (substr($url, -strlen($tail)) === $tail) {
                $url = substr($url, 0, -strlen($tail));
                break;
            }
        }
        return rtrim($url, '/');
    }

    public function generateImage(string $prompt, array $options = []): string
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException('火山文生图未配置完整（需要 API Key + 生图接入点 ID）');
        }

        // 负面提示词：把水印/文字/多手指这类高频废片挡在外面。
        // 调用方可以整条覆盖，但默认值要保证"不配也能出能看的图"。
        $negative = $options['negative_prompt']
            ?? 'nsfw, watermark, text, signature, logo, username, worst quality, low quality, bad anatomy, deformed hands, extra fingers';

        $payload = [
            'model'               => $options['model'] ?? $this->model,
            'prompt'              => $prompt,
            'width'               => (int)($options['width'] ?? 1024),
            'height'              => (int)($options['height'] ?? 1024),
            'num_inference_steps' => (int)($options['steps'] ?? 30),
            'negative_prompt'     => $negative,
        ];

        $body = $this->doPost($this->baseUrl . self::API_PATH, $payload);

        $remoteUrl = $body['data'][0]['url'] ?? '';
        if ($remoteUrl === '') {
            $msg = $body['error']['message'] ?? json_encode($body, JSON_UNESCAPED_UNICODE);
            throw new \RuntimeException('生图接口未返回图片: ' . mb_substr((string)$msg, 0, 200));
        }

        // 转存到本站。转存失败**必须抛**，不能把短效地址返回去（见类注释）
        $localUrl = HttpHelper::saveToLocalAndGetUrl($remoteUrl);
        if (!$localUrl || $localUrl === $remoteUrl) {
            throw new \RuntimeException('生成的图片转存失败（火山原始地址是短效的，不能直接使用）');
        }

        return $localUrl;
    }

    public function isAvailable(): bool
    {
        return $this->apiKey !== '' && $this->model !== '';
    }

    public function getName(): string
    {
        return 'doubao';
    }

    private function doPost(string $url, array $payload): array
    {
        if ($this->client === null) {
            $this->client = new Client([
                'timeout' => self::TIMEOUT,
                'verify'  => false,
            ]);
        }

        try {
            $resp = $this->client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
            ]);
            $raw = $resp->getBody()->getContents();
        } catch (\Throwable $e) {
            Yii::error('[DouBaoImageProvider] 请求失败: ' . $e->getMessage(), 'ai');
            throw new \RuntimeException('文生图请求失败: ' . $e->getMessage());
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException('文生图返回非 JSON: ' . mb_substr($raw, 0, 200));
        }
        return $data;
    }
}
