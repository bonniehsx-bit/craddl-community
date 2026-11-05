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
/**
 * Created by PhpStorm.
 * User: ganxi
 * Date: 2022/5/26
 * Time: 11:05
 * Note:
 */

namespace app\controllers\notify;


use app\helpers\Helper;
use app\helpers\WechatHelper;
use app\models\Mall;
use app\payments\BasePayment;
use yii\base\InvalidConfigException;
use yii\helpers\Json;
use yii\web\Response;

class PayController extends \yii\web\Controller
{
    /**
     * 允许由本端点按 attach.class 反射实例化的支付类白名单。
     *
     * 依据:全项目只有这 3 个类会在下单时往 attach 里塞 class(grep `'attach'` payments/),
     * 而且它们的 handleCallback 都不信任传进来的数组、自己用微信 SDK 重新读请求并验签,
     * 所以名单里的类即使被伪造报文点名也过不了签。
     *
     * 🔒 这不是防御性编程,是堵一个已实际打通的漏洞:attach 是报文自带的、反射前不验签,
     * 报文里写 `{"class":"app\\payments\\SystemPayment"}` 就能让 SystemPayment::handleCallback
     * 按报文里的 pay_order_id 把任意订单置为已支付(它是后台人工补单用的,全程不验签)。
     * 本地实测一条无签名 XML 即可写入 transaction_id=SYS_…。
     * SystemPayment 的合法入口只有 OrderForm 服务端直接 new,从不经 HTTP,拒掉零副作用。
     * 与 notify/FastController::ALLOWED_PAYMENTS 同一思路。
     */
    private const ALLOWED_PAYMENTS = [
        \app\payments\WechatPayment::class,
        \app\payments\MpwxPayment::class,
        \app\payments\WechatAppPayment::class,
    ];

    public $enableCsrfValidation = false;
    public $request;

    public function init()
    {
        \Yii::$app->isAdmin = false;
        // mall 上下文延迟到 actionIndex 中从 attach 提取，不再硬编码
    }

    public function actionIndex()
    {
        \Yii::warning('===================订单支付异步通知=====================');
        $this->request = \Yii::$app->request;
        $content = \Yii::$app->request->rawBody;
        \Yii::warning('回调原始数据: ' . $content); // 增加日志，方便调试

        $res = [];
        $result = [];
        $attach = [];

        // 1. 尝试解析XML (微信官方支付)
        if (Helper::isXml($content)) {
            $res = WechatHelper::xmlToArray($content);
            if (isset($res["attach"]) && !is_numeric($res['attach'])) {
                \Yii::$app->response->format = Response::FORMAT_XML;
                $attach = Json::decode($res["attach"]);
                $result = $res;
            }
        }
        // --- 统一处理逻辑 ---
        if ($attach && is_array($attach) && isset($attach['class']) && isset($attach['mall_id'])) {
            $mallId = (int)$attach['mall_id'];
            \Yii::warning("识别到支付方式: {$attach['class']}, mall_id={$mallId}");
            // 动态设置正确的商城上下文
            \Yii::$app->mall = Mall::findOne($mallId);
            if (!\Yii::$app->mall) {
                \Yii::error("[PayController] mall_id={$mallId} 不存在");
                return;
            }
            // 类名来自报文,反射前必须过白名单(见 self::ALLOWED_PAYMENTS)。
            // 回 FAIL 而不是 SUCCESS:真网关不会点名名单外的类,能走到这里的只有伪造或配置错,
            // 留一条 error 便于发现;伪造方拿到 FAIL 也得不到任何东西。
            $className = (string) $attach['class'];
            if (!in_array($className, self::ALLOWED_PAYMENTS, true)) {
                \Yii::error("[PayController] attach.class 不在白名单内,已拒绝: '{$className}' mall_id={$mallId} "
                    . 'pay_order_id=' . ($result['pay_order_id'] ?? '-'), 'payment');
                \Yii::$app->response->format = Response::FORMAT_RAW;
                \Yii::$app->response->headers->add('Content-Type', 'text/xml');
                \Yii::$app->response->content = '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[支付类不可用]]></return_msg></xml>';
                \Yii::$app->response->send();
                return;
            }
            /** @var BasePayment $payment */
            try {
                $payment = \Yii::createObject($className, [$mallId]);
                return $payment->handleCallback($result);
            } catch (InvalidConfigException $e) {
                \Yii::warning($e->getMessage());
            }
            return;
        }

        // 如果所有识别都失败，返回一个通用的成功响应，防止对方重试
        \Yii::warning('未识别到任何有效的支付回调');
        \Yii::$app->response->headers->add('Content-Type', 'text/xml');
        \Yii::$app->response->content = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        \Yii::$app->response->send();
        return;
    }
}