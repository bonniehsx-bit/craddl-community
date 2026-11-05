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
use ReflectionClass;
use ReflectionProperty;

/**
 * 签名、验签工具类
 * Class SignUtil
 * @package utils
 */
class SignUtil
{
    const MS5_BOUND_SYMBOL = "&key=";

    /**
     * 签名
     * @param string $signData 需要签名的数据
     * @param string $signType 签名类型
     * @param string $priKey 用以签名的私钥
     * @return string
     * @throws \exceptions\SDKException
     */
    public static function sign(string $signData, string $signType, string $priKey)
    {
        if ("2" === $signType) {
            return RSAUtil::sign($signData, $priKey);
        } else if ("1" === $signType) {
            return MD5Util::getMd5Str($signData . self::MS5_BOUND_SYMBOL . $priKey);
        } else {
            throw new SDKException(SDKException::BIZ_ERROR, "未支持的签名类型：" . $signType);
        }
    }

    /**
     * 验签
     * @param string $signData 需要验签的数据
     * @param string $signParam 需要被校验签名的源数据
     * @param string $signType 签名类型
     * @param string $pubKey 用以验签的公钥
     * @return bool
     * @throws \exceptions\SDKException
     */
    public static function verify(string $signData, string $signParam, string $signType, string $pubKey)
    {
        if ("2" === $signType) {
            return RSAUtil::verify($signData, $signParam, $pubKey);
        } else if ("1" === $signType) {
            $signData = MD5Util::getMd5Str($signData . self::MS5_BOUND_SYMBOL . $pubKey);
            return $signData === $signParam;
        } else {
            throw new SDKException(SDKException::BIZ_ERROR, "未支持的签名类型：" . $signType);
        }
    }

    /**
     * 取得 待签名/待验签 的字符串
     * @param object $param
     * @return string
     * @throws \ReflectionException
     */
    public static function getSortedString(object $param)
    {
        $reflect = new ReflectionClass($param);
        $props = $reflect->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PRIVATE | ReflectionProperty::IS_PROTECTED);

        //通过反射取得所有属性和属性的值
        $arr = [];
        foreach ($props as $prop) {
            $prop->setAccessible(true);

            $key = $prop->getName();
            $value = $prop->getValue($param);
            $arr[$key] = $value;
        }

        //按key的字典序升序排序，并保留key值
        ksort($arr);

        //拼接字符串
        $str = '';
        $i = 0;
        foreach ($arr as $key => $value) {
            //不参与签名、验签
            if ($key == "sign" || $key == "sec_key") {
                continue;
            }

            if ($value === null) {
                $value = '';
            }

            if ($i !== 0) {
                $str .= '&';
            }
            $str .= $key . '=' . $value;
            $i++;
        }
        return $str;
    }
}