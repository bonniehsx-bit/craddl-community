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

namespace app\common\joinpay\entity;

/**
 * 密钥类
 * Class SecretKey
 * @package entity
 */
class SecretKey {
    /**
     * 对请求参数进行签名的私钥
     */
    private $reqSignKey;
    /**
     * 对响应参数进行验签的公钥
     */
    private $respVerifyKey;
    /**
     * 对sec_key进行加密的公钥
     */
    private $secKeyEncryptKey;
    /**
     * 对sec_key进行解密的私钥
     */
    private $secKeyDecryptKey;

    /**
     * @return mixed
     */
    public function getReqSignKey()
    {
        return $this->reqSignKey;
    }

    /**
     * @param mixed $reqSignKey
     */
    public function setReqSignKey($reqSignKey): void
    {
        $this->reqSignKey = $reqSignKey;
    }

    /**
     * @return mixed
     */
    public function getRespVerifyKey()
    {
        return $this->respVerifyKey;
    }

    /**
     * @param mixed $respVerifyKey
     */
    public function setRespVerifyKey($respVerifyKey): void
    {
        $this->respVerifyKey = $respVerifyKey;
    }

    /**
     * @return mixed
     */
    public function getSecKeyEncryptKey()
    {
        return $this->secKeyEncryptKey;
    }

    /**
     * @param mixed $secKeyEncryptKey
     */
    public function setSecKeyEncryptKey($secKeyEncryptKey): void
    {
        $this->secKeyEncryptKey = $secKeyEncryptKey;
    }

    /**
     * @return mixed
     */
    public function getSecKeyDecryptKey()
    {
        return $this->secKeyDecryptKey;
    }

    /**
     * @param mixed $secKeyDecryptKey
     */
    public function setSecKeyDecryptKey($secKeyDecryptKey): void
    {
        $this->secKeyDecryptKey = $secKeyDecryptKey;
    }


}