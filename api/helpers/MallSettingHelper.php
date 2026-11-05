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
namespace app\helpers;

use app\models\Mall;
use Yii;

/**
 * Class MallSettingHelper
 * @package app\helpers
 *
 * [升级版] 专门用于高效、安全地获取商城全局配置的服务类。
 * 内部实现了静态缓存，避免在同一次请求中重复查询数据库。
 */
class MallSettingHelper
{
    /**
     * @var array 静态变量，用于缓存当前请求中的商城设置
     */
    private static $settingsCache = [];

    /**
     * [核心 - 升级版] 获取指定键或全部商城设置
     *
     * @param string|null $key      要获取的配置键名 (e.g., 'logo', 'goods_property')。如果为 null，则返回所有配置。
     * @param int|null    $mallId   商城ID。如果为 null，则自动使用当前上下文的商城ID。
     * @param mixed       $default  当键名不存在时返回的默认值。
     * @return array|mixed|null
     */
    public static function get($key = null, $mallId = null, $default = null)
    {
        // 确定要操作的商城ID
        $currentMallId = $mallId ?? Yii::$app->mall->id;
        if (!$currentMallId) {
            // 在无法获取到商城ID的上下文（如某些console命令）中，返回默认值
            return $default;
        }

        // 如果当前商城的配置尚未被缓存，则从数据库加载
        if (!isset(self::$settingsCache[$currentMallId])) {
            $setting = OptionHelper::get('mall_setting', $currentMallId, []);

            // 如果 options 表中没有，则尝试从 mall 表的属性中获取（作为一种备用方案）
            if (empty($setting)) {
                $mall = Mall::findOne($currentMallId);
                $setting = $mall ? $mall->attributes : [];
            }

            self::$settingsCache[$currentMallId] = $setting;
        }

        // 从缓存中获取完整的配置
        $currentSettings = self::$settingsCache[$currentMallId];

        // 如果指定了键名，则返回对应的值，否则返回全部配置
        if ($key !== null) {
            return $currentSettings[$key] ?? $default;
        }

        return $currentSettings;
    }

    /**
     * [保留并优化] 专门获取Logo的方法，内部调用 get()
     * 保持了对旧代码的兼容性
     * @param int $mall_id
     * @return mixed|null
     */
    public static function getLogo($mall_id)
    {
        return self::get('logo', $mall_id);
    }

    /**
     * [废弃建议] 这个方法的功能已被 get() 方法完全覆盖且更强大
     * 保留它是为了向后兼容，但建议新代码统一使用 get()
     * @param string $key
     * @param int $mall_id
     * @return mixed|null
     */
    public static function getValue($key, $mall_id)
    {
        return self::get($key, $mall_id);
    }

    /**
     * 清理指定商城的静态缓存
     * @param int|null $mallId
     */
    public static function clearCache($mallId = null)
    {
        $currentMallId = $mallId ?? Yii::$app->mall->id;
        if (isset(self::$settingsCache[$currentMallId])) {
            unset(self::$settingsCache[$currentMallId]);
        }
    }
}