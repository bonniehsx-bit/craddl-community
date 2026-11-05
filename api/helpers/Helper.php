<?php

/**
 * Copyright (c) 2022-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm.
 * User: ganxi
 * Date: 2022/6/19
 * Time: 15:11
 * Note:
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


use app\models\AccessToken;
use yii\helpers\Json;

class Helper
{
    public static function createUserToken()
    {
        do {
            $token = \Yii::$app->security->generateRandomString(64);
            // DB 中存储的是 hash，查重也用 hash 比对
        } while (AccessToken::find()->where(['token' => hash('sha256', $token)])->exists());
        return $token;
    }

    public static function strToArray($str = null)
    {
        if (!$str) {
            return null;
        }
        if (is_array($str)) {
            return $str;
        }
        try {
            return Json::decode($str, true);
        } catch (\Exception $e) {
            return explode(',', $str);
        }
    }

    public static function isMobile($val)
    {
        return preg_match("/^1[3456789]{1}\d{9}$/", $val);
    }

    public static function isXml($string)
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($string);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        return $xml !== false && empty($errors);
    }

    public static function maskPhoneNumber($phoneNumber)
    {
        $length = strlen($phoneNumber);
        if ($length != 11) {
            // 非10位手机号码，不进行替换
            return $phoneNumber;
        }
        $maskedNumber = substr($phoneNumber, 0, 3) . '****' . substr($phoneNumber, 7);
        return $maskedNumber;
    }

    public static function getRandomStr($length = 10)
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = substr(str_shuffle($characters), 0, $length);
        return $randomString;
    }

    public static function createOrderNo($prefix = '', $rand_len = 6)
    {
        return $prefix . date('YmdHis') . self::getRandomStr($rand_len);
    }
}
