<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-09-10
 * Time: 16:57
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

class SerializeHelper extends \yii\base\Component
{
    public static function encode($value)
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    public static function decode($value)
    {
        $res = json_decode($value, true);
        if ($res === null) {
            if (json_last_error() == JSON_ERROR_NONE) {
                return $res;
            }
            if (json_last_error() != JSON_ERROR_SYNTAX) {
                $error = json_last_error_msg();
                throw new \InvalidArgumentException("{$error}: `{$value}` cannot be decoded!");
            }
            $res = unserialize($value);
            if ($res === false) {
                $value = preg_replace_callback(
                    '/s:([0-9]+):\"(.*?)\";/',
                    function ($matches) {
                        return "s:" . strlen($matches[2]) . ':"' . $matches[2] . '";';
                    },
                    $value
                );
                $res = unserialize($value);
                if ($res === false) {
                    throw new \InvalidArgumentException("`{$value}` cannot be unserialized!");
                }
            }
        }
        return $res;
    }
}
