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

namespace app\plugins\mch\controllers\admin;

use app\controllers\api\filters\LoginFilter;
use app\plugins\ApiController;
use app\plugins\mch\filters\MchFilter;
use app\plugins\mch\forms\admin\order\MchOrderCompleteForm;
use app\plugins\mch\forms\admin\order\MchOrderExpressShipForm;
use app\plugins\mch\forms\admin\order\MchOrderExpressUpdateForm;
use app\plugins\mch\forms\admin\order\MchOrderShipForm;
use app\plugins\mch\forms\admin\order\OrderCancelForm;
use app\plugins\mch\forms\admin\order\OrderDetailForm;
use app\plugins\mch\forms\admin\order\OrderListForm;
use app\plugins\mch\forms\admin\order\RefundActionForm;
use app\plugins\mch\forms\admin\order\RefundListForm;
use app\plugins\mch\forms\admin\order\RefundPayForm;
use app\plugins\mch\forms\admin\order\UnifiedVerifyForm;

use app\plugins\mch\forms\admin\order\MchOrderSendForm;
use app\plugins\mch\forms\admin\order\MchOrderAddressModifyForm;
use app\plugins\mch\forms\admin\order\MchOrderRemarksForm;
use app\plugins\mch\forms\admin\order\MchOrderExportForm;
use app\plugins\mch\forms\admin\order\RefundExchangeCompleteForm;
// [新增] 引入表单

/**
 * Class OrderController
 * @package app\plugins\mch\controllers\admin
 * @desc 商家后台的订单管理
 */
class OrderController extends ApiController
{
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'login' => ['class' => LoginFilter::class],
            'mch' => ['class' => MchFilter::class], // [安全] MchFilter 确保只有登录的店长才能访问
        ]);
    }

    /**
     * 获取订单列表
     * API: GET /plugin/mch/admin/order/index
     * @return \yii\web\Response
     */
    public function actionIndex()
    {
        $form = new OrderListForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getList());
    }

    // 核销统一入口见 actionVerify()，使用 UnifiedVerifyForm


    /**
     * 获取订单详情
     * API: GET /plugin/mch/admin/order/detail
     * @return \yii\web\Response
     */
    public function actionDetail()
    {
        $form = new OrderDetailForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getDetail());
    }

    /**
     * [新增] 店长取消订单
     * API: POST /plugin/mch/admin/order/cancel
     * @return \yii\web\Response
     */
    public function actionCancel()
    {
        $form = new OrderCancelForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->cancel());
    }


    public function actionSend()
    {
        $form = new MchOrderSendForm();
        $form->attributes = $this->request->post();
        return $form->save();
    }

    public function actionAddressModify()
    {
        $form = new MchOrderAddressModifyForm();
        $form->attributes = $this->request->post();
        return $form->save();
    }

    public function actionRemarks()
    {
        $form = new MchOrderRemarksForm();
        $form->attributes = $this->request->post();
        return $form->save();
    }

    public function actionShip()
    {
        $form = new MchOrderShipForm();
        $form->attributes = $this->request->post();
        return $form->ship();
    }

    public function actionComplete()
    {
        $form = new MchOrderCompleteForm();
        $form->attributes = $this->request->post();
        return $form->complete();
    }

    /**
     * 商家快递发货
     * API: POST /plugin/mch/admin/order/express-ship
     * @return \yii\web\Response
     */
    public function actionExpressShip()
    {
        $form = new MchOrderExpressShipForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->save());
    }

    /**
     * 发货后修改快递公司/单号
     */
    public function actionExpressUpdate()
    {
        $form = new MchOrderExpressUpdateForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->save());
    }

    public function actionRefunds()
    {
        $form = new RefundListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }


    /**
     * 【新增】商家端处理售后审核 (同意/拒绝)
     * API: POST /plugin/mch/admin/order/refund-action
     */
    public function actionRefundAction()
    {
        $form = new RefundActionForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->save());
    }

    /**
     * 【新增】商家端处理售后确认退款
     * API: POST /plugin/mch/admin/order/refund-pay
     */
    public function actionRefundPay()
    {
        $form = new RefundPayForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->save());
    }

    /**
     * 【新增】商家端处理售后确认换货完成
     * API: POST /plugin/mch/admin/order/refund-exchange-complete
     */
    public function actionRefundExchangeComplete()
    {
        $form = new RefundExchangeCompleteForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->save());
    }

    public function actionVerify()
    {
        $form = new UnifiedVerifyForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->verify();
        }
        $form->attributes = $this->request->get();
        return $form->search();
    }

    public function actionAsyncExport()
    {
        $form = new MchOrderExportForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->asyncExport());
    }

    public function actionExportStatus()
    {
        $form = new MchOrderExportForm();
        $token = $this->request->get('token');
        return $this->asJson($form->checkStatus($token));
    }
}
