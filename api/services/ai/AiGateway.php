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

namespace app\services\ai;

use app\helpers\ServerHelper;
use app\models\AiCallLog;
use Yii;

/**
 * 美业 AI 网关服务
 *
 * 统一封装四类 AI 能力：皮肤检测（Vision）、语音转写（ASR）、LLM 文本生成、文生图（Image）。
 * LLM/Vision 供应商失败时自动降级到 fallback，全部失败则抛出异常并记 STATUS_FAILED。
 * ASR 是提交/查询两段式（asrSubmit/asrQuery），不是一次调用拿到文本——原因见
 * AsrProviderInterface 的注释（Go 调度器 FastCGI 派发硬超时 30 秒，不能阻塞轮询）。
 *
 * 调用示例：
 *   $scores = AiGateway::skinDetect('https://oss.example.com/face.jpg', $mallId, $ctx);
 *   $task   = AiGateway::asrSubmit($audioUrl, $mallId, $ctx);           // 提交，立即返回 task_id
 *   $status = AiGateway::asrQuery($task['provider'], $task['task_id'], $mallId, $ctx); // 之后错峰查询
 *   $brief  = AiGateway::llmGenerate($prompt, 800, $mallId, $ctx);
 *   $imgUrl = AiGateway::imageGenerate($prompt, $mallId, $ctx);        // 无 fallback，失败即抛
 */
class AiGateway
{
    // ──────────────────────────────────────────────────────────────────────
    // 皮肤检测（Vision 多模态）
    // ──────────────────────────────────────────────────────────────────────

    /**
     * 分析一张皮肤照片，返回五维评分数组
     *
     * @param  string $imageUrl 可公网访问的图片 URL（须过域名白名单）
     * @param  int    $mallId
     * @param  array  $ctx      审计上下文，见 self::ctx()
     * @return array  ['oil'=>int, 'hydration'=>int, 'pores'=>int, 'spots'=>int, 'wrinkles'=>int]
     * @throws \RuntimeException 所有供应商均失败时抛出
     */
    public static function skinDetect(
        string $imageUrl,
        int $mallId,
        array $ctx = []
    ): array {
        $ctx = self::ctx($ctx, AiCallLog::ABILITY_VISION);
        $startMs = (int)(microtime(true) * 1000);

        // 下载图片 → base64（安全拉取：域名白名单 + 私网拦截 + 限时限体积）
        [$imageData, $mime] = self::fetchImageSafely($imageUrl, $mallId);
        $base64 = base64_encode($imageData);

        $systemPrompt = '你是专业美容皮肤分析 AI。请分析图中人物的面部皮肤状态，严格按 JSON 输出，不要输出其他内容。';
        $userMessage  = '请对图中皮肤进行评分（0-100整数），返回格式：{"oil":值,"hydration":值,"pores":值,"spots":值,"wrinkles":值}';
        $options      = ['max_tokens' => 200, 'temperature' => 0.2];

        [$result, $provider, $tokensIn, $tokensOut, $status, $errMsg] = self::tryWithFallback(
            $mallId,
            function ($p) use ($systemPrompt, $userMessage, $base64, $mime, $options) {
                return $p->analyzeImage($systemPrompt, $userMessage, $base64, $mime, $options);
            }
        );

        $durationMs = (int)(microtime(true) * 1000) - $startMs;

        self::writeLog([
            'mall_id'     => $mallId,
            'mch_id'      => $ctx['mch_id'],
            'plugin'      => $ctx['plugin'],
            'ability'     => $ctx['ability'],
            'category'    => $ctx['category'],
            'provider'    => $provider,
            'biz_id'      => $ctx['biz_id'],
            'input_size'  => strlen($imageData),
            'tokens_in'   => $tokensIn,
            'tokens_out'  => $tokensOut,
            'duration_ms' => $durationMs,
            'status'      => $status,
            'error_msg'   => $errMsg,
        ]);

        if ($status === AiCallLog::STATUS_FAILED) {
            throw new \RuntimeException("皮肤检测失败：{$errMsg}");
        }

        return self::parseScores($result);
    }

    // ──────────────────────────────────────────────────────────────────────
    // 语音转写（ASR — 提交/查询两段式，见 AsrProviderInterface）
    // ──────────────────────────────────────────────────────────────────────

