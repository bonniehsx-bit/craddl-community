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

use app\models\User;

class WechatMchTransferHelper
{
    /**
     * @notes 商家转账到零钱
     * @param $cash_no //提现订单号
     * @param $transfer_amount //转账金额 单位 元
     * @param $user_openid //用户openID
     * @param $withdraw_name //提现金额大于200,用户真实名字必填
     * @return bool
     * @throws \Exception
     */
    public static function transfer($cash_no, $transfer_amount, $user_openid, $desc = '商家转账到零钱', $withdraw_name = '')
    {
        $user = User::findOne(['openid' => $user_openid, 'mall_id' => \Yii::$app->mall->id]);
        if (!$user) {
            throw new \Exception('未找到用户信息');
        }
        $transfer_amount = pay_fee($transfer_amount);
        $platform = $user->platform;
        \Yii::$app->platform = $platform;
        \app\helpers\WechatHelper::init(\Yii::$app->mall->id ?? 1);
        $app = \Yii::$app->wechat->payment;
        $appConfig = $app->getConfig();
        $config = [
            'app_id' => $appConfig['app_id'],
            'mch_id' => $appConfig['mch_id'],
            'cert_client' => $appConfig['certificate'],
            'cert_key' => $appConfig['private_key'],
        ];
        if (!file_exists($config['cert_client'] || !file_exists($config['cert_key']))) {
            throw  new \Exception('签名文件不存在!');
        }
        $withdrawApply = [
            'orderid' => '',
        ];
        //请求URL
        $url = 'https://api.mch.weixin.qq.com/v3/transfer/batches';
        //请求方式
        $http_method = 'POST';
        //请求参数
        $data = [
            'appid' => $config['app_id'], //申请商户号的appid或商户号绑定的appid（企业号corpid即为此appid）
            'out_batch_no' => $cash_no, //商户系统内部的商家批次单号，要求此参数只能由数字、大小写字母组成，在商户系统内部唯一
            'batch_name' => $desc, //该笔批量转账的名称
            'batch_remark' => $desc, //转账说明，UTF8编码，最多允许32个字符
            'total_amount' => $transfer_amount, //转账金额单位为“分”。转账总金额必须与批次内所有明细转账金额之和保持一致，否则无法发起转账操作
            'total_num' => 1, //一个转账批次单最多发起三千笔转账。转账总笔数必须与批次内所有明细之和保持一致，否则无法发起转账操作
            'transfer_detail_list' => [
                [ //发起批量转账的明细列表，最多三千笔
                    'out_detail_no' => $cash_no, //商户系统内部区分转账批次单下不同转账明细单的唯一标识，要求此参数只能由数字、大小写字母组成
                    'transfer_amount' => $transfer_amount, //转账金额单位为分
                    'transfer_remark' => $desc, //单条转账备注（微信用户会收到该备注），UTF8编码，最多允许32个字符
                    'openid' => $user_openid, //openid是微信用户在公众号appid下的唯一用户标识（appid不同，则获取到的openid就不同），可用于永久标记一个用户
                ]
            ]
        ];
        if ($transfer_amount >= 200000) {
            if (empty($withdraw_name)) {
                throw new \Exception('转账金额 >= 2000元，收款用户真实姓名必填');
            }
            $data['transfer_detail_list'][0]['user_name'] = self::getEncrypt($withdrawApply['real_name'], $config);
        }

        $token = self::token($url, $http_method, $data, $config); //获取token
        $result = self::https_request($url, json_encode($data), $token); //发送请求
        $result_arr = json_decode($result, true);

        if (!isset($result_arr['create_time'])) { //批次受理失败
            throw new \Exception($result_arr['message']);
        }
        //成功返回信息  {"batch_id":"1030001036201351072852022101201442513049","create_time":"2022-10-12T22:08:21+08:00","out_batch_no":"20221011004103000000146822"}
        //批次受理成功，更新提现申请单为提现中状态
        //业务修改为提现中

        return $result_arr;
    }

