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


use app\forms\mall\fund\BalanceFundForm;
use app\forms\mall\fund\FastPayAgentPayFundForm;
use app\forms\mall\fund\FastPayFlexibleLaborFundForm;
use app\forms\mall\fund\ManualFundForm;
use app\forms\mall\fund\UserSettleListForm;
use app\forms\mall\fund\UserSettleStatusForm;
use app\forms\mall\fund\WechatFundForm;
use app\funds\BalanceFund;


class FundController extends Controller
{


    public function actionBalance()
    {
        $form = new BalanceFundForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        return $form->search();
    }

    public function actionManual()
    {
        $form = new ManualFundForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        return $form->search();
    }

    public function actionWechat()
    {
        $form = new WechatFundForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        return $form->search();
    }

    /**
     * [新增] 快付代付配置
     */
    public function actionFastPayAgentPay()
    {
        if (\Yii::$app->request->isPost) {
            $form = new FastPayAgentPayFundForm();
            $form->attributes = \Yii::$app->request->post();
            return $this->asJson($form->save());
        } else {
            $form = new FastPayAgentPayFundForm();
            return $this->asJson($form->search());
        }
    }

    /**
     * 代付进件记录列表
     */
    public function actionUserSettleList()
    {
        $form = new UserSettleListForm();
        $form->attributes = \Yii::$app->request->get();
        return $this->asJson($form->getList());
    }

    /**
     * [核心新增] 手动重置用户进件状态
     */
    public function actionUserSettleStatusUpdate()
    {
        $form = new UserSettleStatusForm();
        $form->attributes = \Yii::$app->request->post();
        return $this->asJson($form->changeStatus());
    }

    /**
     * [新增] 快付灵活用工配置接口
     */
    public function actionFastPayFlexibleLaborFund()
    {
        if (\Yii::$app->request->isPost) {
            $form = new FastPayFlexibleLaborFundForm();
            $form->setting = \Yii::$app->request->post();
            return $this->asJson($form->save());
        } else {
            $form = new FastPayFlexibleLaborFundForm();
            return $this->asJson($form->search());
        }
    }

    // app/controllers/mall/FundController.php

    public function actionAlipay()
    {
        if (\Yii::$app->request->isPost) {
            $form = new \app\forms\mall\fund\AlipayFundForm();
            $form->attributes = \Yii::$app->request->post();
            $form->fund = \app\helpers\OptionHelper::get('funds', \Yii::$app->mall->id);
            return $this->asJson($form->save());
        } else {
            $form = new \app\forms\mall\fund\AlipayFundForm();
            $form->fund = \app\helpers\OptionHelper::get('funds', \Yii::$app->mall->id);
            return $this->asJson($form->search());
        }
    }

    /**
     * [新增] 银行卡打款配置接口
     */
    public function actionBank()
    {
        if (\Yii::$app->request->isPost) {
            $form = new \app\forms\mall\fund\BankFundForm();
            $form->attributes = \Yii::$app->request->post();
            // 确保 fund 属性被赋值，供 save 方法使用
            $form->fund = \app\helpers\OptionHelper::get('funds', \Yii::$app->mall->id);
            return $this->asJson($form->save());
        } else {
            $form = new \app\forms\mall\fund\BankFundForm();
            $form->fund = \app\helpers\OptionHelper::get('funds', \Yii::$app->mall->id);
            return $this->asJson($form->search());
        }
    }
}