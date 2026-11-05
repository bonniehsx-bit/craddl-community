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

/**
 * AI 提供商统一接口
 *
 * 工厂模式抽象层，未来接入豆包/DeepSeek/Claude 等只需实现此接口。
 * P4.1 AI 问答功能实装时，通过 AiProviderFactory::create() 获取具体实例。
 */
interface AiProviderInterface
{
    /**
     * 一次性对话（阻塞等待完整回复）
     *
     * @param string $systemPrompt 系统提示词（角色/背景设定）
     * @param string $userMessage  用户输入消息
     * @param array  $options      扩展选项（temperature、max_tokens 等，各 provider 自行解读）
     * @return string              模型回复的完整文本
     * @throws \RuntimeException  调用失败时抛出异常
     */
    public function chat(string $systemPrompt, string $userMessage, array $options = []): string;

    /**
     * 流式对话（SSE/chunk 回调，同时返回完整字符串）
     *
     * @param string        $systemPrompt
     * @param string        $userMessage
     * @param array         $options
     * @param callable|null $onChunk  每收到一个 chunk 时回调：function(string $chunk): void
     * @return string                 完整回复（所有 chunk 拼接）
     * @throws \RuntimeException
     */
    public function streamChat(
        string $systemPrompt,
        string $userMessage,
        array $options = [],
        ?callable $onChunk = null
    ): string;

    /**
     * 图片分析（Vision 能力）
     *
     * 将图片与文字提示发送给多模态模型，获取分析结果。
     * 不支持 Vision 的 provider 应抛出 \RuntimeException('不支持图片分析')。
     *
     * @param string $systemPrompt  系统提示词
     * @param string $userMessage   文字提示（描述分析任务）
     * @param string $base64Image   纯 base64 编码的图片数据（不含 data URI 前缀）
     * @param string $mimeType      图片 MIME 类型，默认 image/jpeg
     * @param array  $options       扩展选项
     * @return string               分析结果文本
     * @throws \RuntimeException    不支持或调用失败时抛出异常
     */
    public function analyzeImage(
        string $systemPrompt,
        string $userMessage,
        string $base64Image,
        string $mimeType = 'image/jpeg',
        array $options = []
    ): string;

    /**
     * 提供商是否可用（API Key 已配置且格式有效）
     *
     * 不做网络探活，仅检查本地配置。
     */
    public function isAvailable(): bool;

    /**
     * 提供商标识名（用于日志 / 前端展示）
     * 例：'mock' | 'deepseek' | 'doubao' | 'claude'
     */
    public function getName(): string;
}
