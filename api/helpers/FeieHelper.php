<?php

/**
 * Copyright (c) 2022-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm.
 * User: ganxi
 * Date: 2022/6/18
 * Time: 7:52
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


class FeieHelper
{


    public static function printMsg($data)
    {
        /**
         *
         * $time=time();
         * $data = [
         * 'user' => '账户',
         * 'stime' =>$time,
         * 'sig' => sha1(账号 . ukey. $time),
         * 'apiname' => 'Open_printMsg',
         * 'sn' =>sn编号,
         * 'content' => 内容,
         * ];
         */
        $url = "http://api.feieyun.cn/Api/Open/";
        $data['apiname'] = 'Open_printMsg';
        $res = HttpHelper::post($url, $data, ['Content-Type' => 'application/x-www-form-urlencoded']);
        return $res;
    }

    public static function printBC128($data, $is_custom = false)
    {
        $url = "http://api.feieyun.cn/Api/Open/";
        $data['apiname'] = 'Open_printLabelMsg';
        // $data['content'] = '<BC128 x="30" y="30" h="150" s="1" r="0" n="1" w="20">' . $data['content'] . '</BC128>';
        $data['content'] = '<BC128 x="80" y="30" h="150" s="1" r="0" n="1" w="20">' . $data['content'] . '</BC128>';
        $res = HttpHelper::post($url, $data, ['Content-Type' => 'application/x-www-form-urlencoded']);
        return $res;
    }

    public static function printQrcodeInLabel($data, $is_custom = false)
    {
        $url = "http://api.feieyun.cn/Api/Open/";
        $data['apiname'] = 'Open_printLabelMsg';
        $res = HttpHelper::post($url, $data, ['Content-Type' => 'application/x-www-form-urlencoded']);
        return $res;
    }
}
