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

use app\models\Plugin as PluginModel;
use app\services\contracts\PayoutHandlerInterface;
use Yii;

/**
 * 结算目标处理器注册表(核心)—— 汇总各插件 Plugin::getPayoutHandlers() 声明的 `payoutType => 实现类`。
 *
 * 与 WalletRegistry 同构:mall 无关、进程内缓存、遍历已安装插件。
 * 供 CommissionService::settle() 在命中已注册 payoutType 时委托给对应插件 handler,
 * 使核心结算主干对插件私有的结算实现(如收银台第三方分账)保持零引用。
 *
 * 声明结构:Plugin::getPayoutHandlers() 返回 ['split' => app\plugins\cashier\services\ExternalSplitPayoutHandler::class]
 * 实现类须 implements app\services\contracts\PayoutHandlerInterface。
 */
class PayoutHandlerRegistry
{
    /** @var array<string,string>|null payoutType => handlerClass */
    private static $cache = null;

    /** 全部已安装插件声明的 payout handler(payoutType => 实现类全名) */
    public static function allRaw(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $list = [];
        foreach (PluginModel::find()->where(['is_delete' => 0])->all() as $item) {
            $class = '\\app\\plugins\\' . $item->name . '\\Plugin';
            if (!class_exists($class)) {
                continue;
            }
            try {
                foreach ((new $class())->getPayoutHandlers() as $payoutType => $handlerClass) {
                    $payoutType = (string)$payoutType;
                    if ($payoutType === '' || empty($handlerClass) || !class_exists($handlerClass)) {
                        continue;
                    }
                    // 首个声明者胜出:同一 payoutType 不应由多个插件争夺,避免结算路由歧义
                    if (!isset($list[$payoutType])) {
                        $list[$payoutType] = (string)$handlerClass;
                    }
                }
            } catch (\Throwable $e) {
                Yii::warning('[PayoutHandlerRegistry] 插件 ' . $item->name . ' getPayoutHandlers 失败: ' . $e->getMessage());
            }
        }
        return self::$cache = $list;
    }

    /** 是否有插件为该 payoutType 注册了 handler */
    public static function has(string $payoutType): bool
    {
        return isset(self::allRaw()[$payoutType]);
    }

    /**
     * 取该 payoutType 的 handler 实例;未注册返回 null。
     * @return PayoutHandlerInterface|null
     */
    public static function handler(string $payoutType)
    {
        $class = self::allRaw()[$payoutType] ?? null;
        if (!$class) {
            return null;
        }
        $instance = new $class();
        return $instance instanceof PayoutHandlerInterface ? $instance : null;
    }
}