    /**
     * 提交一段录音的转写任务（立即返回，不等待结果）。
     *
     * @param  string $audioUrl 音频文件的公网可访问 URL
     * @param  int    $mallId
     * @param  array  $ctx      审计上下文，见 self::ctx()
     * @return array  ['provider'=>驱动key, 'task_id'=>供应商任务ID]，query 时原样传回
     * @throws \RuntimeException ASR 未配置或提交失败
     */
    public static function asrSubmit(string $audioUrl, int $mallId, array $ctx = []): array
    {
        $ctx      = self::ctx($ctx, AiCallLog::ABILITY_ASR);
        $provider = AsrProviderFactory::create($mallId);

        if (!$provider->isAvailable()) {
            throw new \RuntimeException('语音识别未配置（ASR 驱动密钥缺失）');
        }

        try {
            $taskId = $provider->submit($audioUrl);
        } catch (\Throwable $e) {
            self::writeLog([
                'mall_id' => $mallId, 'mch_id' => $ctx['mch_id'], 'plugin' => $ctx['plugin'],
                'ability' => $ctx['ability'], 'category' => $ctx['category'], 'provider' => $provider->getName(),
                'biz_id' => $ctx['biz_id'], 'input_size' => 0, 'tokens_in' => 0, 'tokens_out' => 0,
                'duration_ms' => 0, 'status' => AiCallLog::STATUS_FAILED, 'error_msg' => $e->getMessage(),
            ]);
            throw $e;
        }

        return ['provider' => $provider->getName(), 'task_id' => $taskId];
    }

    /**
     * 查询转写任务状态（立即返回，不阻塞等待；未完成时调用方需自行错峰重查）。
     *
     * 审计日志只在任务到达终态（done/failed）时写一条，避免每次轮询都灌一条 pending 记录。
     *
     * @param  string $providerKey    asrSubmit() 返回的 provider（必须原样传回，见 AsrProviderFactory::build 注释）
     * @param  string $taskId         asrSubmit() 返回的 task_id
     * @param  int    $mallId
     * @param  array  $ctx            审计上下文，见 self::ctx()
     * @param  int    $submittedAtMs  任务提交时刻（毫秒时间戳，用于终态时计算 duration_ms；不传则记 0）
     * @return array  ['status'=>'pending'|'done'|'failed', 'text'=>string, 'error_msg'=>string]
     * @throws \RuntimeException 查询请求本身失败（区别于"任务失败"，调用方通常按同一逻辑处理即可）
     */
    public static function asrQuery(
        string $providerKey,
        string $taskId,
        int $mallId,
        array $ctx = [],
        int $submittedAtMs = 0
    ): array {
        $ctx      = self::ctx($ctx, AiCallLog::ABILITY_ASR);
        $provider = AsrProviderFactory::build($providerKey, $mallId);
        $result   = $provider->query($taskId);

        if ($result['status'] !== 'pending') {
            $durationMs = $submittedAtMs > 0 ? ((int)(microtime(true) * 1000) - $submittedAtMs) : 0;
            self::writeLog([
                'mall_id'     => $mallId,
                'mch_id'      => $ctx['mch_id'],
                'plugin'      => $ctx['plugin'],
                'ability'     => $ctx['ability'],
                'category'    => $ctx['category'],
                'provider'    => $providerKey,
                'biz_id'      => $ctx['biz_id'],
                'input_size'  => 0,
                'tokens_in'   => 0,
                'tokens_out'  => mb_strlen($result['text'] ?? ''),
                'duration_ms' => $durationMs,
                'status'      => $result['status'] === 'done' ? AiCallLog::STATUS_OK : AiCallLog::STATUS_FAILED,
                'error_msg'   => $result['error_msg'] ?? '',
            ]);
        }

        return $result;
    }

    // ──────────────────────────────────────────────────────────────────────
    // LLM 文本生成
    // ──────────────────────────────────────────────────────────────────────

