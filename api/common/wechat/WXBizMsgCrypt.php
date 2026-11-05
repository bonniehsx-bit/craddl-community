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

namespace app\common\wechat;

use Exception;
use Yii;

class WXBizMsgCrypt
{
    private $token;
    private $encodingAesKey;
    private $appId;

    public function __construct($token, $encodingAesKey, $appId)
    {
        $this->token = trim($token); // 过滤掉可能的空格
        $this->encodingAesKey = trim($encodingAesKey);
        $this->appId = trim($appId);
    }

    /**
     * 验证 URL (GET 请求中的 echostr 解密)
     */
    public function verifyUrl($msgSignature, $timestamp, $nonce, $echostr)
    {
        // 1. 签名校验
        $signature = $this->getSHA1($this->token, $timestamp, $nonce, $echostr);
        if ($signature != $msgSignature) {
            Yii::error("签名验证不通过");
            return false;
        }

        // 2. 解密 echostr
        $result = $this->decrypt($echostr);
        return $result; // 直接返回明文字符串
    }

    /**
     * 解密核心方法
     */
    public function decrypt($encryptMsg)
    {
        try {
            $aesKey = base64_decode($this->encodingAesKey . '=');
            $ciphertext = base64_decode($encryptMsg);

            // 使用 OPENSSL_ZERO_PADDING 配合手动 PKCS7 解码，或者直接用默认填充
            $decrypted = openssl_decrypt(
                $ciphertext,
                'aes-256-cbc',
                $aesKey,
                OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
                substr($aesKey, 0, 16)
            );

            // 1. 去除 PKCS7 填充
            $decrypted = $this->decodePKCS7($decrypted);

            // 2. 剥离 16 字节随机串
            $content = substr($decrypted, 16);

            // 3. 读取 4 字节明文长度
            $lenList = unpack("N", substr($content, 0, 4));
            $xmlLen = $lenList[1];

            // 4. 截取明文和 AppId
            $xmlContent = substr($content, 4, $xmlLen);
            $fromAppId = substr($content, $xmlLen + 4);


            if ($fromAppId !== $this->appId) {
                Yii::error("AppID 不匹配: " . $fromAppId);
                return false;
            }

            return $xmlContent;
        } catch (Exception $e) {
            Yii::error("解密异常: " . $e->getMessage());
            return false;
        }
    }

    /**
     * PKCS7 解码
     */
    private function decodePKCS7($text)
    {
        $pad = ord(substr($text, -1));
        if ($pad < 1 || $pad > 32) $pad = 0;
        return substr($text, 0, (strlen($text) - $pad));
    }

    /**
     * 生成签名
     */
    private function getSHA1($token, $timestamp, $nonce, $encrypt)
    {
        $array = [$token, $timestamp, $nonce, $encrypt];
        sort($array, SORT_STRING);
        return sha1(implode($array));
    }

    /**
     * 检验消息的真实性，并且获取解密后的明文.
     * @param string $sMsgSignature 从接收消息的URL中获取的msg_signature参数
     * @param string $sTimeStamp 从接收消息的URL中获取的timestamp参数
     * @param string $sNonce 从接收消息的URL中获取的nonce参数
     * @param string $sPostData 从接收消息的URL中获取的整个post数据
     * @param string &$sMsg 解密后的原文，由于是用引用传递，执行后该变量即为明文XML
     * @return int 成功0，失败返回对应的错误码
     */
    public function DecryptMsg($sMsgSignature, $sTimeStamp, $sNonce, $sPostData, &$sMsg)
    {
        if (strlen($this->encodingAesKey) != 43) {
            return -40004; // 密钥长度错误
        }

        // 1. 解析 XML 获取 Encrypt 内容
        try {
            $xml = new \DOMDocument();
            $xml->loadXML($sPostData);
            $array_e = $xml->getElementsByTagName('Encrypt');
            $encrypt = $array_e->item(0)->nodeValue;
        } catch (Exception $e) {
            return -40002; // 解析XML失败
        }

        // 2. 验证签名
        $signature = $this->getSHA1($this->token, $sTimeStamp, $sNonce, $encrypt);
        if ($signature != $sMsgSignature) {
            return -40001; // 签名验证不通过
        }

        // 3. 解密
        $result = $this->decrypt($encrypt);
        if ($result === false) {
            return -40007; // 解密失败
        }

        $sMsg = $result;
        return 0;
    }
}