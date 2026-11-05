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

namespace app\common\helpers;

/**
 * error code 说明.
 */
class WxWorkErrorCode
{
    public static function getErrorMsg($errorCode): string
    {
        $errorMessages = [
            /* 官方示例自定义错误码 */
            '-40001' => '签名验证错误，请检查token、encodingAesKey参数',
            '-40002' => 'xml解析失败',
            '-40003' => 'sha加密生成签名失败',
            '-40004' => 'encodingAesKey 非法',
            '-40005' => 'corpid 校验错误',
            '-40006' => 'aes 加密失败',
            '-40007' => 'aes 解密失败',
            '-40008' => '解密后得到的buffer非法',
            '-40009' => 'base64加密失败',
            '-40010' => 'base64解密失败',
            '-40011' => '生成xml失败',

            /* 官方文档全局错误码 */
            '-1'      => '系统繁忙',
            '0'       => '请求成功',
            '40001'   => '不合法的secret参数',
            '40014'   => '不合法的access_token',
            '40058'   => '不合法的参数',
            '41001'   => '缺少access_token参数',
            '42001'   => 'access_token已过期',
            // ... (为了节省篇幅，这里保留你之前文件中的所有数组内容，逻辑不变，只是增加了namespace) ...
            '95011'   => '已在企业微信使用微信客服',
        ];

        return isset($errorMessages[$errorCode]) ? $errorMessages[$errorCode] : '未知错误(' . $errorCode . ')';
    }
}