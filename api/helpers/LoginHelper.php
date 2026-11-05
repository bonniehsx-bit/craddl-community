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
/**
 * Created by PhpStorm.
 * Author:ganxh
 * User: cp
 * Date: 2021/11/22
 * Time: 10:28
 */

namespace app\helpers;

use Yii;

class LoginHelper
{
    // 前缀，防止缓存冲突
    const CAPTCHA_PREFIX = 'SYS_CAPTCHA_';

    /**
     * 设置验证码
     * @param string $key 前端传回来的唯一标识 (UUID)
     * @param string $code 验证码内容
     * @param int $expire 过期时间，建议 300秒 (5分钟)，60秒对于手慢的用户太短了
     */
    public static function setAuthCode($key, $code, $expire = 300)
    {
        $cacheKey = self::CAPTCHA_PREFIX . $key;
        CacheHelper::set($cacheKey, strtolower($code), $expire); // 存入时转小写，方便比对
    }

    /**
     * 获取验证码
     * @param string $key
     * @return mixed
     */
    public static function getAuthCode($key)
    {
        if (empty($key)) return null;
        $cacheKey = self::CAPTCHA_PREFIX . $key;
        return CacheHelper::get($cacheKey);
    }

    /**
     * 移除验证码 (验证成功后，或者需要强制刷新时调用)
     * @param string $key
     */
    public static function removeAuthCode($key)
    {
        if (empty($key)) return;
        $cacheKey = self::CAPTCHA_PREFIX . $key;
        CacheHelper::remove($cacheKey);
    }

    public static function checkAuthCode($captcha_key, $captcha)
    {
        $cacheCode = self::getAuthCode($captcha_key);
        // 验证码过期/已被消费时 getAuthCode 返回 null,PHP 8.1 起直接传进 strtoupper 会报废弃警告
        // (严格错误处理下更会抛异常)。这里显式转字符串:空串必然比不中,判定结果与原来一致。
        return strtoupper((string) $captcha) === strtoupper((string) $cacheCode);
    }
}