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

namespace app\plugins\marketing\controllers\mall;

use app\plugins\Controller;
use app\plugins\marketing\forms\mall\CampaignListForm;
use app\plugins\marketing\forms\mall\CampaignSaveForm;
use Yii;

/**
 * 营销活动管理(平台后台)
 */
class CampaignController extends Controller
{
    /** 活动列表 */
    public function actionIndex()
    {
        $form = new CampaignListForm();
        $form->attributes = Yii::$app->request->get();
        return $this->asJson($form->search());
    }

    /** 活动详情(编辑回显) */
    public function actionDetail()
    {
        $form = new CampaignListForm();
        $form->id = (int)Yii::$app->request->get('id');
        return $this->asJson($form->detail());
    }

    /** 新建/编辑活动 */
    public function actionSave()
    {
        $form = new CampaignSaveForm();
        $form->attributes = Yii::$app->request->post();
        return $this->asJson($form->save());
    }

    /** 变更状态: 进行/暂停/结束 */
    public function actionStatus()
    {
        $form = new CampaignSaveForm();
        $form->id = (int)Yii::$app->request->post('id');
        $form->status = (int)Yii::$app->request->post('status');
        return $this->asJson($form->changeStatus());
    }

    /** 删除活动 */
    public function actionDelete()
    {
        $form = new CampaignSaveForm();
        $form->id = (int)Yii::$app->request->post('id');
        return $this->asJson($form->delete());
    }

    /** 可选平台券模板(供活动选券) */
    public function actionCouponOptions()
    {
        $form = new CampaignListForm();
        $form->keyword = (string)Yii::$app->request->get('keyword');
        return $this->asJson($form->couponOptions());
    }

    /** 可选订单类型(平台订单类型清单,供「计入的订单类型」勾选) */
    public function actionOrderTypes()
    {
        $list = array_map(function ($t) {
            return ['key' => $t['key'], 'name' => $t['name'], 'plugin_label' => $t['plugin_label']];
        }, \app\services\order\OrderTypeRegistry::all());
        return $this->asJson(['code' => 0, 'msg' => '', 'data' => ['list' => $list]]);
    }
}
