<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-24
 * Time: 15:19
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


use yii\helpers\BaseFileHelper;
use Yii; // 引入 Yii

class FileHelper extends BaseFileHelper
{
    public static function getUrl($name)
    {

        return \Yii::$app->request->hostInfo  . '/web' . $name;
    }

    public static function getUrlOnPlugin($name)
    {
        return \Yii::$app->request->hostInfo . '/plugins' . $name;
    }

    /**
     * [新增] 从指定URL下载文件内容
     * 使用cURL或file_get_contents
     * @param string $url 要下载的URL
     * @param int $timeout 超时时间，秒
     * @return string|false 下载的文件内容，失败返回false
     */
    public static function httpGet(string $url, int $timeout = 10)
    {
        if (extension_loaded('curl')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 忽略SSL证书验证，适用于测试环境
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 忽略主机验证
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // 遵循301/302跳转
            $content = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);
            if ($error) {
                Yii::error("FileHelper::httpGet cURL error: " . $error . " URL: " . $url);
                return false;
            }
            return $content;
        } else {
            // 如果cURL不可用，使用file_get_contents
            // 注意：file_get_contents 对网络请求的控制较弱，且可能受 allow_url_fopen 配置限制
            $context = stream_context_create([
                'http' => [
                    'timeout' => $timeout,
                    'ignore_errors' => true // 忽略HTTP错误，仍然尝试获取内容
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ]);
            $content = @file_get_contents($url, false, $context);
            if ($content === false) {
                Yii::error("FileHelper::httpGet file_get_contents failed for URL: " . $url);
            }
            return $content;
        }
    }
}
