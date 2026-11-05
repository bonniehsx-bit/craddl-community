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

/**
 * http工具类
 * Class HttpUtil
 * @package utils
 */
class HttpUtil {
    private static $_CAINFO = __DIR__ . "/ca/cacert.pem";//证书存放目录

    /**
     * 以post方式发起http请求，请求参数为json格式
     * @param string $url
     * @param string $jsonData
     * @return bool|string
     */
    public static function postJsonSync(string $url, string $jsonData){
        $curl = curl_init();

        $headers=[];
        $headers[] = "Content-type: application/json;charset=UTF-8";//设置请求体类型
        $headers[] = 'Accept: application/json;charset=UTF-8';//设置预期响应类型
        $headers[] = 'Expect:';//禁用"Expect"头域

        $opts = [];
        $opts[CURLOPT_URL] = $url;//设置请求的url
        $opts[CURLOPT_POST] = 1;//设置post方式提交
        $opts[CURLOPT_RETURNTRANSFER] = true;//设置获取的信息以文件流的形式返回，而不是直接输出。
        $opts[CURLOPT_CONNECTTIMEOUT] = 5;//设置连接超时时间(秒)
        $opts[CURLOPT_TIMEOUT] = 20;//设置超时时间(秒)
        $opts[CURLOPT_HTTPHEADER] = $headers;//设置请求头
        $opts[CURLOPT_POSTFIELDS] = $jsonData;//设置需要提交的数据
        $opts[CURLOPT_HTTP_VERSION] = 3;
        $opts[CURLOPT_HEADER] = false;//头文件的信息不当做数据流输出
        if(strpos($url, "https") === 0){//https请求
            $opts[CURLOPT_SSL_VERIFYHOST] = 2;// 从证书中检查SSL加密算法是否存在
            if(static::$_CAINFO){
                $opts[CURLOPT_CAINFO] = static::$_CAINFO; //设置证书路径
                $opts[CURLOPT_SSL_VERIFYPEER] = true; //需要执行证书检查
            }else{
                $opts[CURLOPT_SSL_VERIFYPEER] = false; //跳过证书检查（不建议）
            }
        }

        curl_setopt_array($curl, $opts);
        //执行命令
        $data = curl_exec($curl);
        curl_close($curl);
        return $data;
    }
}