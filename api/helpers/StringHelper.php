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
/**
 * Created by PhpStorm.
 * User: dujxmall
 * Date: 2023/1/5
 * Time: 14:36
 */

namespace app\helpers;


class StringHelper
{

    public static function explodeByComma($str)
    {
        if (!$str) {
            return [];
        }
        $string = str_replace('，', ',', $str);
        return explode(',', $string);
    }
    /**
     * 替换固定长度的中间部分，统一用3个星号
     * @param string $str 原始字符串
     * @param int $start 开始替换的位置
     * @param int $length 替换的长度
     * @return string 替换后的字符串
     */
    public static function replaceFixedLengthMiddle($str, $start, $length)
    {
        return mb_substr($str, 0, $start, 'UTF-8') . '***' . mb_substr($str, $start + $length, null, 'UTF-8');
    }

    /**
     * 保留首尾字符，中间用3个星号替换
     * @param string $str 原始字符串
     * @return string 替换后的字符串
     */
    public static function replaceMiddleKeepEnds($str)
    {
        $len = mb_strlen($str, 'UTF-8');
        if ($len <= 3) {
            return $str;
        }
        $firstChar = mb_substr($str, 0, 1, 'UTF-8');
        $lastChar = mb_substr($str, -1, 1, 'UTF-8');
        return $firstChar . '***' . $lastChar;
    }

    /**
     * 按比例替换中间部分，统一用3个星号
     * @param string $str 原始字符串
     * @param float $startRatio 开始替换的比例
     * @param float $endRatio 结束替换的比例
     * @return string 替换后的字符串
     */
    public static function replaceMiddleByRatio($str, $startRatio = 0.2, $endRatio = 0.8)
    {
        $len = mb_strlen($str, 'UTF-8');
        $start = (int) ($len * $startRatio);
        $end = (int) ($len * $endRatio);
        return mb_substr($str, 0, $start, 'UTF-8') . '***' . mb_substr($str, $end, null, 'UTF-8');
    }
}