    /**
     * @param $cash_no
     * @param $transfer_amount
     * @param $user_openid
     * @param $desc
     * @param $withdraw_name
     * @return mixed
     * @throws \Exception
     * 最新版微信支付企业付款到个人
     */
    public static function transferBills($order_no, $transfer_amount, $user_openid, $desc = '商家转账到零钱', $withdraw_name = '')
    {
        $user = User::findOne(['openid' => $user_openid, 'mall_id' => \Yii::$app->mall->id]);
        if (!$user) {
            throw new \Exception('未找到用户信息');
        }
        $transfer_amount = pay_fee($transfer_amount);
        $platform = $user->platform;
        \Yii::$app->platform = $platform;
        \app\helpers\WechatHelper::init(\Yii::$app->mall->id ?? 1);
        $app = \Yii::$app->wechat->payment;
        $appConfig = $app->getConfig();
        $config = [
            'app_id' => $appConfig['app_id'],
            'mch_id' => $appConfig['mch_id'],
            'cert_client' => $appConfig['certificate'], //商家cert证书
            'cert_key' => $appConfig['private_key'], //商家key证书
        ];
        if (!file_exists($config['cert_client'] || !file_exists($config['cert_key']))) {
            throw  new \Exception('签名文件不存在!');
        }
        //请求URL
        $url = 'https://api.mch.weixin.qq.com/v3/fund-app/mch-transfer/transfer-bills';
        //请求方式
        $http_method = 'POST';
        //请求参数
        $data = [
            'appid' => $config['app_id'], //申请商户号的appid或商户号绑定的appid（企业号corpid即为此appid）
            'out_bill_no' => $order_no, //商户系统内部的商家批次单号，要求此参数只能由数字、大小写字母组成，在商户系统内部唯一
            'transfer_scene_id' => '1005', //转账场景ID
            'user_name' => $withdraw_name, //用户真实姓名转账金额 >= 2,000元时，该笔明细必须填写，若商户传入收款用户姓名，微信支付会校验收款用户与输入姓名是否一致，并提供电子回单
            'openid' => $user_openid, //用户openid
            'transfer_remark' => $desc, //转账说明，UTF8编码，最多允许32个字符
            'transfer_amount' => $transfer_amount, //转账金额单位为“分”。转账总金额必须与批次内所有明细转账金额之和保持一致，否则无法发起转账操作
            'notify_url' => '',
            'transfer_scene_report_infos' => [
                [
                    'info_type' => '活动名称',
                    'info_content' => '用户提现'
                ],
                [
                    'info_type' => '奖励说明',
                    'info_content' => '收益到账'
                ]
            ],
        ];
        if ($transfer_amount >= 200000) {
            if (empty($withdraw_name)) {
                throw new \Exception('转账金额 >= 2000元，收款用户真实姓名必填');
            }
        }
        $token = self::token($url, $http_method, $data, $config); //获取token
        $result = self::https_request($url, json_encode($data), $token); //发送请求
        $result_arr = json_decode($result, true);

        if (!isset($result_arr['create_time'])) { //批次受理失败
            throw new \Exception($result_arr['message']);
        }
        return $result_arr;
    }


    /**
     * @notes 签名生成
     * @param $url
     * @param $http_method
     * @param $data
     * @param $config
     * @return string
     * @author 动力宇宙 gxh
     * @date 2022/9/27 4:14 下午
     */
    public static function token($url, $http_method, $data, $config)
    {
        $timestamp = time(); //请求时间戳
        $url_parts = parse_url($url); //获取请求的绝对URL
        $nonce = $timestamp . rand('10000', '99999'); //请求随机串
        $body = empty($data) ? '' : json_encode((object)$data); //请求报文主体
        $stream_opts = [
            "ssl" => [
                "verify_peer" => false,
                "verify_peer_name" => false,
            ]
        ];
        $apiclient_cert_arr = openssl_x509_parse(file_get_contents($config['cert_client'], false, stream_context_create($stream_opts)));
        $serial_no = $apiclient_cert_arr['serialNumberHex']; //证书序列号
        $mch_private_key = file_get_contents($config['cert_key'], false, stream_context_create($stream_opts)); //密钥
        $merchant_id = $config['mch_id']; //商户id
        $canonical_url = ($url_parts['path'] . (!empty($url_parts['query']) ? "?{$url_parts['query']}" : ""));
        $message = $http_method . "\n" .
            $canonical_url . "\n" .
            $timestamp . "\n" .
            $nonce . "\n" .
            $body . "\n";
        openssl_sign($message, $raw_sign, $mch_private_key, 'sha256WithRSAEncryption');
        $sign = base64_encode($raw_sign); //签名
        $schema = 'WECHATPAY2-SHA256-RSA2048';
        $token = sprintf(
            'mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"',
            $merchant_id,
            $nonce,
            $timestamp,
            $serial_no,
            $sign
        ); //微信返回token
        return $schema . ' ' . $token;
    }

    /**
     * @notes 发送请求
     * @param $url
     * @param $data
     * @param $token
     * @return bool|string
     * @author 动力宇宙 gxh
     * @date 2022/9/27 4:15 下午
     */
    public static function https_request($url, $data, $token)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, (string)$url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        //添加请求头
        $headers = [
            'Authorization:' . $token,
            'Accept: application/json',
            'Content-Type: application/json; charset=utf-8',
            'User-Agent:Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.132 Safari/537.36',
        ];
        if (!empty($headers)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

    /**
     * @notes 敏感信息加解密
     * @param $str
     * @param $config
     * @return string
     * @throws \Exception
     * @author 动力宇宙 gxh
     * @date 2022/9/27 3:53 下午
     */
    public static function getEncrypt($str, $config)
    {
        //$str是待加密字符串
        $public_key = file_get_contents($config['cert_client']);
        $encrypted = '';
        if (openssl_public_encrypt($str, $encrypted, $public_key, OPENSSL_PKCS1_OAEP_PADDING)) {
            //base64编码
            $sign = base64_encode($encrypted);
        } else {
            throw new \Exception('encrypt failed');
        }
        return $sign;
    }

    /**
     * @notes 商家明细单号查询明细单API
     * @param $withdrawApply
     * @param $config
     * @return mixed
     * @author 动力宇宙 gxh
     * @date 2022/9/27 5:54 下午
     */
    public static function details($withdrawApply, $config)
    {
        //请求URL
        $url = 'https://api.mch.weixin.qq.com/v3/transfer/batches/out-batch-no/' . $withdrawApply['batch_no'] . '/details/out-detail-no/' . $withdrawApply['sn'];
        //请求方式
        $http_method = 'GET';
        //请求参数
        $data = [];
        $token = self::token($url, $http_method, $data, $config); //获取token
        $result = self::https_request($url, $data, $token); //发送请求
        $result_arr = \yii\helpers\Json::decode($result);
        return $result_arr;
    }
}
