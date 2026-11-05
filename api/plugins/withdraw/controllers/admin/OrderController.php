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

namespace app\plugins\withdraw\controllers\admin;

use app\plugins\ApiController;
use app\plugins\withdraw\forms\admin\order\OrderActionForm;
use app\plugins\withdraw\forms\admin\order\OrderExportForm;
use app\plugins\withdraw\forms\admin\order\OrderListForm;
use Yii;

/**
 * 平台后台 - 提现单管理
 *
 * 路由：plugin/withdraw/admin/order/{action}
 */
class OrderController extends ApiController
{
    public function actionIndex()
    {
        $form = new OrderListForm();
        $form->attributes = Yii::$app->request->get();
        return $this->asJson($form->getList());
    }

    public function actionDetail()
    {
        $form = new OrderListForm();
        return $this->asJson($form->getDetail((int)Yii::$app->request->get('id')));
    }

    /** 审核通过 */
    public function actionAuditPass()
    {
        $form = new OrderActionForm();
        $form->attributes = Yii::$app->request->post();
        return $this->asJson($form->audit(true));
    }

    /** 审核驳回 */
    public function actionAuditReject()
    {
        $form = new OrderActionForm();
        $form->attributes = Yii::$app->request->post();
        return $this->asJson($form->audit(false));
    }

    /** 发起打款 */
    public function actionPayout()
    {
        $form = new OrderActionForm();
        $form->attributes = Yii::$app->request->post();
        return $this->asJson($form->payout());
    }

    /** 标记到账 */
    public function actionMarkSuccess()
    {
        $form = new OrderActionForm();
        $form->attributes = Yii::$app->request->post();
        return $this->asJson($form->markSuccess());
    }

    /** 标记失败（退回） */
    public function actionMarkFailed()
    {
        $form = new OrderActionForm();
        $form->attributes = Yii::$app->request->post();
        return $this->asJson($form->markFailed());
    }

    /** 打款失败后重试（退回待打款，资金不动） */
    public function actionRetry()
    {
        $form = new OrderActionForm();
        $form->attributes = Yii::$app->request->post();
        return $this->asJson($form->retry());
    }

    /** 上传/更新打款凭证 */
    public function actionUploadReceipt()
    {
        $form = new OrderActionForm();
        $form->attributes = Yii::$app->request->post();
        return $this->asJson($form->uploadReceipt());
    }

    /** 发起异步导出，返回 token */
    public function actionAsyncExport()
    {
        $form = new OrderExportForm();
        $form->attributes = Yii::$app->request->get();
        return $this->asJson($form->asyncExport());
    }

    /** 轮询导出状态 */
    public function actionExportStatus()
    {
        $form = new OrderExportForm();
        return $this->asJson($form->checkStatus(Yii::$app->request->get('token')));
    }
}