    /**
     * 调用大语言模型生成文本
     *
     * @param  string $prompt      完整的用户 prompt（调用方负责拼装系统 + 用户内容）
     * @param  int    $maxTokens
     * @param  int    $mallId
     * @param  array  $ctx         审计上下文，见 self::ctx()
     * @return string 模型回复文本
     * @throws \RuntimeException 所有供应商均失败时抛出
     */
    public static function llmGenerate(
        string $prompt,
        int $maxTokens = 800,
        int $mallId = 0,
        array $ctx = []
    ): string {
        $ctx = self::ctx($ctx, AiCallLog::ABILITY_TEXT);
        $startMs = (int)(microtime(true) * 1000);
        $options = ['max_tokens' => $maxTokens, 'temperature' => 0.7];

        [$result, $provider, $tokensIn, $tokensOut, $status, $errMsg] = self::tryWithFallback(
            $mallId,
            function ($p) use ($prompt, $options) {
                return $p->chat('', $prompt, $options);
            }
        );

        $durationMs = (int)(microtime(true) * 1000) - $startMs;

        self::writeLog([
            'mall_id'     => $mallId,
            'mch_id'      => $ctx['mch_id'],
            'plugin'      => $ctx['plugin'],
            'ability'     => $ctx['ability'],
            'category'    => $ctx['category'],
            'provider'    => $provider,
            'biz_id'      => $ctx['biz_id'],
            'input_size'  => mb_strlen($prompt),
            'tokens_in'   => $tokensIn,
            'tokens_out'  => $tokensOut,
            'duration_ms' => $durationMs,
            'status'      => $status,
            'error_msg'   => $errMsg,
        ]);

        if ($status === AiCallLog::STATUS_FAILED) {
            throw new \RuntimeException("AI 生成失败：{$errMsg}");
        }

        return $result;
    }

    // ──────────────────────────────────────────────────────────────────────
    // 文生图（Text-to-Image）
    // ──────────────────────────────────────────────────────────────────────

    /**
     * 按提示词生成一张图，返回**转存到本站后**的 URL。
     *
     * 与 llmGenerate 的关键差别：**没有 fallback**。
     * 文本降级到备用模型，用户至多觉得文案平庸；生图如果降级到一张占位图，
     * 用户拿到的是一本花钱买来的空白绘本 —— 那还不如明确失败，让业务侧走退款。
     * 所以这里驱动不可用/调用失败一律抛异常，由调用方决定怎么补偿。
     *
     * @param  string $prompt   完整提示词
     * @param  int    $mallId
     * @param  array  $ctx      审计上下文，见 self::ctx()
     * @param  array  $options  width/height/steps/negative_prompt 等，见 ImageProviderInterface
     * @return string 本站图片 URL
     * @throws \RuntimeException 未配置或生成失败
     */
    public static function imageGenerate(
        string $prompt,
        int $mallId,
        array $ctx = [],
        array $options = []
    ): string {
        $ctx     = self::ctx($ctx, AiCallLog::ABILITY_IMAGE);
        $startMs = (int)(microtime(true) * 1000);

        $provider = ImageProviderFactory::create($mallId);
        $status   = AiCallLog::STATUS_OK;
        $errMsg   = '';
        $url      = '';

        try {
            if (!$provider->isAvailable()) {
                throw new \RuntimeException('文生图未配置（后台「商城管理 → AI 配置」）');
            }
            $url = $provider->generateImage($prompt, $options);
        } catch (\Throwable $e) {
            $status = AiCallLog::STATUS_FAILED;
            $errMsg = $e->getMessage();
        }

        self::writeLog([
            'mall_id'     => $mallId,
            'mch_id'      => $ctx['mch_id'],
            'plugin'      => $ctx['plugin'],
            'ability'     => $ctx['ability'],
            'category'    => $ctx['category'],
            'provider'    => $provider->getName(),
            'biz_id'      => $ctx['biz_id'],
            'input_size'  => mb_strlen($prompt),
            'tokens_in'   => 0,
            'tokens_out'  => 0,
            'duration_ms' => (int)(microtime(true) * 1000) - $startMs,
            'status'      => $status,
            'error_msg'   => $errMsg,
        ]);

        if ($status === AiCallLog::STATUS_FAILED) {
            throw new \RuntimeException("图片生成失败：{$errMsg}");
        }

        return $url;
    }

    // ──────────────────────────────────────────────────────────────────────
    // 内部：主/降级供应商尝试链
    // ──────────────────────────────────────────────────────────────────────

