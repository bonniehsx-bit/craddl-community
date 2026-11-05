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
 * 异步参数类
 * Class Response
 * @package entity
 */
class NotifyResponse {
//    private $resp_code;
    private $biz_code;
    private $biz_msg;
    private $mch_no;
    private $data;
    private $rand_str;
    private $sign_type;
    private $sign;
    private $sec_key;

//    /**
//     * @return mixed
//     */
//    public function getRespCode()
//    {
//        return $this->resp_code;
//    }
//
//    /**
//     * @param mixed $resp_code
//     */
//    public function setRespCode($resp_code): void
//    {
//        $this->resp_code = $resp_code;
//    }

    /**
     * @return mixed
     */
    public function getBizCode()
    {
        return $this->biz_code;
    }

    /**
     * @param mixed $biz_code
     */
    public function setBizCode($biz_code): void
    {
        $this->biz_code = $biz_code;
    }

    /**
     * @return mixed
     */
    public function getBizMsg()
    {
        return $this->biz_msg;
    }

    /**
     * @param mixed $biz_msg
     */
    public function setBizMsg($biz_msg): void
    {
        $this->biz_msg = $biz_msg;
    }

    /**
     * @return mixed
     */
    public function getMchNo()
    {
        return $this->mch_no;
    }

    /**
     * @param mixed $mch_no
     */
    public function setMchNo($mch_no): void
    {
        $this->mch_no = $mch_no;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     */
    public function setData($data): void
    {
        $this->data = $data;
    }

    /**
     * @return mixed
     */
    public function getRandStr()
    {
        return $this->rand_str;
    }

    /**
     * @param mixed $rand_str
     */
    public function setRandStr($rand_str): void
    {
        $this->rand_str = $rand_str;
    }

    /**
     * @return mixed
     */
    public function getSignType()
    {
        return $this->sign_type;
    }

    /**
     * @param mixed $sign_type
     */
    public function setSignType($sign_type): void
    {
        $this->sign_type = $sign_type;
    }

    /**
     * @return mixed
     */
    public function getSign()
    {
        return $this->sign;
    }

    /**
     * @param mixed $sign
     */
    public function setSign($sign): void
    {
        $this->sign = $sign;
    }

    /**
     * @return mixed
     */
    public function getSecKey()
    {
        return $this->sec_key;
    }

    /**
     * @param mixed $sec_key
     */
    public function setSecKey($sec_key): void
    {
        $this->sec_key = $sec_key;
    }

    /**
     * 判断本次请求是否成功
     * @return bool
     */
    public function isSuccess(): bool
    {
        return "SUCCESS" === $this->resp_code && "JS000000" === $this->biz_code;
    }
}