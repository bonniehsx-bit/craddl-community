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

use app\helpers\CacheHelper;
use Yii;
use Exception;
use DOMDocument;
use app\models\CommonPayOrder;
use app\helpers\ServerHelper;

class WxWorkHelper
{
    /**
     * 点击菜单按钮显示的文字
     */
    const CLICK_MENU_CONTENT = "👉 点击此处获取支付链接";

    /**
     * 处理解密后的业务逻辑
     */
    public static function processBusinessLogic($sMsg, $config): string
    {
        // 1. 解析 XML
        $xml = new DOMDocument();
        $xml->loadXML($sMsg);

        $eventNode = $xml->getElementsByTagName('Event')->item(0)->nodeValue;

        // 2. 客服账号授权变更
        if ($eventNode == "kf_account_auth_change") {
            Yii::warning("收到：客服账号授权变更事件推送", 'wxwork');
            CacheHelper::remove(WxWorkConst::KF_ACCOUNT_LIST . $config['corpId']);
            return "success";
        }

        // 3. 客服消息或事件
        if ($eventNode == "kf_msg_or_event") {
            Yii::warning("收到：接收消息和事件推送", 'wxwork');

            $accessToken = self::getAccessToken($config);
            $config['accessToken'] = $accessToken;

            $token = $xml->getElementsByTagName('Token')->item(0)->nodeValue;
            $openKfId = $xml->getElementsByTagName('OpenKfId')->item(0)->nodeValue;
            $nextCursor = CacheHelper::get(WxWorkConst::KF_NEXT_CURSOR . $openKfId);

            $msg = self::getSyncMsg($accessToken, $token, $openKfId, $nextCursor);

            if (empty($msg)) return "success";

            Yii::warning("企微消息内容: " . json_encode($msg, JSON_UNESCAPED_UNICODE), 'wxwork');

            $msgType = isset($msg['msgtype']) ? $msg['msgtype'] : NULL;

            // ==========================================
            // A. 处理进入会话事件 (enter_session)
            // ==========================================
            if ($msgType == "event") {
                $event = $msg['event'];
                $orderNo = isset($event['scene']) ? $event['scene'] : NULL;

                if ($event['event_type'] == "enter_session" && !empty($orderNo)) {
                    Yii::warning("用户进入会话，订单号：" . $orderNo, 'wxwork');

                    $payOrder = self::getPayOrderByOrderNo($orderNo, $config);

                    if ($payOrder) {
                        // 【变更点1】这里不再直接发支付链接，而是发送一个菜单消息
                        self::sendMenuMsg($event, $config, $payOrder);
                    }
                }
            }

            // ==========================================
            // B. 处理文本消息 (用户点击菜单)
            // ==========================================
            if ($msgType == "text") {
                $text = $msg['text'];
                // 获取菜单ID (如果是点击菜单产生的消息，会有这个字段)
                $menuId = isset($text['menu_id']) ? $text['menu_id'] : NULL;
                $content = $text['content'];

                // 判断是否是点击了我们设定的按钮
                if (!empty($menuId) && $content == self::CLICK_MENU_CONTENT) {
                    Yii::warning("用户点击了获取支付链接，订单号(MenuID)：" . $menuId, 'wxwork');

                    // 这里的 menuId 我们存的是 orderNo
                    $payOrder = self::getPayOrderByOrderNo($menuId, $config);

                    if ($payOrder) {
                        // 构建支付中转链接
                        $host = ServerHelper::getHost() ?: Yii::$app->request->hostInfo;
                        $payUrl = $host . "/index.php?r=wechat/wx-work-pay/index" .
                            "&mall_id=" . Yii::$app->mall->id .
                            "&pay_order_id=" . $payOrder['id'];

                        Yii::warning($payUrl);
                        // 【变更点2】收到点击后，才发送真正的支付链接
                        self::sendPayLinkMsg($msg, $config, $payOrder, $payUrl);
                    }
                }
            }
        }

        return "success";
    }

