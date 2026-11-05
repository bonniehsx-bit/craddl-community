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
 * AES加解密工具类
 * Class AESUtil
 * @package utils
 */
class AESUtil {
    const EBC_MODE = "AES-128-ECB";

    /**
     * AES加密，模式为：AES/ECB/PKCK7Padding
     * @param string $data
     * @param string $secKey
     * @return string
     * @throws SDKException
     */
    public static function encryptECB(string $data, string $secKey){
        $encrypted = openssl_encrypt($data, self::EBC_MODE, $secKey, OPENSSL_RAW_DATA);
        if($encrypted === false){
            throw new SDKException(SDKException::BIZ_ERROR, "aes加密失败");
        }
        return base64_encode($encrypted);
    }

    /**
     * AES解密，模式为：AES/ECB/PKCK7Padding
     * @param string $data
     * @param string $secKey
     * @return string
     * @throws SDKException
     */
    public static function decryptECB(string $data, string $secKey){
        $decrypted = openssl_decrypt(base64_decode($data), self::EBC_MODE, $secKey, OPENSSL_RAW_DATA);
        if($decrypted === false){
            throw new SDKException(SDKException::BIZ_ERROR, "aes解密失败");
        }
        return $decrypted;
    }
}