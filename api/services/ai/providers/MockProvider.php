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

/**
 * Mock AI 提供商（开发 / 测试专用）
 *
 * 不调用任何外部 API，直接返回预设回复。
 * - 开发阶段默认使用此 provider，保证功能可演示、无需 API Key
 * - 生产环境可在后台设置页切换为真实 provider
 */
class MockProvider implements AiProviderInterface
{
    /** @var string 固定回复（可由外部注入，便于单元测试） */
    private string $reply;

    public function __construct(string $reply = '')
    {
        $this->reply = $reply ?: "👋 Mock AI：收到您的问题了！\n\n这是一个演示回复。"
            . "请在后台「商城设置 → AI 配置」中切换到真实 AI 提供商（DeepSeek / 豆包 / Claude）。";
    }

    public function chat(string $systemPrompt, string $userMessage, array $options = []): string
    {
        return $this->reply;
    }

    public function streamChat(
        string $systemPrompt,
        string $userMessage,
        array $options = [],
        ?callable $onChunk = null
    ): string {
        // 模拟分段输出（每 8 个字符一个 chunk）
        $full   = $this->reply;
        $chunks = str_split($full, 8);
        foreach ($chunks as $chunk) {
            if ($onChunk !== null) {
                $onChunk($chunk);
            }
        }
        return $full;
    }

    public function analyzeImage(
        string $systemPrompt,
        string $userMessage,
        string $base64Image,
        string $mimeType = 'image/jpeg',
        array $options = []
    ): string {
        // Mock：根据任务类型返回固定 JSON，方便前端联调
        $type = $options['analyze_type'] ?? 0;
        if ($type === 1) {
            // 便便颜色 Mock
            return json_encode([
                'label'        => '金黄色便',
                'color_hex'    => '#D4A017',
                'stool_color'  => 2,
                'status'       => 'normal',
                'status_label' => '正常',
                'advice'       => '金黄色便便是母乳宝宝的典型颜色，说明消化良好，营养吸收正常，无需担心。',
            ], JSON_UNESCAPED_UNICODE);
        }
        if ($type === 2) {
            // 辅食克数 Mock
            return json_encode([
                'label'         => '南瓜泥',
                'weight_g'      => 80,
                'calories_kcal' => 18,
                'status'        => 'normal',
                'advice'        => '估计重量约 80g，热量约 18kcal，适合 6~12 月龄宝宝作为辅食添加。',
            ], JSON_UNESCAPED_UNICODE);
        }
        return $this->reply;
    }

    public function isAvailable(): bool
    {
        return true; // Mock 永远可用
    }

    public function getName(): string
    {
        return 'mock';
    }
}
