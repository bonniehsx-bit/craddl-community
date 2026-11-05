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

namespace app\plugins\mch\controllers\mall;

use app\plugins\Controller;
use app\plugins\mch\forms\mall\DashboardForm;
use app\plugins\mch\forms\mall\MchDashboardForm;
use app\plugins\mch\forms\mall\MchDeleteForm;
use app\plugins\mch\forms\mall\MchEditForm;
use app\plugins\mch\forms\mall\MchHideStatusForm;
use app\plugins\mch\forms\mall\MchListForm;
use app\plugins\mch\forms\mall\MchSearchForm;
use app\plugins\mch\forms\mall\MchStatusForm;

class MchController extends Controller
{
    /**
     * 商户列表页
     */
    public function actionIndex()
    {
        $form = new MchListForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getList());
    }

    /**
     * 获取商户详情 或 保存商户 (创建/编辑)
     */
    public function actionEdit()
    {
        $form = new MchEditForm();
        if (\Yii::$app->request->isPost) {
            // 假设前端用 form 对象包裹所有数据
            $form->attributes = \Yii::$app->request->post('form');
            return $this->asJson($form->save());
        } else {
            $form->attributes = \Yii::$app->request->get();
            return $this->asJson($form->getDetail());
        }
    }

    /**
     * 审核商户 (通过/驳回)
     */
    public function actionUpdateStatus()
    {
        $form = new MchEditForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->updateStatus());
    }

    /**
     * [新增] 用于后台选择框的商家远程搜索
     */
    public function actionSearch()
    {
        $form = new MchSearchForm();
        $form->attributes = \Yii::$app->request->get();
        return $this->asJson($form->search());
    }

    public function actionDashboard()
    {
        if (\Yii::$app->request->isGet) {
            $form = new DashboardForm();
            // 不接参数的话时间 tab 永远只会返回默认周期(今日),前端切换毫无反应
            $form->period = \Yii::$app->request->get('period', 'today');
            return $this->asJson($form->get());
        }
    }

    public function actionMchDashboard()
    {
        $form = new MchDashboardForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getStats());
    }

    /**
     * [修改] 更新商户状态 (启用/禁用/审核)，现在调用新的 MchStatusForm
     * @return \yii\web\Response
     */
    public function actionStatusChange()
    {
        // 注意：原 MchEditForm 中的 updateStatus 依然可以用于“审核”，
        // 但为了统一，我们这里改用新的、更轻量的 Form
        $form = new MchStatusForm();
        $form->attributes = \Yii::$app->request->post();
        return $this->asJson($form->update());
    }

    /**
     * [修改] 软删除商户，现在调用新的 MchDeleteForm
     * @return \yii\web\Response
     */
    public function actionDestroy()
    {
        $form = new MchDeleteForm();
        $form->attributes = \Yii::$app->request->post();
        return $this->asJson($form->softDelete());
    }

    /**
     * [新增] 更新商户前端显示状态 (显示/隐藏)
     * @return \yii\web\Response
     */
    public function actionUpdateHideStatus()
    {
        $form = new MchHideStatusForm();
        $form->attributes = \Yii::$app->request->post();
        return $this->asJson($form->update());
    }

}