    /**
     * 按主 → 降级顺序尝试，返回 [result, providerName, tokensIn, tokensOut, status, errMsg]
     */
    private static function tryWithFallback(int $mallId, callable $call): array
    {
        $primary  = AiProviderFactory::create($mallId);
        $fallback = AiProviderFactory::createFallback($mallId);
        $providers = [$primary, $fallback];

        $lastErr  = '';
        $status   = AiCallLog::STATUS_OK;
        foreach ($providers as $idx => $provider) {
            try {
                $text = $call($provider);
                // 粗估 token（4 chars ≈ 1 token，仅用于成本统计）
                $tokensOut = (int)(mb_strlen($text) / 4);
                if ($idx > 0) {
                    $status = AiCallLog::STATUS_FALLBACK;
                }
                return [$text, $provider->getName(), 0, $tokensOut, $status, ''];
            } catch (\Throwable $e) {
                $lastErr = $e->getMessage();
                Yii::warning("[AiGateway] AI provider {$provider->getName()} failed: {$lastErr}", 'ai');
            }
        }

        return ['', 'none', 0, 0, AiCallLog::STATUS_FAILED, $lastErr];
    }

    // ──────────────────────────────────────────────────────────────────────
    // 内部：工具方法
    // ──────────────────────────────────────────────────────────────────────

    /**
     * 归一化审计上下文。
     *
     * @param array  $ctx     ['plugin'=>来源插件, 'category'=>业务细分, 'mch_id'=>门店, 'biz_id'=>关联业务ID]
     * @param string $ability 能力大类，由各能力方法自己传入，调用方无需关心
     */
    private static function ctx(array $ctx, string $ability): array
    {
        return [
            'plugin'   => (string)($ctx['plugin'] ?? ''),
            'category' => (string)($ctx['category'] ?? $ability),
            'mch_id'   => (int)($ctx['mch_id'] ?? 0),
            'biz_id'   => (int)($ctx['biz_id'] ?? 0),
            'ability'  => $ability,
        ];
    }

    private static function writeLog(array $attrs): void
    {
        try {
            $log = new AiCallLog();
            foreach ($attrs as $k => $v) {
                $log->$k = $v;
            }
            $log->save(false);
        } catch (\Throwable $e) {
            Yii::error('[AiGateway] writeLog failed: ' . $e->getMessage(), 'ai');
        }
    }

    private static function parseScores(string $text): array
    {
        $defaults = ['oil' => 50, 'hydration' => 50, 'pores' => 50, 'spots' => 50, 'wrinkles' => 50];

        // 提取 JSON（模型可能在回复中夹杂说明文字）
        if (preg_match('/\{[^}]+\}/', $text, $m)) {
            $data = json_decode($m[0], true);
            if (is_array($data)) {
                foreach ($defaults as $k => $_) {
                    if (isset($data[$k])) {
                        $defaults[$k] = max(0, min(100, (int)$data[$k]));
                    }
                }
            }
        }
        return $defaults;
    }

    // ──────────────────────────────────────────────────────────────────────
    // 内部：图片安全拉取（防 SSRF）
    // ──────────────────────────────────────────────────────────────────────

    /** 图片下载上限（字节） */
    private const IMAGE_MAX_BYTES = 10 * 1024 * 1024;

    /** 图片下载超时（秒） */
    private const IMAGE_TIMEOUT = 10;

    /**
     * 拉取待检测的图片。
     *
     * image_url 由客户端传入，直接下载等于把服务器当代理用（SSRF）。四道防线：
     *   ① 只收 http/https，且域名必须在白名单内（默认仅本站，可后台加 CDN/OSS 域名）
     *   ② 解析出的所有 IP 不得落在私网 / 环回 / 保留段，拦住探测内网
     *   ③ curl 固定住已校验的 IP（防 DNS rebinding）+ 禁跳转 + 限时 + 限体积
     *   ④ 落地内容必须真的是图片，MIME 以实际内容为准（不再按扩展名猜）
     *
     * @return array [二进制内容, MIME]
     * @throws \RuntimeException 任一环节不通过
     */
    private static function fetchImageSafely(string $imageUrl, int $mallId): array
    {
        $parts  = parse_url($imageUrl);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host   = (string)($parts['host'] ?? '');

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new \RuntimeException('图片地址不合法');
        }

