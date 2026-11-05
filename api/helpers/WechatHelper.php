<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-25
 * Time: 21:50
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
use EasyWeChat\Kernel\Exceptions\InvalidArgumentException;
use GuzzleHttp\Exception\GuzzleException;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\helpers\Json;
use yii\httpclient\Client;

class WechatHelper
{


    public static function init($mall_id)
    {
        $wechat = OptionHelper::get('platform_wechat', $mall_id);
        if ($wechat) {
            \Yii::$app->params['wechatConfig'] = [
                'app_id' => $wechat['appid'],         // AppID
                'secret' => $wechat['secret'],     // AppSecret
                'token' => $wechat['token'],          // Token
                'aes_key' => $wechat['aes_key'],     // EncodingAESKey，兼容与安全模式下请一定要填写！！！
                /**
                 * 指定 API 调用返回结果的类型：array(default)/collection/object/raw/自定义类名
                 * 使用自定义类名时，构造函数将会接收一个 `EasyWeChat\Kernel\Http\Response` 实例
                 */
                'response_type' => 'array',
                // 稳定版 access_token(stable_token 接口):同一个 appid 在本地、测试服、线上同时用也不会互相顶掉。
                // 普通 token 每换一次新的,旧的 5 分钟后就作废(40001),本地调一次微信就会让线上的小程序码/手机号登录/订阅消息全挂。
                'use_stable_access_token' => true,
            ];
        }
        $mpwx = OptionHelper::get('platform_mpwx', $mall_id);
        $wxapp = OptionHelper::get('platform_wxapp', $mall_id);
        try {
            if ($mpwx) {
                \Yii::$app->params['wechatMiniProgramConfig'] = [
                    'app_id' => $mpwx['appid'],         // AppID
                    'secret' => $mpwx['secret'],     // AppSecret
                    'use_stable_access_token' => true, // 同上:稳定版 token,多环境共用一个小程序不互相顶
                ];
            }
            if ($wxapp) {
                \Yii::$app->params['wechatOpenPlatformConfig'] = [
                    'app_id' => $wxapp['appid'],         // AppID
                    'secret' => $wxapp['secret'],     // AppSecret
                ];
            }
            $payment = OptionHelper::get('payment', $mall_id);
            if (\Yii::$app->platform == ConstantHelper::PLATFORM_MPWX) {
                if ($mpwx) {
                    $payment['wechat_app_id'] = $mpwx['appid'];
                }
            }
            $payment['wxapp_pay_mpwx_appid'] = '';
            if (\Yii::$app->platform == ConstantHelper::PLATFORM_APP) {
                if ($wxapp) {
                    $payment['wechat_app_id'] = $wxapp['appid'];
                    $payment['wxapp_pay_mpwx_appid'] = $wxapp['wxapp_pay_mpwx_appid'];
                }
            }
            if ($payment) {
                if (\Yii::$app->platform == ConstantHelper::PLATFORM_MPWX) {
                    $payment['wechat_pay_type'] = $payment['mpwx_wechat_pay_type'] ?? 'original';
                    $payment['join_app_secret'] = $payment['mpwx_join_app_secret'] ?? '';
                    $payment['join_private_key'] = $payment['mpwx_join_private_key'] ?? '';
                    $payment['join_trade_merchant_no'] = $payment['mpwx_join_trade_merchant_no'] ?? '';
                    $payment['join_merchant_no'] = $payment['mpwx_join_merchant_no'] ?? '';
                }

                \Yii::$app->params['wechatPaymentConfig'] = [
                    'app_id' => $payment['wechat_app_id'], //如果是app端这里要换成APP的支付
                    'mch_id' => $payment['wechat_mch_id'],
                    // V6 API 秘钥
                    'secret_key' => !empty($payment['wechat_v3_key']) ? $payment['wechat_v3_key'] : $payment['wechat_pay_secret'],
                    'v2_secret_key' => $payment['wechat_pay_secret'],
                    // 商户证书
                    'certificate' => $payment['wechat_cert_pem_path'],    // XXX: 绝对路径！！！！
                    'private_key' => $payment['wechat_key_pem_path'], // XXX: 绝对路径！！！！
                    'notify_url' => '',     // 你也可以在下单时单独设置来想覆盖它
                    'wxapp_pay_mpwx_appid' => $payment['wxapp_pay_mpwx_appid'],
                ];
            }
        } catch (\Exception $exception) {
            \Yii::warning($exception->getMessage());
        }
        return true;
    }


    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-10-28
     * @Time: 0:49
     * @Note:告诉微信已经成功了
     * @return mixed
     */
    public static function success()
    {
        return ArrayHelper::toXml(['return_code' => 'SUCCESS', 'return_msg' => 'OK']);
    }


    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-10-28
     * @Time: 0:50
     * @Note:告诉微信失败了
     * @return mixed
     */
    public static function fail()
    {
        return ArrayHelper::toXml(['return_code' => 'FAIL', 'return_msg' => 'OK']);
    }

