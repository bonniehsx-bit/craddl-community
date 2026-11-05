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
 * 文生图（Text-to-Image）驱动接口。
 *
 * ── 为什么单开一套接口，而不是往 AiProviderInterface 里加方法 ──────────────
 * AiProviderInterface 已有 4 个实现（mock/deepseek/doubao/claude），加抽象方法会
 * 全部编译不过；而这几家里真正能生图的只有火山，其余强行实现只能抛"不支持"。
 * 更关键的是**密钥维度不同** —— 火山的生图要单独开推理接入点（endpoint id），
 * 和文本接入点不是同一个，塞进同一份配置里迟早混。
 *
 * 所以照搬 ASR 的做法（AsrProviderInterface 也是单开一套 + 独立密钥），
 * 核心只认接口，谁能生图谁来注册。
 *
 * ── 返回值约定 ──────────────────────────────────────────────────────────
 * 必须返回**本站可长期访问的 URL**，不是供应商的临时地址。
 * 火山/OpenAI 这类返回的 URL 有效期只有几十分钟到一天，直接存库的话，
 * 用户第二天打开绘本就是一片裂图。实现方负责转存后再返回。
 */
interface ImageProviderInterface
{
    /**
     * 按提示词生成一张图。
     *
     * @param  string $prompt  完整提示词（调用方负责拼装风格/角色特征等前后缀）
     * @param  array  $options 扩展参数：width / height / negative_prompt / steps 等，
     *                         各 provider 自行解读，不支持的忽略即可
     * @return string 转存到本站后的图片 URL
     * @throws \RuntimeException 未配置 / 网络失败 / 供应商拒绝（含内容安全拦截）
     */
    public function generateImage(string $prompt, array $options = []): string;

    /**
     * 驱动是否可用（密钥与接入点已配置），不做网络探活。
     */
    public function isAvailable(): bool;

    /**
     * 驱动标识名（用于日志 / 前端展示）。例：'mock' | 'doubao'
     */
    public function getName(): string;
}
