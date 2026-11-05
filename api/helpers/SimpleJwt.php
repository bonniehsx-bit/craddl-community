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

/**
 * 一个极简的 JWT 生成类，仅支持 HS256，无任何依赖。
 * 兼容 PHP 5.4+
 */
class SimpleJwt
{
    /**
     * 生成 Token
     * @param array $payload 载荷数据
     * @param string $key 密钥 (AppSecret)
     * @return string JWT Token
     */
    public static function encode(array $payload, $key)
    {
        // 1. Header (固定使用 HS256)
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];

        $segments = [];
        $segments[] = self::urlsafeB64Encode(json_encode($header));
        $segments[] = self::urlsafeB64Encode(json_encode($payload));

        // 2. 拼接前两段
        $signing_input = implode('.', $segments);

        // 3. 生成签名 (HMAC SHA256)
        $signature = hash_hmac('sha256', $signing_input, $key, true);
        $segments[] = self::urlsafeB64Encode($signature);

        // 4. 返回完整 Token
        return implode('.', $segments);
    }

    /**
     * URL 安全的 Base64 编码 (JWT 标准要求替换 +/ 为 -_ 且去掉 =)
     */
    private static function urlsafeB64Encode($input)
    {
        return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
    }
}