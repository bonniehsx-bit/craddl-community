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
 * 文生图供应商工厂 —— 复用 AiProviderFactory 的商城级配置存储（同一个 ai_setting key），
 * 只是多读 image_provider / image_api_key / image_base_url / image_model 四个字段。
 *
 * 为什么不复用 ai_api_key：火山的生图要单独开一个推理接入点（endpoint id），
 * 与文本接入点不是同一个；密钥虽常相同但接入点必然不同，共用一份配置必然出错。
 *
 * 驱动清单来自 ImageProviderRegistry（核心内置 + 插件扩展），
 * 本工厂只负责「读配置 → 交给注册表实例化」，不认识任何具体供应商。
 */
class ImageProviderFactory
{
    /** 当前商城配置的文生图驱动 */
    public static function create(int $mallId): ImageProviderInterface
    {
        $s = AiProviderFactory::setting($mallId);
        return self::build(
            (string)($s['image_provider']  ?? 'mock'),
            (string)($s['image_api_key']   ?? ''),
            (string)($s['image_base_url']  ?? ''),
            (string)($s['image_model']     ?? '')
        );
    }

    /**
     * 按显式参数构建（供后台「测试连通性」用未保存的表单数据）
     */
    public static function build(string $provider, string $apiKey, string $baseUrl, string $model): ImageProviderInterface
    {
        return ImageProviderRegistry::make($provider, $apiKey, $baseUrl, $model);
    }

    /** 可选供应商清单（后台下拉框用） */
    public static function providers(): array
    {
        return ImageProviderRegistry::options();
    }
}
