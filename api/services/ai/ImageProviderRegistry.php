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

use app\models\Plugin as PluginModel;
use app\services\ai\providers\DouBaoImageProvider;
use app\services\ai\providers\ImageMockProvider;
use Yii;

/**
 * 文生图驱动注册表（核心）—— 内置驱动 + 各插件 Plugin::getImageProviders() 声明的驱动。
 *
 * 与 AiProviderRegistry / AsrProviderRegistry 同范式：核心只认接口不认具体供应商，
 * 新增一家生图只需实现 ImageProviderInterface 并声明进来，**工厂与配置页无需改动**。
 *
 * 驱动项结构：['key'=>'doubao', 'label'=>'字节豆包(火山ARK)', 'class'=>..., 'plugin'=>'']
 * key 约定：内置驱动用裸名；插件扩展的建议带插件前缀防跨插件重名互相覆盖。
 */
class ImageProviderRegistry
{
    /** @var array|null 进程内缓存 */
    private static $cache = null;

    private const BUILTIN = [
        ['key' => 'mock',   'label' => '未配置（不可用，调用即报错）', 'class' => ImageMockProvider::class],
        ['key' => 'doubao', 'label' => '字节豆包（火山引擎 ARK）',     'class' => DouBaoImageProvider::class],
    ];

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $list = [];
        foreach (self::BUILTIN as $d) {
            $list[$d['key']] = $d + ['plugin' => ''];
        }

        // 插件扩展的驱动：不覆盖内置，同 key 以先注册的为准
        foreach (PluginModel::find()->where(['is_delete' => 0])->all() as $item) {
            $class = '\\app\\plugins\\' . $item->name . '\\Plugin';
            if (!class_exists($class)) {
                continue;
            }
            try {
                $plugin = new $class();
                foreach ($plugin->getImageProviders() as $d) {
                    $key = (string)($d['key'] ?? '');
                    $cls = (string)($d['class'] ?? '');
                    if ($key === '' || $cls === '' || isset($list[$key])) {
                        continue;
                    }
                    if (!class_exists($cls) || !is_subclass_of($cls, ImageProviderInterface::class)) {
                        Yii::warning("[ImageProviderRegistry] 插件 {$item->name} 声明的驱动 {$key} 无效：{$cls} 未实现 ImageProviderInterface");
                        continue;
                    }
                    $list[$key] = [
                        'key'    => $key,
                        'label'  => (string)($d['label'] ?? $key),
                        'class'  => $cls,
                        'plugin' => $item->name,
                    ];
                }
            } catch (\Throwable $e) {
                Yii::warning("[ImageProviderRegistry] 插件 {$item->name} getImageProviders 失败: " . $e->getMessage());
            }
        }

        return self::$cache = $list;
    }

    /** 后台下拉框用的选项列表 */
    public static function options(): array
    {
        $out = [];
        foreach (self::all() as $d) {
            $out[] = ['value' => $d['key'], 'label' => $d['label']];
        }
        return $out;
    }

    public static function exists(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    /**
     * 实例化驱动；key 未注册时回落到 Mock（Mock 的 isAvailable() 恒 false，
     * 调用方会因此拿到明确异常，而不是悄悄拿到一张占位图当成功）
     */
    public static function make(string $key, string $apiKey = '', string $baseUrl = '', string $model = ''): ImageProviderInterface
    {
        $d = self::all()[$key] ?? null;
        if (!$d) {
            if ($key !== '' && $key !== 'mock') {
                Yii::warning("[ImageProviderRegistry] 未注册的文生图驱动 [{$key}]，已回落到 Mock");
            }
            return new ImageMockProvider();
        }

        $cls = $d['class'];
        return new $cls($apiKey, $baseUrl, $model);
    }

    /** 测试用：清空进程内缓存 */
    public static function flush(): void
    {
        self::$cache = null;
    }
}
