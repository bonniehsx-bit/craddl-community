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
 * User: JUST
 * Date: 2022/6/30
 * Time: 15:44
 */

namespace app\controllers\mall;

use app\forms\mall\payment\BalancePaymentForm;
use app\forms\mall\payment\FastPayAliJsPaymentForm;
use app\forms\mall\payment\FastPayMpwxPaymentForm;
use app\forms\mall\payment\FastPayQuickPaymentForm;
use app\forms\mall\payment\FastPayWechatJsPaymentForm;
use app\forms\mall\payment\JoinPayMpwxPaymentForm;
use app\forms\mall\payment\MpwxPaymentForm;
use app\forms\mall\payment\OrderListForm;
use app\forms\mall\payment\PaymentModel;
use app\forms\mall\payment\SystemPaymentForm;
use app\forms\mall\payment\WxWorkPaymentForm;
use app\forms\mall\payment\WechatPaymentForm;
use app\forms\mall\payment\WechatAppPaymentForm;
use app\payments\FastPayWechatJsPayment;

class PaymentController extends Controller
{
    public function actionSystem()
    {
        $form = new SystemPaymentForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        return $form->search();
    }

    public function actionMpwx()
    {
        $form = new MpwxPaymentForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        return $form->search();
    }

    public function actionWechat()
    {
        $form = new WechatPaymentForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        return $form->search();
    }

    public function actionHjmpwx()
    {
        $form = new JoinPayMpwxPaymentForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        return $form->search();
    }

    /** App 微信原生支付配置(WechatAppPayment) */
    public function actionWechatApp()
    {
        $form = new WechatAppPaymentForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        return $form->search();
    }

    public function actionBalance()
    {
        $form = new BalancePaymentForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        return $form->search();
    }


    public function actionOrder()
    {
        $form = new OrderListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    public function actionPayments()
    {
        $form = new PaymentModel();
        return $form->getPaymentList();
    }

    /**
     * 快付(FastPay)小程序支付配置
     * @return \yii\web\Response
     */
    public function actionFastpayMpwx()
    {
        if (\Yii::$app->request->isPost) {
            $form = new FastPayMpwxPaymentForm();
            $form->attributes = \Yii::$app->request->post();
            return $this->asJson($form->save());
        } else {
            $form = new FastPayMpwxPaymentForm();
            return $this->asJson($form->search());
        }
    }

    public function actionFastpayWechatjs()
    {
        if (\Yii::$app->request->isPost) {
            $form = new FastPayWechatJsPaymentForm();
            $form->attributes = \Yii::$app->request->post();
            return $this->asJson($form->save());
        } else {
            $form = new FastPayWechatJsPaymentForm();
            return $this->asJson($form->search());
        }
    }

    public function actionFastpayAlijs()
    {
        if (\Yii::$app->request->isPost) {
            $form = new FastPayAliJsPaymentForm();
            $form->attributes = \Yii::$app->request->post();
            return $this->asJson($form->save());
        } else {
            $form = new FastPayAliJsPaymentForm();
            return $this->asJson($form->search());
        }
    }
    public function actionWxwork()
    {
        $form = new WxWorkPaymentForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        return $form->search();
    }

    /**
     * 快付(FastPay) 银行卡快捷支付配置
     */
    public function actionFastpayQuick()
    {
        $form = new FastPayQuickPaymentForm();
        if (\Yii::$app->request->isPost) {
            $form->attributes = \Yii::$app->request->post();
            return $this->asJson($form->save());
        }
        return $this->asJson($form->search());
    }
}
