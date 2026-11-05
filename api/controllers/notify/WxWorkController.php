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

use app\common\wechat\WXBizMsgCrypt;
use app\common\helpers\WxWorkHelper;
use app\helpers\OptionHelper;
use app\models\Mall;
use app\payments\WxWorkPayment;
use Yii;
use yii\web\Controller;
use Exception;

class WxWorkController extends Controller
{
    // 关闭 CSRF 验证，因为这是给微信回调用的
    public $enableCsrfValidation = false;

    public function init()
    {
        parent::init();
    }

    public function actionIndex()
    {
        $request = Yii::$app->request;

        // 1. 获取 URL 中的 mall_id (必传参数)
        $mallId = $request->get('mall_id', 1);
        $mall = Mall::findOne($mallId);
        if ($mall) {
            Yii::$app->mall = $mall;
        }

        // 2. 获取微信回调的签名参数
        $signature = $request->get('msg_signature');
        $timestamp = $request->get('timestamp');
        $nonce = $request->get('nonce');
        $echostr = $request->get('echostr');

        try {
            // 3. 根据 mall_id 动态获取配置
            $config = $this->getConfigByMallId($mallId);
            $wxcpt = new \app\common\wechat\WXBizMsgCrypt($config['token'], $config['encodingAesKey'], $config['corpId']);
            // --- 场景一：URL 验证 (GET 请求) ---
            if ($echostr) {
                $result = $wxcpt->verifyUrl($signature, $timestamp, $nonce, $echostr);
                if ($result !== false) {
                    // 注意：必须原样返回解密后的字符串内容
                    echo $result;
                    Yii::$app->end();
                }
                return "failed";
            }
            // --- 场景二：消息推送 (POST 请求) ---
            $postData = $request->getRawBody();
            if ($postData) {
                $sMsg = ""; // 解密后的明文
                $errCode = $wxcpt->DecryptMsg($signature, $timestamp, $nonce, $postData, $sMsg);

                if ($errCode == 0) {
                    // $sMsg 是明文 XML
                    Yii::warning("收到企业微信回调(MallId:{$mallId}): " . $sMsg, 'wxwork');

                    // 5. 调用 Helper 处理业务逻辑 (发送支付链接等)
                    // 注意：config 数组中已包含 corpId, corpSecret 等信息
                    WxWorkHelper::processBusinessLogic($sMsg, $config);

                    echo "success";
                    Yii::$app->end();
                } else {
                    Yii::error("企业微信解密失败(MallId:{$mallId})，错误码: " . $errCode, 'wxwork');
                    return "fail";
                }
            }
        } catch (Exception $e) {
            Yii::error("企业微信回调处理异常(MallId:{$mallId}): " . $e->getMessage(), 'wxwork');
            return "fail";
        }

        return "success";
    }

    /**
     * 根据 mall_id 从数据库获取配置
     * @param int $mallId
     * @return array
     * @throws Exception
     */
    private function getConfigByMallId($mallId): array
    {
        if (empty($mallId)) {
            throw new Exception("缺少参数 mall_id");
        }

        // 从 OptionHelper 获取所有支付配置
        $payments = OptionHelper::get('payments', $mallId, 'mall');

        // 获取 WxWorkPayment 对应的配置段
        // 注意：这里使用的键名必须与 WxWorkPaymentForm 保存时一致
        $config = $payments[WxWorkPayment::class] ?? null;

        Yii::warning('[WxWorkController] 加载企业微信配置 mall_id=' . $mallId);
        if (empty($config)) {
            throw new Exception("未找到该商城的企业微信客服支付配置");
        }

        if (empty($config['status'])) {
            throw new Exception("该商城的企业微信客服支付未开启");
        }

        // 检查关键参数
        $required = ['corpId', 'corpSecret', 'token', 'encodingAesKey'];
        foreach ($required as $key) {
            if (empty($config[$key])) {
                throw new Exception("企业微信配置不完整，缺少: {$key}");
            }
        }

        return $config;
    }
}