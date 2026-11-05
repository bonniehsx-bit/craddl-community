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

namespace app\controllers\notify;

use app\models\Mall;
use app\payments\WechatPayment;
use yii\base\InvalidConfigException;

class WechatPayController extends \yii\web\Controller
{
    public $enableCsrfValidation = false;
    public $request;

    public function init()
    {
        // 从 REQUEST_URI 提取伪静态的 mall_id 路径参数
        // 例如: /notify/wechat-pay.php/123 -> 提取出 123
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $mallId = (int) basename(rtrim($path, '/'));

        if ($mallId <= 0) {
            $mallId = (int)($_GET['mall_id'] ?? 0);
        }
        if ($mallId <= 0) {
            \Yii::error('[WechatPayController] 无法提取 mall_id，回退到 1（历史兼容）');
            $mallId = 1;
        }

        \Yii::$app->mall = Mall::findOne($mallId);
        if (!\Yii::$app->mall) {
            \Yii::warning('[WechatPayController] 未找到对应的商城配置 mall_id = ' . $mallId);
        }
    }

    public function actionIndex()
    {
        \Yii::warning('===================公众号订单支付异步专属通知=====================');
        $this->request = \Yii::$app->request;
        $content = \Yii::$app->request->rawBody;
        \Yii::warning('回调原始数据(公众号H5专属网关): ' . $content);

        try {
            if (\Yii::$app->mall) {
                // 实例化公众号支付网关，传入精准的 mall_id
                $payment = \Yii::createObject(WechatPayment::class, [\Yii::$app->mall->id]);
                // 让 EasyWeChat V6 自动执行 V3 AES-256-GCM 密文解密和路由验签
                return $payment->handleCallback([]);
            } else {
                \Yii::warning('[WechatPayController] 无法实例化网关，因为商户上下文丢弃');
            }
        } catch (InvalidConfigException $e) {
            \Yii::warning('类实例化异常: ' . $e->getMessage());
        } catch (\Exception $e) {
            \Yii::getLogger()->log("公众号支付回调处理异常：" . $e->getMessage(), \yii\log\Logger::LEVEL_ERROR);
        }

        // 异常阻断防连环重试
        \Yii::warning('支付回调处理失败，结束流');
        \Yii::$app->response->headers->add('Content-Type', 'text/xml');
        \Yii::$app->response->content = '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[解析异常]]></return_msg></xml>';
        \Yii::$app->response->send();
        return;
    }
}
