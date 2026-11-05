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
use app\plugins\mch\forms\admin\mch\DeliveryConfigForm;
use app\plugins\mch\forms\admin\mch\MchActionsForm;
use app\plugins\mch\forms\admin\mch\MchEditForm;
use app\plugins\mch\forms\admin\mch\MchFinanceDashboardForm;
use app\plugins\mch\forms\admin\mch\MchInitForm;
use app\plugins\mch\forms\admin\mch\MchPendingSettlementListForm;
use app\plugins\mch\forms\admin\mch\MchPosterForm;
use app\plugins\mch\forms\api\index\MchSettingForm;
use Yii;

class MchController extends ApiController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['mch'] = [
            'class' => MchFilter::class,
            'except' => ['init', 'switch'], // [修改] init 和 switch 接口不需要 mch_id
        ];
        $behaviors['login'] = ['class' => LoginFilter::class];
        return $behaviors;
    }


    public function actionEdit()
    {
        $form = new MchEditForm();
        if ($this->request->isPost) {
            $form->attributes = \Yii::$app->request->post();
            return $form->save();
        }
        return $form->get();
    }

    public function actionGetActions()
    {
        $form = new MchActionsForm();
        return $this->asJson($form->getActions());
    }

    /**
     * [新增] 商家中心初始化接口
     * API: GET /plugin/mch/admin/mch/init
     * @return \yii\web\Response
     */
    public function actionInit()
    {
        $form = new MchInitForm();
        return $this->asJson($form->getData());
    }

    /**
     * 获取/保存同城配送配置
     * API:
     *   GET /plugin/mch/admin/mch/delivery-config
     *   POST /plugin/mch/admin/mch/delivery-config
     * @return \yii\web\Response
     */
    public function actionDeliveryConfig()
    {
        $form = new DeliveryConfigForm();
        if (\Yii::$app->request->isPost) {
            $form->attributes = \Yii::$app->request->post();
            return $this->asJson($form->save());
        } else {
            return $this->asJson($form->get());
        }
    }

    /**
     * [修改] 获取店铺小程序主页码及海报信息
     * @return \yii\web\Response
     */
    public function actionPoster()
    {
        // 控制器只负责调度，所有逻辑都在 Form 中
        $form = new MchPosterForm();
        return $this->asJson($form->get());
    }

    public function actionQrcode()
    {
        $form = new MchPosterForm();
        return $form->qrcode();
    }

    /**
     * [新增] 货款数据中心接口
     */
    public function actionFinanceDashboard()
    {
        $form = new MchFinanceDashboardForm();
        $form->attributes = \Yii::$app->request->get();
        return $this->asJson($form->getData());
    }

    public function actionSetting()
    {
        $form = new MchSettingForm();
        return $form->search();
    }

    public function actionPendingSettlementList()
    {
        $form = new MchPendingSettlementListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }
}