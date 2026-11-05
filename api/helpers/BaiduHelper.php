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

namespace app\helpers;

use app\core\ApiCode;

class BaiduHelper
{

    public static function analyseAddress($text, $mall_id)
    {
        $setting = OptionHelper::get('mall_setting', $mall_id);
        if (!$setting) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '百度接口配置有误');
        }
        $appid = $setting['bd_addr_appid'];
        $apikey = $setting['bd_addr_apikey'];
        $secret = $setting['bd_addr_secret'];
        $url = "https://aip.baidubce.com/rpc/2.0/nlp/v1/address";
        $tokenURL = "https://aip.baidubce.com/oauth/2.0/token?client_id={$apikey}&client_secret={$secret}&grant_type=client_credentials";
        $res = HttpHelper::post($tokenURL, null, ['Content-Type' => 'application/json', 'Accept' => 'application/json']);
        if (!$res) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '请求出错');
        }
        if (isset($res['error'])) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $res['error_description']);
        }
        $url = $url . '?access_token=' . $res['access_token'];
        $res = HttpHelper::postRaw($url, ['text' => $text], ['Content-Type' => 'application/json', 'Accept' => 'application/json']);
        if (!$res) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '请求出错');
        }
        if (isset($res['error_code'])) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $res['error_msg']);
        }
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', ['result' => $res]);
    }

}