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

namespace app\services;

use app\models\CommissionLog;
use Yii;

/**
 * 分佣业务类型注册表
 *
 * 架构目标：新增一种业务类型不再需要改 CommissionLog 常量与前端硬编码 map。
 *
 * 使用方式：
 *   // 1) 插件/业务模块在 listener 的 register() 里登记友好元数据（可选）
 *   CommissionTypeRegistry::register('referral', [
 *       'label'          => '推荐奖',
 *       'tag_type'       => 'success',      // element-ui tag 颜色
 *       'rate_label'     => '推荐奖比例',
 *       'rate_desc_mode' => 'commission',   // commission | platform_fee
 *       'group'          => 'custom',
 *   ]);
 *
 *   // 2) 写入 log 时 type 直接传字符串（不用常量）
 *   (new CommissionService())->log($uid, $amount, 'referral', ...);
 *
 *   // 3) 未登记的类型也能正常展示 —— DB 中出现即自动进下拉，label 用 type 字符串兜底
 *
 * 数据来源合并顺序（高→低）：register 登记 > 内置常量 > DB DISTINCT type
 */
class CommissionTypeRegistry
{
    /**
     * 运行时注册表 [type => config]
     * @var array
     */
    private static $registry = [];

    /**
     * 元数据默认值
     */
    const DEFAULT_META = [
        'label'          => '',
        'tag_type'       => '',           // '' | success | warning | danger | info
        'rate_label'     => '佣金比例',
        'rate_desc_mode' => 'commission', // commission: 直接展示佣金 / platform_fee: 服务费 = 原额 - 结算
        'group'          => 'custom',     // 业务分组，前端可按组归类
    ];

    /**
     * 注册/覆盖一种业务类型的展示元数据
     * @param string $type  业务类型字符串（写入 commission_log.type 的值）
     * @param array  $config 元数据
     */
    public static function register(string $type, array $config): void
    {
        $type = trim($type);
        if ($type === '') return;
        self::$registry[$type] = array_merge(self::DEFAULT_META, ['label' => $type], $config);
    }

    /**
     * 批量注册
     */
    public static function registerMany(array $map): void
    {
        foreach ($map as $type => $config) {
            self::register((string)$type, is_array($config) ? $config : ['label' => (string)$config]);
        }
    }

    /**
     * 获取一种类型的元数据（未登记返回兜底元数据）
     */
    public static function get(string $type): array
    {
        if (isset(self::$registry[$type])) {
            return self::$registry[$type];
        }
        $builtin = self::builtin();
        if (isset($builtin[$type])) {
            return array_merge(self::DEFAULT_META, $builtin[$type]);
        }
        // 兜底：未登记的类型用 type 字符串作为 label
        return array_merge(self::DEFAULT_META, ['label' => $type]);
    }

    /**
     * 获取所有已知类型
     * 合并顺序（后者覆盖前者）：内置 → 事件收集（listener 挂载） → 运行时 register() → DB DISTINCT 兜底
     *
     * 推荐做法：插件在自己的 listener::register() 中监听
     * CommissionTypeEvent::EVENT_COLLECT 事件并 add() 自己的类型元数据。
     * 这样插件不需要 import Registry，解耦更彻底。
     *
     * @param int|null $mallId 指定商城则扫描 DB；传 null 只返回注册表+内置
     * @return array [type => metaArray]
     */
    public static function getAll(?int $mallId = null): array
    {
        $all = [];

        // 1. 内置
        foreach (self::builtin() as $type => $meta) {
            $all[$type] = array_merge(self::DEFAULT_META, $meta);
        }

        // 2. 事件收集：各 listener 贡献的类型（核心扩展点）
        try {
            $event = new \app\events\CommissionTypeEvent();
            $event->mallId = $mallId;
            \Yii::$app->trigger(\app\events\CommissionTypeEvent::EVENT_COLLECT, $event);
            foreach ((array)$event->types as $type => $meta) {
                $all[$type] = array_merge(self::DEFAULT_META, is_array($meta) ? $meta : ['label' => (string)$meta]);
            }
        } catch (\Throwable $e) {
            \Yii::error('[CommissionTypeRegistry] 事件收集异常: ' . $e->getMessage());
        }

        // 3. 运行时主动 register()（优先级最高，可覆盖上述）
        foreach (self::$registry as $type => $meta) {
            $all[$type] = $meta;
        }

        // 4. DB 自发现兜底（未登记补 label=type）
        if ($mallId) {
            foreach (self::scanDbTypes($mallId) as $type) {
                if (!isset($all[$type])) {
                    $all[$type] = array_merge(self::DEFAULT_META, ['label' => $type, 'group' => 'unknown']);
                }
            }
        }
        return $all;
    }

    /**
     * 仅返回 [type => label] 映射（向后兼容 CommissionLog::getTypeMap 的返回格式）
     */
    public static function getTypeMap(?int $mallId = null): array
    {
        $out = [];
        foreach (self::getAll($mallId) as $type => $meta) {
            $out[$type] = $meta['label'] ?: $type;
        }
        return $out;
    }

    /**
     * 扫描 DB 中出现过的所有 type（缓存 5 分钟）
     */
    private static function scanDbTypes(int $mallId): array
    {
        $cacheKey = 'commission_type_scan_' . $mallId;
        $cached = Yii::$app->cache->get($cacheKey);
        if (is_array($cached)) return $cached;

        try {
            $rows = CommissionLog::find()
                ->select('type')
                ->distinct()
                ->where(['mall_id' => $mallId, 'is_delete' => 0])
                ->column();
        } catch (\Throwable $e) {
            $rows = [];
        }
        $rows = array_values(array_filter(array_map('strval', $rows), fn($v) => $v !== ''));
        Yii::$app->cache->set($cacheKey, $rows, 300);
        return $rows;
    }

    /**
     * 核心内置类型：已清空。
     *
     * 所有类型（分销/区域/礼品券/经销商/品牌代理/手气拼团等）均在各自插件的
     * CommissionTypeListener 中通过事件机制登记，核心不持有任何业务类型元数据。
     *
     * 【新插件接入分润展示】只需在插件里新建一个 CommissionTypeListener 挂
     * CommissionTypeEvent::EVENT_COLLECT 即可，无需修改本文件。
     */
    private static function builtin(): array
    {
        return [];
    }

    /**
     * 刷新 DB 类型扫描缓存（新类型首次写入后可主动调用，或等 5 分钟自动过期）
     */
    public static function flushScanCache(int $mallId): void
    {
        Yii::$app->cache->delete('commission_type_scan_' . $mallId);
    }
}