    /**
     * 数组转换成XML数据
     * @param $array
     * @return string
     * @throws \Exception
     */
    public static function arrayToXml($array)
    {
        if (!is_array($array)) {
            throw new Exception('`$arr`不是有效的array。');
        }
        $xml = "<xml>\r\n";
        $xml .= self::arrayToXmlSub($array);
        $xml .= "</xml>";
        return $xml;
    }

    private static function arrayToXmlSub($array)
    {
        if (!is_array($array)) {
            throw new Exception('`$array`不是有效的array。');
        }
        $xml = "";
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                if (is_numeric($key)) {
                    $xml .= self::arrayToXmlSub($val);
                } else {
                    $xml .= "<" . $key . ">" . self::arrayToXmlSub($val) . "</" . $key . ">\r\n";
                }
            } elseif (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">\r\n";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">\r\n";
            }
        }
        return $xml;
    }

    /**
     * 是否是xml
     * @param $str
     * @return bool|mixed
     */
    public static function isXmlString($str)
    {
        $xml_parser = xml_parser_create();
        if (!xml_parse($xml_parser, $str, true)) {
            xml_parser_free($xml_parser);
            return false;
        } else {
            return (json_decode(json_encode(simplexml_load_string($str)), true));
        }
    }

    public static function xmlToArray($xml)
    {
        $res = [];
        if (self::isXmlString($xml)) {
            // 禁止引用外部xml实体

            if (PHP_VERSION_ID < 80000) {
                libxml_disable_entity_loader(true);
            }
            $res = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        }
        return (array)$res;
    }

    /**
     * Created by PhpStorm.
     * User：ganxi
     * Date：2022/4/22
     * Time：13:11
     * Note：商户平台接口封装
     */
    public static function post($url, $body, $mall_id)
    {
        $mpwx = OptionHelper::get('platform_mpwx', $mall_id);
        if (!$mpwx) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '未配置小程序');
        }
        $payment = OptionHelper::get('payment', $mall_id);
        if (!$mpwx) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '为配置微信支付信息');
        }
        $url_parts = parse_url($url);
        // 修复：${var} -> {$var}
        $canonical_url = ($url_parts['path'] . (!empty($url_parts['query']) ? "?{$url_parts['query']}" : ""));
        $timestamp = time();
        $nonce = uniqid();
        if (is_array($body)) {
            $body = Json::encode($body);
        }
        $message = 'POST' . "\n" .
            $canonical_url . "\n" .
            $timestamp . "\n" .
            $nonce . "\n" .
            $body . "\n";
        $mch_private_key = $payment['wechat_key_pem'];
        openssl_sign($message, $raw_sign, $mch_private_key, 'sha256WithRSAEncryption');
        $sign = base64_encode($raw_sign);
        $serial_no = $payment['wechat_serial_no'];
        $schema = 'WECHATPAY2-SHA256-RSA2048';
        $token = sprintf(
            'mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"',
            $payment['wechat_mch_id'],
            $nonce,
            $timestamp,
            $serial_no,
            $sign
        );
        $token = $schema . " " . $token;
        $client = new Client([
            'transport' => 'yii\httpclient\CurlTransport'
        ]);
        try {
            $response = $client->createRequest()
                ->setUrl($url)
                ->setHeaders(['Authorization' => $token, 'Content-Type' => 'application/json', 'Accept' => 'application/json', 'User-Agent' => $_SERVER['HTTP_USER_AGENT']])
                ->setMethod('POST')
                ->setContent($body)->send();
        } catch (InvalidConfigException $e) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $e->getMessage());
        }
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', ['result' => $response->content]);
    }

    /**
     * Created by PhpStorm.
     * User：ganxi
     * Date：2022/4/22
     * Time：13:11
     * Note：商户平台接口封装
     */
    public static function get($url, $body = '', $mall_id = 1)
    {
        $mpwx = OptionHelper::get('platform_mpwx', $mall_id);
        if (!$mpwx) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '未配置小程序');
        }
        $payment = OptionHelper::get('payment', $mall_id);
        if (!$mpwx) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '未配置微信支付信息');
        }
        $url_parts = parse_url($url);
        // 修复：${var} -> {$var}
        $canonical_url = ($url_parts['path'] . (!empty($url_parts['query']) ? "?{$url_parts['query']}" : ""));
        $timestamp = time();
        $nonce = uniqid();
        if (is_array($body)) {
            $body = Json::encode($body);
        }
        $message = 'GET' . "\n" .
            $canonical_url . "\n" .
            $timestamp . "\n" .
            $nonce . "\n" .
            $body . "\n";
        $mch_private_key = $payment['wechat_key_pem'];
        openssl_sign($message, $raw_sign, $mch_private_key, 'sha256WithRSAEncryption');
        $sign = base64_encode($raw_sign);
        $serial_no = $payment['wechat_serial_no'];
        $schema = 'WECHATPAY2-SHA256-RSA2048';
        $token = sprintf(
            'mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"',
            $payment['wechat_mch_id'],
            $nonce,
            $timestamp,
            $serial_no,
            $sign
        );
        $token = $schema . " " . $token;
        $client = new Client([
            'transport' => 'yii\httpclient\CurlTransport'
        ]);
        try {
            $response = $client->createRequest()
                ->setUrl($url)
                ->setHeaders(['Authorization' => $token, 'Content-Type' => 'application/json', 'Accept' => 'application/json', 'User-Agent' => $_SERVER['HTTP_USER_AGENT']])
                ->setMethod('GET')
                ->setContent($body)->send();
        } catch (InvalidConfigException $e) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $e->getMessage());
        }
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', ['result' => $response->content]);
    }


    public static function sendTplMsg($openid, $tpl_id, $msg_data = [], $page = 'pages/index/index', $mall_id = 1)
    {
        /**
         * [ 'key1' => ['value' => $val]],*/
        $arr = [];
        foreach ($msg_data as $key => $val) {
            $arr[$key] = ['value' => $val];
        }

        self::init($mall_id);
        $app = \Yii::$app->wechat->miniProgram;

        $data = [
            'template_id' => $tpl_id, // 所需下发的订阅模板id
            'touser' => $openid,     // 接收者（用户）的 openid
            'page' => $page,       // 点击模板卡片后的跳转页面，仅限本小程序内的页面。支持带参数,（示例index?foo=bar）。该字段不填则模板无跳转。
            'data' => $arr
        ];
        try {
            $client = $app->getClient();
            $response = $client->postJson('cgi-bin/message/subscribe/send', $data);
            $res = $response->toArray(false);
            \Yii::warning($res);
            return $res;
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            \Yii::warning($e->getMessage());
        } catch (\Exception $e) {
            \Yii::warning($e->getMessage());
        }
        return false;
    }

    public static function upload_shipping_info($form, $mall_id = 1)
    {
        WechatHelper::init($mall_id);
        $app = \Yii::$app->wechat->miniProgram;
        $token = CacheHelper::get('mpwx_token_' . $mall_id);
        if (!$token) {
            $tokenStr = $app->getAccessToken()->getToken();
            if ($tokenStr) {
                $token = $tokenStr;
                CacheHelper::set('mpwx_token_' . $mall_id, $token, 1000);
            }
        }
        // 修复：${var} -> {$var}
        $res = HttpHelper::postRaw("https://api.weixin.qq.com/wxa/sec/order/upload_shipping_info?access_token={$token}", $form);
        \Yii::warning($res);
        if ($res && $res['errcode'] == 40001) {
            CacheHelper::remove('mpwx_token_' . $mall_id);
        }
        return $res;
    }

    /**
     * 微信运力(快递公司)列表:发货信息录入的 express_company 必须填这里的 delivery_id
     *
     * 列表只有接口能拿到,文档里没有;微信物流助手等其它能力的快递编码是另一套(如韵达 YUNDA/YD),不能混用。
     * 一天变不了几次,按商城缓存一天;取失败返回 null,调用方自行兜底,失败后 10 分钟内不重试。
     *
     * @return array|null [['delivery_id' => 'SF', 'delivery_name' => '顺丰速运'], ...]
     */
    public static function get_delivery_list($mall_id = 1)
    {
        $cacheKey = 'mpwx_delivery_list_' . $mall_id;
        // 没开这个接口权限的小程序会一直失败:失败后 10 分钟内不再请求,免得每推一单都打一次微信
        $failKey = 'mpwx_delivery_list_fail_' . $mall_id;
        try {
            $cached = CacheHelper::get($cacheKey);
            if (is_array($cached) && $cached) {
                return $cached;
            }
            if (CacheHelper::get($failKey)) {
                return null;
            }

            WechatHelper::init($mall_id);
            $app = \Yii::$app->wechat->miniProgram;
            $token = CacheHelper::get('mpwx_token_' . $mall_id);
            if (!$token) {
                $token = $app->getAccessToken()->getToken();
                if (!$token) {
                    CacheHelper::set($failKey, 1, 600);
                    return null;
                }
                CacheHelper::set('mpwx_token_' . $mall_id, $token, 1000);
            }
            $res = HttpHelper::postRaw("https://api.weixin.qq.com/cgi-bin/express/delivery/open_msg/get_delivery_list?access_token={$token}", '{}');

            $list = $res['delivery_list'] ?? null;
            if (is_array($list) && $list) {
                CacheHelper::set($cacheKey, $list, 86400);
                return $list;
            }
            if (in_array((int)($res['errcode'] ?? 0), [40001, 40014, 42001], true)) {
                // 凭证失效 / 过期:清掉缓存的 token,下一单重新取。这不算接口不可用,不进 10 分钟冷却
                CacheHelper::remove('mpwx_token_' . $mall_id);
                return null;
            }
            \Yii::warning('get_delivery_list 返回异常: ' . json_encode($res, JSON_UNESCAPED_UNICODE));
            CacheHelper::set($failKey, 1, 600);
            return null;
        } catch (\Throwable $e) {
            \Yii::warning('get_delivery_list 失败: ' . $e->getMessage());
            try {
                CacheHelper::set($failKey, 1, 600);
            } catch (\Throwable $ignored) {
                // 缓存本身挂了就算了,调用方按取不到处理
            }
            return null;
        }
    }

    // =================================================================
    // ================== [ 新增方法 ] ==================
    // =================================================================

    /**
     * [新增] 设置小程序订单消息跳转路径 (全局一次性设置)
     *
     * @param string $jumpPath 要跳转的小程序页面路径, e.g., 'pages/order/list'
     * @param int $mall_id
     * @return array
     */
    public static function set_msg_jump_path($jumpPath, $mall_id = 1)
    {
        // 1. 初始化 (复用)
        WechatHelper::init($mall_id);
        $app = \Yii::$app->wechat->miniProgram;

        // 2. 获取Token (完全复用 upload_shipping_info 的逻辑)
        $token = CacheHelper::get('mpwx_token_' . $mall_id);
        if (!$token) {
            try {
                $tokenStr = $app->getAccessToken()->getToken();
                if (empty($tokenStr)) {
                    throw new \Exception('getToken 返回数据异常');
                }
                $token = $tokenStr;
                CacheHelper::set('mpwx_token_' . $mall_id, $token, 7000);
            } catch (\Exception $e) {
                \Yii::error('set_msg_jump_path 获取 token 失败: ' . $e->getMessage());
                return ['errcode' => -1, 'errmsg' => '获取AccessToken失败'];
            }
        }

        // 3. 准备API和数据
        // 修复：${var} -> {$var}
        $apiUrl = "https://api.weixin.qq.com/wxa/sec/order/set_msg_jump_path?access_token={$token}";
        $form = [
            'path' => $jumpPath,
        ];

        // 4. 调用 (完全复用 upload_shipping_info 的调用方式)
        // 假设 HttpHelper::postRaw 会自动 json_encode $form
        $res = HttpHelper::postRaw($apiUrl, $form);
        \Yii::warning("set_msg_jump_path result: " . json_encode($res, JSON_UNESCAPED_UNICODE));

        // 假设 HttpHelper::postRaw 返回的是一个数组 (同 upload_shipping_info)
        return $res;
    }
}
