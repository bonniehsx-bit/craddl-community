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

namespace app\forms\mall\payment;

use app\core\ApiCode;
use app\helpers\OptionHelper;
use app\helpers\ResponseHelper;
use app\helpers\WechatPayHelper;
use app\payments\WechatAppPayment;
use yii\helpers\FileHelper;

/**
 * App 微信原生支付(WechatAppPayment)后台配置。
 * 与 WechatPaymentForm 同构;证书按 mch_id 存,若与公众号支付同一商户则复用同一份证书。
 */
class WechatAppPaymentForm extends PaymentModel
{
    public $setting;

    public function rules()
    {
        return [
            [['setting'], 'safe'],
        ];
    }

    public function save()
    {
        $payment = $this->payment;
        if (!empty($this->setting['cert_pem']) && !empty($this->setting['key_pem'])) {
            if (!is_writeable(\Yii::$app->runtimePath)) {
                chmod(\Yii::$app->runtimePath, 0755);
            }
            $pemDir = \Yii::$app->runtimePath . '/pem';
            if (!is_dir($pemDir)) {
                FileHelper::createDirectory($pemDir, 0755);
            }
            $certPemFile = WechatPayHelper::getApiclientCertPemPath($this->setting['mch_id']);
            file_put_contents($certPemFile, $this->setting['cert_pem']);
            $keyPemFile = WechatPayHelper::getApiclientKeyPemPath($this->setting['mch_id']);
            file_put_contents($keyPemFile, $this->setting['key_pem']);
            if (!empty($this->setting['pub_key'])) {
                $pubKeyFile = WechatPayHelper::getWechatPlatformPubKeyPath($this->setting['mch_id']);
                file_put_contents($pubKeyFile, $this->setting['pub_key']);
            }
        }
        $this->setting['payment_type'] = WechatAppPayment::class;
        $payment[WechatAppPayment::class] = $this->setting;
        OptionHelper::set('payments', $payment, \Yii::$app->mall->id);

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '保存成功');
    }

    public function search()
    {
        $this->setting = $this->getPayment(WechatAppPayment::class);
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', $this->setting);
    }
}
