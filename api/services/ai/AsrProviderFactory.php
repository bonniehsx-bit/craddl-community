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
 * ASR 供应商工厂 —— 复用 AiProviderFactory 的商城级配置存储（同一个 ai_setting key），
 * 只是多读 asr_provider / asr_secret_id / asr_secret_key 三个字段。
 *
 * 驱动清单来自 AsrProviderRegistry（核心内置 + 插件通过 Plugin::getAsrProviders() 扩展），
 * 本工厂只负责「读配置 → 交给注册表实例化」，不认识任何具体供应商。
 */
class AsrProviderFactory
{
    /** 当前商城配置的 ASR 驱动 */
    public static function create(int $mallId): AsrProviderInterface
    {
        $s = AiProviderFactory::setting($mallId);
        return self::build((string)($s['asr_provider'] ?? 'tencent'), $mallId);
    }

    /**
     * 按驱动 key 构建，密钥取当前商城配置。
     *
     * 之所以按 key 单独构建而不是只有 create()：查询任务状态时必须用「提交任务那一刻」
     * 记录下来的驱动 key（任务 ID 是驱动私有的），不能用「查询这一刻」的默认驱动——
     * 如果两次之间管理员切换了供应商，用错驱动去查会直接查不到任务。
     */
    public static function build(string $key, int $mallId): AsrProviderInterface
    {
        $s = AiProviderFactory::setting($mallId);
        return AsrProviderRegistry::make(
            $key,
            (string)($s['asr_secret_id'] ?? ''),
            (string)($s['asr_secret_key'] ?? '')
        );
    }

    /** 可选供应商清单（后台下拉框用） */
    public static function providers(): array
    {
        return AsrProviderRegistry::options();
    }
}
