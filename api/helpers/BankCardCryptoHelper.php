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

/**
 * 绑卡敏感字段对称加解密。
 *
 * 使用 Yii::$app->security->encryptByPassword / decryptByPassword，
 * 底层为 OpenSSL AES-128-CBC + HMAC（authenticated encryption），
 * 不依赖已废弃的 mcrypt 扩展。
 *
 * 密钥来自 params['bankCardEncryptKey']。
 */
class BankCardCryptoHelper
{
    public static function encrypt($plain): string
    {
        if ($plain === null || $plain === '') {
            return '';
        }
        $key = Yii::$app->params['bankCardEncryptKey'] ?? '';
        if ($key === '') {
            throw new \RuntimeException('bankCardEncryptKey 未配置');
        }
        return base64_encode(Yii::$app->security->encryptByPassword((string)$plain, $key));
    }

    public static function decrypt($cipher): string
    {
        if ($cipher === null || $cipher === '') {
            return '';
        }
        $key = Yii::$app->params['bankCardEncryptKey'] ?? '';
        if ($key === '') {
            throw new \RuntimeException('bankCardEncryptKey 未配置');
        }
        $raw = base64_decode($cipher, true);
        if ($raw === false) {
            return '';
        }
        $plain = Yii::$app->security->decryptByPassword($raw, $key);
        return $plain === false ? '' : $plain;
    }
}
