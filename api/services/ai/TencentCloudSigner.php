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

namespace app\services\ai;

/**
 * 腾讯云 TC3-HMAC-SHA256 签名工具
 *
 * 多个腾讯云 API 驱动共用（一句话识别、录音文件识别等），从 AiGateway 抽出来
 * 避免每个 Provider 各写一份签名逻辑。
 */
class TencentCloudSigner
{
    public static function sign(
        string $secretId,
        string $secretKey,
        string $host,
        string $service,
        int $timestamp,
        string $date,
        string $payload
    ): string {
        $contentType   = 'application/json; charset=utf-8';
        $signedHeaders = 'content-type;host';

        $canonicalRequest = implode("\n", [
            'POST', '/', '',
            "content-type:{$contentType}\nhost:{$host}\n",
            $signedHeaders,
            hash('sha256', $payload),
        ]);

        $credentialScope = "{$date}/{$service}/tc3_request";
        $stringToSign = implode("\n", [
            'TC3-HMAC-SHA256',
            $timestamp,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $secretDate    = hash_hmac('sha256', $date,         'TC3' . $secretKey, true);
        $secretService = hash_hmac('sha256', $service,      $secretDate,        true);
        $secretSigning = hash_hmac('sha256', 'tc3_request', $secretService,     true);
        $signature     = hash_hmac('sha256', $stringToSign, $secretSigning);

        return "TC3-HMAC-SHA256 Credential={$secretId}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";
    }
}
