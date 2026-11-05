<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2022-05-29
 * Time: 0:36
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

namespace app\components;

use app\helpers\ServerHelper;
use joinpay\entity\OrderQuery;
use joinpay\entity\Refund;
use joinpay\entity\SinglePay;
use joinpay\entity\UniPay;
use joinpay\JoinPayClient;
use yii\base\Component;
use yii\helpers\Json;

class JoinPay extends Component
{

    public function pay($params)
    {
        $pay_map = ['H5' => 'WEIXIN_GZH', 'WECHAT' => 'WEIXIN_GZH', 'MPWX' => 'WEIXIN_XCX', 'APP' => 'WEIXIN_APP3'];
        $params['frp_code'] = $pay_map[\Yii::$app->platform];
        $setting = \Yii::$app->params['wechatPaymentConfig'];
        if ($params['frp_code'] == $pay_map['APP']) {
            $setting['app_id'] = $setting['wxapp_pay_mpwx_appid'];
            \Yii::warning($setting['app_id']);
        }

        $config = [
            // 商户ID
            'app_id' => $setting['app_id'], // 商户加密密钥
            'app_secret' => $setting['join_app_secret'], // 报备商户号
            'private_key' => $setting['join_private_key'], // 商户私钥
            'trade_merchantNo' => $setting['join_trade_merchant_no'], // 报备商户号
            'merchant_no' => $setting['join_merchant_no'], //
        ];

        \Yii::warning($config);
        $joinPayClient = JoinPayClient::getInstance($config);
        /**
         * @var UniPay $joinPayClient ;
         */
        $joinPayClient = $joinPayClient->driver('UniPay'); // 使用驱动方式重新构造订单查询
        $res = $joinPayClient
            ->setVerison('1.0') // 版本号
            ->setOrderNo($params['order_no']) //
            ->setAmount($params['price'])
            ->setFrpCode($params['frp_code'])
            ->setProductName($params['goods_name'])
            ->setProductDesc($params['desc'])
            ->setNotifyUrl($params['notify_url'])
            ->setOpenId($params['open_id'])
            ->setMp(\Yii::$app->mall->id)
            ->send();
        if ($res && $res['ra_Code'] == 100) {
            $res = Json::decode($res['rc_Result']);
            if (isset($res['timeStamp'])) {
                $res['timestamp'] = $res['timeStamp'];
                unset($res['timeStamp']);
            }
            return $res;
        }
        throw new \yii\base\Exception($res['rb_CodeMsg']);
    }


    public function queryOrder($order_no)
    {
        $setting = \Yii::$app->params['wechatPaymentConfig'];
        $config = [
            // 商户ID
            'app_id' => $setting['app_id'], // 商户加密密钥
            'app_secret' => $setting['join_app_secret'], // 报备商户号
            'private_key' => $setting['join_private_key'], // 商户私钥
            'trade_merchantNo' => $setting['join_trade_merchant_no'], // 报备商户号
            'merchant_no' => $setting['join_merchant_no'], //
        ];
        $joinPayClient = JoinPayClient::getInstance($config);
        /**
         * @var OrderQuery $joinPayClient ;
         */
        $joinPayClient = $joinPayClient->driver('OrderQuery'); // 使用驱动方式重新构造订单查询
        $res = $joinPayClient
            ->setOrderNo($order_no) // 订单号
            ->send();
        return $res;
    }


    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2022-05-26
     * @Time: 19:22
     * @Note:退款
     * @param $order_no
     * @return mixed|string
     */
    public function refund($order_no, $refund_price, $refund_order_no, $reason = '订单退款')
    {
        $setting = \Yii::$app->params['wechatPaymentConfig'];
        $config = [
            // 商户ID
            'app_id' => $setting['app_id'], // 商户加密密钥
            'app_secret' => $setting['join_app_secret'], // 报备商户号
            'private_key' => $setting['join_private_key'], // 商户私钥
            'trade_merchantNo' => $setting['join_trade_merchant_no'], // 报备商户号
            'merchant_no' => $setting['join_merchant_no'], //
        ];
        $joinPayClient = JoinPayClient::getInstance($config);
        /**
         * @var OrderQuery $joinPayClient ;
         */
        $client = $joinPayClient->driver('Refund');// 使用驱动方式重新构造订单查询
        /**
         * @var Refund $client ;
         */
        $res = $client
            ->setVerison('2.1')
            ->setOrderNo($order_no) // 原支付订单号
            ->setRefundOrderNo($refund_order_no) // 退款订单号
            ->setRefundAmount($refund_price) // 退款金额
            ->setRefundReason($reason) // 退款原因
            ->setNotifyUrl(ServerHelper::getHost() . '/web/notify/refund.php') // 服务器异步通知地址
            ->send();
        return $res;
    }


    /**
     * Created by PhpStorm.
     * User：ganxi
     * Date：2022/5/23
     * Time：10:04
     * Note：单笔代付
     */
    public function singlePay($params)
    {
        $setting = \Yii::$app->params['wechatPaymentConfig'];
        $config = [
            // 商户ID
            'app_id' => $setting['app_id'], // 商户加密密钥
            'app_secret' => $setting['join_app_secret'], // 报备商户号
            'private_key' => $setting['join_private_key'], // 商户私钥
            'trade_merchantNo' => $setting['join_trade_merchant_no'], // 报备商户号
            'merchant_no' => $setting['join_merchant_no'], //
        ];
        $joinPayClient = JoinPayClient::getInstance($config);
        $joinPayClient->is_sort = false;
        /**
         * @var SinglePay $client ;
         */

        $client = $joinPayClient->driver('SinglePay'); // 使用驱动方式重新构造订单查询
        $arr = [
            "userNo" => $config['merchant_no'],
            "productCode" => "BANK_PAY_ORDINARY_ORDER",
            "requestTime" => "",
            "merchantOrderNo" => "",
            "receiverAccountNoEnc" => "",
            "receiverNameEnc" => "",
            "receiverAccountType" => "201",
            "receiverBankChannelNo" => "",
            "paidAmount" => "",
            "currency" => "201",
            "isChecked" => "202",
            "paidDesc" => "",
            "paidUse" => "201",
            "callbackUrl" => ""
        ];
        foreach ($arr as $k => $item) {
            if (isset($params[$k]) && $params[$k]) {
                $arr[$k] = $params[$k];
            }
        }
        $client->setParams($arr);
        return $client->send(true, false, 'POST');
    }
}
