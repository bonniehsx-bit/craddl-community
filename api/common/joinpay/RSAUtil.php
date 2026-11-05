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

namespace app\common\joinpay;

use app\common\joinpay\exceptions\SDKException;

/**
 * 使用RSA进行加密、解密、签名、验签
 * Class RSAUtil
 * @package utils
 */
class RSAUtil
{
    /**
     * 使用公钥加密
     * @param string $data
     * @param string $pubKey
     * @return string
     * @throws SDKException
     */
    public static function encrypt(string $data, string $pubKey)
    {
        $pubKey = openssl_get_publickey($pubKey);
        if ($pubKey === false) {
            throw new SDKException(SDKException::BIZ_ERROR, "rsa解密公钥无效，修改建议：平台公钥代码中格式为：首行-----BEGIN PUBLIC KEY-----；第二行平台公钥；第三行-----END PUBLIC KEY-----");
            echo "<br>";
        }

        $crypted = '';
        $isSuccess = openssl_public_encrypt($data, $crypted, $pubKey);
        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            openssl_free_key($pubKey);
        }
        if ($isSuccess == false) {
            throw new SDKException(SDKException::BIZ_ERROR, "rsa加密失败");
        }
        return base64_encode($crypted);
    }

    /**
     * 使用私钥解密
     * @param string $data
     * @param string $priKey
     * @return string
     * @throws SDKException
     */
    public static function decrypt(string $data, string $priKey)
    {
        $priKey = openssl_get_privatekey($priKey);
        if ($priKey === false) {
            throw new SDKException(SDKException::BIZ_ERROR, "rsa解密私钥无效");
        }

        $decrypted = '';
        $isSuccess = openssl_private_decrypt(base64_decode($data), $decrypted, $priKey);
        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            openssl_free_key($priKey);
        }
        if (!$isSuccess) {
            throw new SDKException(SDKException::BIZ_ERROR, "rsa解密失败，请检查是否有遗漏加密敏感信息。");
            echo "<br>";
        }
        return $decrypted;
    }

    /**
     * 使用私钥进行签名
     * @param string $data
     * @param string $priKey
     * @return string
     * @throws SDKException
     */
    public static function sign(string $data, string $priKey)
    {
        $priKey = openssl_get_privatekey($priKey);
        if ($priKey === false) {
            throw new SDKException(SDKException::BIZ_ERROR, "rsa签名私钥无效修改建议：私钥代码中格式为：首行-----BEGIN RSA PRIVATE KEY-----；第二行私钥；第三行-----END RSA PRIVATE KEY-----");
        }

        $binary_signature = '';
        $isSuccess = openssl_sign($data, $binary_signature, $priKey, OPENSSL_ALGO_MD5);
        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            openssl_free_key($priKey);
        }
        if (!$isSuccess) {
            throw new SDKException(SDKException::BIZ_ERROR, "rsa签名失败");
        }
        return base64_encode($binary_signature);
    }

    /**
     * 使用公钥进行验签
     * @param string $signData
     * @param string $signParam
     * @param string $pubKey
     * @return bool
     * @throws SDKException
     */
    public static function verify(string $signData, string $signParam, string $pubKey)
    {
        $pubKey = openssl_get_publickey($pubKey);
        if ($pubKey === false) {
            throw new SDKException(SDKException::BIZ_ERROR, "rsa验签公钥无效");
        }

        $signParam = base64_decode($signParam);
        $isMatch = openssl_verify($signData, $signParam, $pubKey, OPENSSL_ALGO_MD5);
        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            openssl_free_key($pubKey);
        }
        return $isMatch;
    }
}