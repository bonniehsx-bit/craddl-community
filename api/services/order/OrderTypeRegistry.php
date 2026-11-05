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

namespace app\services\order;

use app\models\Plugin as PluginModel;
use Yii;

/**
 * 订单类型注册表
 *
 * 汇总「核心内置类型」+ 各已安装插件 Plugin::getOrderTypes() 声明的类型,
 * 供后台「订单列表配置」勾选展示哪些类型,供 C 端订单列表按 order.plugin 过滤。
 *
 * 每项结构:['key'=>唯一键, 'name'=>默认名, 'plugins'=>[匹配的 order.plugin 值], 'icon'=>'']
 */
class OrderTypeRegistry
{
    /**
     * 全平台订单类型清单(按当前商城已授权插件过滤,未授权插件的类型不出现)。
     * @return array
     */
    public static function all(): array
    {
        // 核心内置:普通商品订单(order.plugin 默认 'mall',兼容历史空值)
        $types = [
            ['key' => 'mall', 'name' => '普通订单', 'plugins' => ['mall', ''], 'types' => [], 'icon' => '', 'plugin' => '', 'plugin_label' => '商城核心'],
        ];

        // 当前商城授权的插件名(null = 不限商城/取全部)
        $authorized = Yii::$app->plugin->resolveAuthorizedPluginNames();

        foreach (PluginModel::find()->where(['is_delete' => 0])->all() as $item) {
            if ($authorized !== null && !in_array($item->name, $authorized, true)) {
                continue;
            }
            $class = '\\app\\plugins\\' . $item->name . '\\Plugin';
            if (!class_exists($class)) {
                continue;
            }
            try {
                $plugin = new $class();
                // 插件中文名作为分组标题(后台按插件分组展示类型,避免分不清归属)
                $label = '';
                try {
                    $label = (string)$plugin->getLabel();
                } catch (\Throwable $e) {
                }
                foreach ($plugin->getOrderTypes() as $t) {
                    if (empty($t['key'])) {
                        continue;
                    }
                    $types[] = [
                        'key'          => (string)$t['key'],
                        'name'         => (string)($t['name'] ?? $t['key']),
                        'plugins'      => (isset($t['plugins']) && is_array($t['plugins']) && $t['plugins']) ? $t['plugins'] : [$item->name],
                        // 可选的 order.type 细分。省略 = 不限该插件下的 type(绝大多数插件是
                        // plugin 与 type 一一对应,不用声明);plaza 那种一个插件产出多种订单
                        // (账单缴费 / 预存充值)才需要,拆成多个 key 各自限定 types。
                        'types'        => (isset($t['types']) && is_array($t['types'])) ? array_values($t['types']) : [],
                        'icon'         => (string)($t['icon'] ?? ''),
                        'plugin'       => $item->name,
                        'plugin_label' => $label !== '' ? $label : $item->name,
                    ];
                }
            } catch (\Throwable $e) {
                Yii::warning('[OrderTypeRegistry] 插件 ' . $item->name . ' getOrderTypes 失败: ' . $e->getMessage());
            }
        }

        return $types;
    }

    /**
     * 判定一个订单是否落在选中的类型里。
     *
     * 与 pluginsForKeys() 的区别:那个返回 plugin 值集合、给列表查询拼 IN 条件用;
     * 这个是**判定单个订单**,支付网关限制要的是"这一单能不能用这个网关",
     * 而且必须能吃到 types 细分 —— 拼 IN 做不到(plaza 的两种订单 plugin 值相同)。
     *
     * ⛔ $keys 为空一律返回 true(不限)。支付网关那边默认放行,
     *    默认拒绝的话升上去所有商城立刻收不了款。
     *
     * ⚠ order.plugin 有历史空值(普通商城单),内置的 mall 类型把 '' 也列进 plugins 了,
     *    这里按值比对天然覆盖,不要额外写 NULL 判断。
     *
     * @param array  $keys   选中的类型 key
     * @param string $plugin 订单的 order.plugin
     * @param string $type   订单的 order.type
     */
    public static function matches(array $keys, string $plugin, string $type): bool
    {
        if (empty($keys)) {
            return true;
        }
        foreach (self::all() as $t) {
            if (!in_array($t['key'], $keys, true)) {
                continue;
            }
            if (!in_array($plugin, $t['plugins'], true)) {
                continue;
            }
            // types 为空 = 不限该插件下的 type
            if (empty($t['types']) || in_array($type, $t['types'], true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 把选中的 type key 列表 → 对应的 order.plugin 值集合(供 OrderListForm 过滤)。
     * @param array $keys 选中的类型 key
     * @return array order.plugin 值数组
     */
    public static function pluginsForKeys(array $keys): array
    {
        if (empty($keys)) {
            return [];
        }
        $map = [];
        foreach (self::all() as $t) {
            $map[$t['key']] = $t['plugins'];
        }
        $plugins = [];
        foreach ($keys as $k) {
            if (isset($map[$k])) {
                $plugins = array_merge($plugins, $map[$k]);
            }
        }
        return array_values(array_unique($plugins));
    }
}
