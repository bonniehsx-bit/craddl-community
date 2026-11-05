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

use app\helpers\OptionHelper;
use app\services\ai\providers\MockProvider;

/**
 * 平台级 AI 提供商工厂
 *
 * AI 供应商配置是**商城级公共配置**，不属于任何单个插件：一个商城配一次密钥，
 * 所有需要 AI 能力的插件共用，避免每个插件各建一套配置页、各存一份 Key，
 * 导致同一个商城出现多份互相打架的供应商设置。
 *
 * 配置入口：后台「商城管理 → AI 配置」（controllers/mall/AiConfigController）
 * 存储：OptionHelper，key = self::OPTION_KEY，按 mall_id 维度
 *
 * 驱动清单来自 AiProviderRegistry（核心内置 + 插件通过 Plugin::getAiProviders() 扩展），
 * 本工厂只负责「读配置 → 交给注册表实例化」，不认识任何具体供应商。
 *
 * 主备双驱动：主供应商调用失败时，AiGateway 层自动切到降级供应商；
 * 未配置降级供应商时返回 MockProvider（返回模板内容，保证业务不中断）。
 */
class AiProviderFactory
{
    /** 配置存储键（商城级） */
    public const OPTION_KEY = 'ai_setting';

    /**
     * 主供应商
     */
    public static function create(int $mallId): AiProviderInterface
    {
        $s = self::setting($mallId);
        return self::build(
            $s['ai_provider'] ?? 'mock',
            $s['ai_api_key']  ?? '',
            $s['ai_base_url'] ?? '',
            $s['ai_model']    ?? ''
        );
    }

    /**
     * 降级供应商；未配置时返回 Mock（不抛异常，保证有兜底）
     */
    public static function createFallback(int $mallId): AiProviderInterface
    {
        $s = self::setting($mallId);
        $provider = $s['ai_fallback_provider'] ?? '';
        if (!$provider) {
            return new MockProvider();
        }
        return self::build($provider, $s['ai_fallback_api_key'] ?? '', '', '');
    }

    /**
     * 读商城级 AI 配置（整份）
     */
    public static function setting(int $mallId): array
    {
        $s = OptionHelper::get(self::OPTION_KEY, $mallId, []);
        return is_array($s) ? $s : [];
    }

    /**
     * 可选供应商清单（后台下拉框用）—— 内置 + 插件扩展，来自注册表
     */
    public static function providers(): array
    {
        return AiProviderRegistry::options();
    }

    /**
     * 按显式参数构建 Provider（供后台「测试连通性」用未保存的表单数据）
     *
     * 具体类由注册表解析 —— 新增供应商只需实现 AiProviderInterface 并注册，
     * 本工厂无需改动。未注册的 key 回落到 Mock。
     */
    public static function build(
        string $provider,
        string $apiKey,
        string $baseUrl,
        string $model
    ): AiProviderInterface {
        return AiProviderRegistry::make($provider, $apiKey, $baseUrl, $model);
    }
}
