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

use app\models\IntegralLog;
use Yii;

/**
 * 积分业务类型注册表(镜像 CommissionTypeRegistry)
 *
 * 架构目标:新增一种积分来源/去向,不需要改核心枚举与前端硬编码 map。
 *
 * 使用方式:
 *   // 1) 插件/业务在 listener 里登记(可选,给个友好 label + 元数据)
 *   IntegralTypeRegistry::register('order_base', [
 *       'label' => '消费获积分', 'direction' => 'add', 'revertible' => true, 'group' => 'consume',
 *   ]);
 *   // 2) 发/扣积分时 type 直接传字符串(不用常量)
 *   (new IntegralService())->grant($uid, $amount, 'order_base', [...]);
 *   // 3) 未登记的类型也能展示 —— DB 中出现即自动进列表,label 用 type 兜底
 *
 * 合并顺序(高→低):运行时 register() > 事件收集(listener) > 内置 > DB DISTINCT 兜底
 */
class IntegralTypeRegistry
{
    /** @var array 运行时注册表 [type => config] */
    private static $registry = [];

    const DEFAULT_META = [
        'label'      => '',
        'direction'  => 'add',    // add=获取 / sub=消耗
        'revertible' => false,    // 是否可撤销(退款回收)
        'group'      => 'custom', // 业务分组,前端可按组归类
    ];

    /**
     * 注册/覆盖一种积分类型的展示元数据
     */
    public static function register(string $type, array $config): void
    {
        $type = trim($type);
        if ($type === '') {
            return;
        }
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
     * 获取一种类型的元数据(未登记返回兜底元数据)
     */
    public static function get(string $type): array
    {
        if (isset(self::$registry[$type])) {
            return self::$registry[$type];
        }
        $event = self::collectFromEvent(null);
        if (isset($event[$type])) {
            return array_merge(self::DEFAULT_META, $event[$type]);
        }
        return array_merge(self::DEFAULT_META, ['label' => $type]);
    }

    /**
     * 该类型是否可撤销(退款回收);默认不可撤销,防误回收。
     */
    public static function isRevertible(string $type): bool
    {
        return !empty(self::get($type)['revertible']);
    }

    /**
     * 获取所有已知类型
     * 合并顺序(后者覆盖前者):事件收集 → 运行时 register() → DB DISTINCT 兜底
     *
     * @param int|null $mallId 指定商城则扫描 DB;传 null 只返回事件+注册表
     * @return array [type => metaArray]
     */
    public static function getAll(?int $mallId = null): array
    {
        $all = [];

        // 1. 事件收集:各 listener 贡献的类型(核心扩展点,插件不需 import 本类)
        foreach (self::collectFromEvent($mallId) as $type => $meta) {
            $all[$type] = array_merge(self::DEFAULT_META, is_array($meta) ? $meta : ['label' => (string)$meta]);
        }

        // 2. 运行时主动 register()(优先级最高)
        foreach (self::$registry as $type => $meta) {
            $all[$type] = $meta;
        }

        // 3. DB 自发现兜底(未登记补 label=type)
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
     * 仅返回 [type => label] 映射
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
     * 触发 EVENT_COLLECT 汇总各 listener 贡献的类型
     */
    private static function collectFromEvent(?int $mallId): array
    {
        try {
            $event = new \app\events\IntegralTypeEvent();
            $event->mallId = $mallId;
            \Yii::$app->trigger(\app\events\IntegralTypeEvent::EVENT_COLLECT, $event);
            return (array)$event->types;
        } catch (\Throwable $e) {
            \Yii::error('[IntegralTypeRegistry] 事件收集异常: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 扫描 DB 中出现过的所有 biz_type(缓存 5 分钟)
     */
    private static function scanDbTypes(int $mallId): array
    {
        $cacheKey = 'integral_type_scan_' . $mallId;
        $cached = Yii::$app->cache->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $rows = IntegralLog::find()
                ->select('biz_type')
                ->distinct()
                ->where(['mall_id' => $mallId, 'is_delete' => 0])
                ->andWhere(['not', ['biz_type' => null]])
                ->column();
        } catch (\Throwable $e) {
            $rows = [];
        }
        $rows = array_values(array_filter(array_map('strval', $rows), fn($v) => $v !== ''));
        Yii::$app->cache->set($cacheKey, $rows, 300);
        return $rows;
    }

    /**
     * 刷新 DB 类型扫描缓存
     */
    public static function flushScanCache(int $mallId): void
    {
        Yii::$app->cache->delete('integral_type_scan_' . $mallId);
    }
}
