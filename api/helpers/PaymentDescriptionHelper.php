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

/** 支付商品描述：按支付单所属商城取配置，留空保留调用入口的原文案。 */
class PaymentDescriptionHelper
{
    const MAX_LENGTH = 127;

    public static function normalize(string $value): string
    {
        return preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $value) ?? '';
    }

    public static function isValid(string $value): bool
    {
        return mb_strlen($value, 'UTF-8') <= self::MAX_LENGTH
            && preg_match('/[\x00-\x1F\x7F]/u', $value) === 0;
    }

    public static function resolve(int $mallId, string $fallback): string
    {
        if ($mallId <= 0) {
            return $fallback;
        }
        $setting = OptionHelper::get('mall_setting', $mallId, []);
        $value = is_array($setting) ? ($setting['payment_body'] ?? '') : '';
        if (!is_string($value)) {
            return $fallback;
        }
        $value = self::normalize($value);
        return $value !== '' && self::isValid($value) ? $value : $fallback;
    }
}