        $allowType = self::hostAllowType($host, $mallId);
        if ($allowType === '') {
            throw new \RuntimeException('图片来源域名不在允许范围内');
        }

        // 本站域名豁免私网校验：内网部署 / 本地开发时本站就解析到私网地址，
        // 而「让服务器访问它自己」不构成 SSRF。外部白名单域名仍须指向公网，
        // 防止有人把自己域名的 A 记录指到内网来借道探测。
        $ip = self::resolveIp($host, $allowType === 'self');
        if ($ip === '') {
            throw new \RuntimeException('图片地址解析失败或指向内网');
        }

        $port = (int)($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        $buffer = '';
        $ch = curl_init($imageUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,                    // 禁跳转，防绕过白名单
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => self::IMAGE_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_RESOLVE        => ["{$host}:{$port}:{$ip}"], // 钉死已校验 IP
            CURLOPT_WRITEFUNCTION  => function ($_ch, $chunk) use (&$buffer) {
                $buffer .= $chunk;
                // 超限返回 0 触发 curl 中断，避免大文件打满内存
                return strlen($buffer) > self::IMAGE_MAX_BYTES ? 0 : strlen($chunk);
            },
        ]);
        curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if (strlen($buffer) > self::IMAGE_MAX_BYTES) {
            throw new \RuntimeException('图片体积超过 10MB');
        }
        if ($curlErr !== '') {
            throw new \RuntimeException("图片下载失败：{$curlErr}");
        }
        if ($httpCode !== 200 || $buffer === '') {
            throw new \RuntimeException("无法获取图片（HTTP {$httpCode}）");
        }

        $info = @getimagesizefromstring($buffer);
        if ($info === false || empty($info['mime'])) {
            throw new \RuntimeException('该地址不是有效图片');
        }

        return [$buffer, (string)$info['mime']];
    }

    /**
     * 域名的许可类型。
     *
     *   'self'  本站域名（.env APP_URL / 请求域名）
     *   'extra' 后台配置的 image_host_whitelist（换行或逗号分隔，支持 *.example.com）
     *   ''      不允许
     */
    private static function hostAllowType(string $host, int $mallId): string
    {
        $host     = strtolower($host);
        $selfHost = strtolower((string)parse_url((string)ServerHelper::getHost(), PHP_URL_HOST));

        if ($selfHost !== '' && $host === $selfHost) {
            return 'self';
        }

        $setting = AiProviderFactory::setting($mallId);
        $extra   = (string)($setting['image_host_whitelist'] ?? '');
        foreach (preg_split('/[\s,;]+/', $extra) as $rule) {
            $rule = strtolower(trim($rule));
            if ($rule === '') {
                continue;
            }
            if ($rule === $host) {
                return 'extra';
            }
            // *.example.com —— 匹配任意子域，但不匹配裸域
            if (str_starts_with($rule, '*.') && str_ends_with($host, substr($rule, 1))) {
                return 'extra';
            }
        }
        return '';
    }

    /**
     * 解析域名为 IP；host 本身是 IP 时直接采用。
     *
     * @param bool $allowPrivate 是否允许私网地址（仅本站域名为 true）
     * @return string 通过校验的 IP，未通过返回空串
     */
    private static function resolveIp(string $host, bool $allowPrivate = false): string
    {
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : [];

        if (!$ips) {
            $v4 = gethostbynamel($host);
            if (is_array($v4)) {
                $ips = $v4;
            }
            $v6 = @dns_get_record($host, DNS_AAAA);
            foreach (is_array($v6) ? $v6 : [] as $rec) {
                if (!empty($rec['ipv6'])) {
                    $ips[] = $rec['ipv6'];
                }
            }
        }
        if (!$ips) {
            return '';
        }

        if (!$allowPrivate) {
            // 任一解析结果落在私网/保留段即整体拒绝（多 A 记录里藏内网 IP 的绕过手法）
            foreach ($ips as $ip) {
                $public = filter_var(
                    $ip,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                );
                if ($public === false) {
                    return '';
                }
            }
        }

        return (string)$ips[0];
    }
}