    /**
     * 发送菜单消息 (引导用户点击)
     */
    private static function sendMenuMsg($event, $config, $payOrder)
    {
        $params = [
            'msgtype' => 'msgmenu', // 菜单类型
            'open_kfid' => $event['open_kfid'],
            'touser' => $event['external_userid'],
            'welcomeCode' => isset($event['welcome_code']) ? $event['welcome_code'] : null,

            // 业务数据
            'orderAmount' => $payOrder['orderAmount'],
            'orderNo' => $payOrder['orderNo'], // 将订单号作为菜单ID

            'accessToken' => $config['accessToken']
        ];

        return self::wxWorkSendMsgService($params);
    }

    /**
     * 发送支付链接消息 (Link类型)
     */
    private static function sendPayLinkMsg($event, $config, $payOrder, $payUrl)
    {
        // 注意：如果是点击菜单触发的，这里通常没有 welcome_code，走主动发送接口
        $params = [
            'msgtype' => 'link',
            'open_kfid' => isset($event['open_kfid']) ? $event['open_kfid'] : '',
            'touser' => isset($event['external_userid']) ? $event['external_userid'] : '',

            // 只有 enter_session 才有 welcomeCode，点击菜单时为 null
            'welcomeCode' => null,

            'orderTitle' => $payOrder['orderTitle'],
            'orderDesc' => $payOrder['orderDesc'],
            'payUrl' => $payUrl,
            'logoUrl' => self::getLogoUrl($config),
            'accessToken' => $config['accessToken']
        ];

        return self::wxWorkSendMsgService($params);
    }

    /**
     * 统一发送消息服务
     */
    private static function wxWorkSendMsgService($params)
    {
        $reqData = [];
        $url = "";

        // 判断接口类型
        if (!empty($params['welcomeCode'])) {
            $url = "https://qyapi.weixin.qq.com/cgi-bin/kf/send_msg_on_event";
            $reqData['code'] = $params['welcomeCode'];
            $reqData['msgid'] = md5(uniqid(mt_rand(), true));
        } else {
            $url = "https://qyapi.weixin.qq.com/cgi-bin/kf/send_msg";
            $reqData['touser'] = $params['touser'];
            $reqData['open_kfid'] = $params['open_kfid'];
            $reqData['msgid'] = md5(uniqid(mt_rand(), true));
        }

        $url .= '?access_token=' . $params['accessToken'];
        $reqData['msgtype'] = $params['msgtype'];

        // 1. 构造 菜单消息 (msgmenu)
        if ($params['msgtype'] == 'msgmenu') {
            $reqData['msgmenu'] = [
                'head_content' => "您的订单已生成，金额：￥" . $params['orderAmount'],
                'list' => [
                    [
                        'type' => 'click',
                        'click' => [
                            'id' => $params['orderNo'], // 关键：把订单号放在id里
                            'content' => self::CLICK_MENU_CONTENT // 按钮文字
                        ]
                    ]
                ],
                'tail_content' => "请点击上方链接进行支付"
            ];
        } // 2. 构造 链接消息 (link)
        elseif ($params['msgtype'] == 'link') {
            // 兜底逻辑：如果 Logo 获取失败，转为 Text
            if (empty($params['logoUrl'])) {
                $reqData['msgtype'] = 'text';
                $reqData['text'] = [
                    'content' => "【" . $params['orderTitle'] . "】\n" .
                        $params['orderDesc'] . "\n\n" .
                        "👉 <a href=\"" . $params['payUrl'] . "\">点击此处立即支付</a>"
                ];
                // 移除 link 字段
                if (isset($reqData['link'])) unset($reqData['link']);
            } else {
                $reqData['link'] = [
                    'title' => $params['orderTitle'],
                    'desc' => $params['orderDesc'],
                    'url' => $params['payUrl'],
                    'thumb_media_id' => $params['logoUrl']
                ];
            }
        }

        $response = self::curlPost($url, $reqData);
        $resData = json_decode($response, true);

        if (isset($resData['errcode']) && $resData['errcode'] !== 0) {
            Yii::error("发送企微消息失败: " . $response, 'wxwork');
        } else {
            Yii::warning("发送企微消息成功 MsgId: " . ($resData['msgid'] ?? ''), 'wxwork');
        }

        return isset($resData['msgid']) ? $resData['msgid'] : '';
    }

