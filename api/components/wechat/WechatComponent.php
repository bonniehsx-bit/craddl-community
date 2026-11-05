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

namespace app\components\wechat;

use Yii;
use yii\base\Component;
use EasyWeChat\OfficialAccount\Application as OfficialAccount;
use EasyWeChat\MiniApp\Application as MiniApp;
use EasyWeChat\Pay\Application as Payment;
use yii\web\ParameterException;

/**
 * Class WechatComponent
 * 专门适配旧版 yii2-easy-wechat 调用方式 (Yii::$app->wechat->app / miniProgram) 到 v6
 *
 * @property OfficialAccount $app 微信公众号实例
 * @property MiniApp $miniProgram 微信小程序实例
 * @property Payment $payment 微信支付实例
 */
class WechatComponent extends Component
{
    /**
     * @var array 公众号配置
     */
    public $userConfig = [];

    /**
     * @var array 小程序配置
     */
    public $miniProgramConfig = [];

    /**
     * @var array 微信支付配置
     */
    public $paymentConfig = [];

    /**
     * @var array 开放平台配置（保留配置项结构）
     */
    public $openPlatformConfig = [];

    /**
     * @var array 企业微信配置（保留配置项结构）
     */
    public $workConfig = [];

    private $_app;
    private $_miniProgram;
    private $_payment;
    private $_openPlatform;
    private $_work;

    private $_appConfigHash;
    private $_miniProgramConfigHash;
    private $_paymentConfigHash;

    /**
     * 获取微信公众号实例
     * @return OfficialAccount
     */
    public function getApp()
    {
        $config = Yii::$app->params['wechatConfig'] ?? $this->userConfig;
        $hash = md5(json_encode($config));
        if ($this->_app === null || $this->_appConfigHash !== $hash) {
            $this->_app = new OfficialAccount($config);
            $this->_appConfigHash = $hash;
        }
        return $this->_app;
    }

    /**
     * 获取微信小程序实例
     * @return MiniApp
     */
    public function getMiniProgram()
    {
        $config = Yii::$app->params['wechatMiniProgramConfig'] ?? $this->miniProgramConfig;
        $hash = md5(json_encode($config));
        if ($this->_miniProgram === null || $this->_miniProgramConfigHash !== $hash) {
            $this->_miniProgram = new MiniApp($config);
            $this->_miniProgramConfigHash = $hash;
        }
        return $this->_miniProgram;
    }

    /**
     * 获取微信支付实例
     * @return Payment
     */
    public function getPayment()
    {
        $config = Yii::$app->params['wechatPaymentConfig'] ?? $this->paymentConfig;
        $hash = md5(json_encode($config));
        if ($this->_payment === null || $this->_paymentConfigHash !== $hash) {
            $this->_payment = new Payment($config);
            $this->_paymentConfigHash = $hash;
        }
        return $this->_payment;
    }

    /**
     * 判定是否通过微信浏览器访问
     * @return bool
     */
    public function getIsWechat()
    {
        return strpos(Yii::$app->request->getUserAgent(), 'MicroMessenger') !== false;
    }
}
