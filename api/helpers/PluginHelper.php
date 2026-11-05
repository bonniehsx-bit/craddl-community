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

use Yii;
use app\helpers\ServerHelper;

class PluginHelper
{
    /**
     * 共用品牌资源，避免已发布的旧插件图片和浏览器缓存继续显示旧 Logo。
     */
    public static function getDefaultLogoUrl(): string
    {
        return rtrim(ServerHelper::getHost(), '/') . '/statics/brand/craddl/icon-128.png';
    }

    /**
     * 仅替换内置默认图片及历史占位值，自定义上传图片保持原样。
     */
    public static function resolveLogo(string $pluginName, ?string $logo = ''): string
    {
        $logo = trim($logo ?? '');
        $path = parse_url($logo, PHP_URL_PATH);
        $defaults = [
            '/plugins/' . $pluginName . '/logo.png',
            '/plugins/' . $pluginName . '/assets/logo.png',
            '/statics/brand/craddl/icon-128.png',
        ];
        foreach (['png', 'jpg'] as $ext) {
            $defaults[] = '/statics/plugin_logos/' . $pluginName . '.' . $ext;
        }

        if ($logo === '' || in_array($logo, ['logo.png', 'icon.png'], true)
            || (strpos($logo, 'http') !== 0 && strpos($logo, '/') !== 0)
            || in_array($path, $defaults, true)) {
            return self::getDefaultLogoUrl();
        }

        return $logo;
    }

    /**
     * 兼容原插件图标入口，默认图标直接使用公共品牌资源。
     */
    public static function getPluginIconUrl($pluginName)
    {
        if (!is_dir(Yii::getAlias('@app/plugins/' . $pluginName))) {
            return '';
        }
        return self::getDefaultLogoUrl();
    }
}