    /**
     * 根据订单号查询真实订单
     */
    private static function getPayOrderByOrderNo($orderNo, $config): ?array
    {
        $order = CommonPayOrder::findOne(['order_no' => $orderNo]);
        if (!$order) return null;

        return [
            'id' => $order->id,
            'orderNo' => $order->order_no,
            'orderAmount' => $order->pay_price,
            'orderTitle' => '订单支付',
            'orderDesc' => '支付金额：￥' . $order->pay_price
        ];
    }

    /**
     * 获取 Logo MediaID
     */
    private static function getLogoUrl($config)
    {
        $cacheKey = WxWorkConst::WX_LOGO_ID . $config['corpId'];
        $mediaId = CacheHelper::get($cacheKey);

        if ($mediaId) return $mediaId;

        $fileUrl = WxWorkConst::PAY_ICON_URL;
        if (empty($fileUrl)) {
            $fileUrl = "https://quickpay-dev.oss-cn-guangzhou.aliyuncs.com/quickpay/logo/dee2ce5762e84a87a6da62e17fb2e208.png";
        }

        $tempFile = Yii::getAlias('@runtime') . '/wxwork_logo_' . time() . '.png';

        try {
            $imgContent = @file_get_contents($fileUrl);
            if (!$imgContent) return '';

            file_put_contents($tempFile, $imgContent);

            $url = 'https://qyapi.weixin.qq.com/cgi-bin/media/upload?type=image&access_token=' . $config['accessToken'];

            $data = class_exists('\CURLFile') ? ['media' => new \CURLFile(realpath($tempFile))] : ['media' => '@' . realpath($tempFile)];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);

            $response = curl_exec($ch);
            curl_close($ch);
            @unlink($tempFile);

            $resData = json_decode($response, true);
            if (isset($resData['media_id'])) {
                CacheHelper::set($cacheKey, $resData['media_id'], 86400 * 2);
                return $resData['media_id'];
            }
            return '';
        } catch (Exception $e) {
            @unlink($tempFile);
            return '';
        }
    }

    private static function getAccessToken($config): string
    {
        $cacheKey = WxWorkConst::ACCESS_TOKEN . $config['corpId'];
        $accessToken = CacheHelper::get($cacheKey);

        if (!$accessToken) {
            $url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=" . $config['corpId'] . "&corpsecret=" . $config['corpSecret'];
            $response = self::curlGet($url);
            $resData = json_decode($response, true);
            if (isset($resData['errcode']) && $resData['errcode'] !== 0) throw new Exception("AccessToken Error");
            $accessToken = $resData['access_token'];
            CacheHelper::set($cacheKey, $accessToken, 7000);
        }
        return $accessToken;
    }

    private static function getSyncMsg($accessToken, $token, $openKfId, $nextCursor): array
    {
        $url = "https://qyapi.weixin.qq.com/cgi-bin/kf/sync_msg?access_token=" . $accessToken;
        $params = ['token' => $token, 'open_kfid' => $openKfId];
        if ($nextCursor) $params['cursor'] = $nextCursor;

        $response = self::curlPost($url, $params);
        $resData = json_decode($response, true);

        if (isset($resData['errcode']) && $resData['errcode'] !== 0) {
            if ($resData['errcode'] == 40055) {
                CacheHelper::delete(WxWorkConst::KF_NEXT_CURSOR . $openKfId);
                return [];
            }
            throw new Exception("SyncMsg Error: " . $resData['errcode']);
        }
        if (!empty($resData['next_cursor'])) CacheHelper::set(WxWorkConst::KF_NEXT_CURSOR . $openKfId, $resData['next_cursor'], 86400 * 365);
        $msgList = isset($resData['msg_list']) ? $resData['msg_list'] : [];
        if (empty($msgList)) return [];
        $column = array_column($msgList, 'send_time');
        array_multisort($column, SORT_ASC, $msgList);
        return end($msgList);
    }

    private static function curlGet($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    private static function curlPost($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $resp = curl_exec($ch);
        curl_close($ch);
        return $resp;
    }
}