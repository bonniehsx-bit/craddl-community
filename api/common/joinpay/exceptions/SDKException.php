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

namespace app\common\joinpay\exceptions;

/**
 * 自定义异常类
 * Class BizException
 * @package exceptions
 */
class SDKException extends \Exception
{
    const PARAM_ERROR = "10000";
    const BIZ_ERROR = "20000";

    private $bizCode;
    private $msg;

    function __construct(int $bizCode, string $msg)
    {
        parent::__construct();
        $this->bizCode = $bizCode;
        $this->msg = $msg;
    }

    /**
     * @return int
     */
    public function getBizCode()
    {
        return $this->bizCode;
    }

    /**
     * @entity int $bizCode
     */
    public function setBizCode($bizCode)
    {
        $this->bizCode = $bizCode;
    }

    /**
     * @return string
     */
    public function getMsg()
    {
        return $this->msg;
    }

    /**
     * @entity mixed $msg
     */
    public function setMsg($msg)
    {
        $this->msg = $msg;
    }


}