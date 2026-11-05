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

namespace app\services\order\factory;

use app\handlers\order\BaseOrderHandler;
use app\handlers\order\NormalOrderHandler;

class OrderHandlerFactory
{
    /**
     * @var array 静态缓存，避免一次请求中重复进行字符串计算和文件 I/O (class_exists)
     */
    private static $handlerCache = [];

    /**
     * 静态工厂方法：智能获取订单处理器
     * * @param string|null $plugin 插件名，如 'mch', 'integral'
     * @param string|null $type 业务类型，如 'group_buy', 'cashier'
     * @return BaseOrderHandler
     */
    public static function create($plugin = null, $type = null): BaseOrderHandler
    {
        // 缓存键名
        $cacheKey = ($plugin ?: 'mall') . '_' . ($type ?: 'normal');

        if (isset(self::$handlerCache[$cacheKey])) {
            $class = self::$handlerCache[$cacheKey];
            return new $class();
        }

        // ==========================================
        // 1. [名称转换] 统一转换为大驼峰 (PascalCase)
        // ==========================================
        $pluginClassName = '';
        if ($plugin) {
            // 例: group_buy -> GroupBuy
            $pluginClassName = str_replace(' ', '', ucwords(str_replace('_', ' ', $plugin)));
        }

        $typeClassName = '';
        if ($type) {
            // 例: group_buy -> GroupBuy
            $typeClassName = str_replace(' ', '', ucwords(str_replace('_', ' ', $type)));
        }

        // ==========================================
        // 2. [构建候选路由列表] 降级策略
        // ==========================================
        $candidates = [];

        if ($plugin && $plugin !== 'mall') {
            // 目录名保持原始的下划线 $plugin，类名使用大驼峰
            if ($typeClassName) {
                // 优先找: 插件目录下特定业务的 Handler
                // 例如: app\plugins\mch\handlers\GroupBuyOrderHandler
                $candidates[] = "app\\plugins\\{$plugin}\\handlers\\{$typeClassName}OrderHandler";
            }
            // 降级找: 插件目录下的默认插件 Handler
            // 例如: app\plugins\mch\handlers\MchOrderHandler
            $candidates[] = "app\\plugins\\{$plugin}\\handlers\\{$pluginClassName}OrderHandler";

            // 最终降级找: 插件目录下通用的 OrderHandler
            $candidates[] = "app\\plugins\\{$plugin}\\handlers\\OrderHandler";
        } else {
            // 非插件 (商城主干逻辑)
            if ($typeClassName) {
                // 例如: app\handlers\order\GroupBuyOrderHandler
                $candidates[] = "app\\handlers\\order\\{$typeClassName}OrderHandler";
            }
        }

        // ==========================================
        // 3. [命中与缓存]
        // ==========================================
        foreach ($candidates as $class) {
            if (class_exists($class)) {
                self::$handlerCache[$cacheKey] = $class;
                return new $class();
            }
        }

        // 4. [全局兜底] 如果全都没命中，走普通订单
        $defaultClass = NormalOrderHandler::class;
        self::$handlerCache[$cacheKey] = $defaultClass;

        return new $defaultClass();
    }
}