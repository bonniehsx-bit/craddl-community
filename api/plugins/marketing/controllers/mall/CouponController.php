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
use app\plugins\marketing\forms\mall\CouponForm;
use app\plugins\marketing\forms\mall\CouponSendForm;
use Yii;

/**
 * 优惠券管理(平台后台)—— 由核心 mall/market 迁入
 * 路由:plugin/marketing/mall/coupon/{action}
 * 说明:券模型/CouponService/结算仍在核心,本控制器只承接后台管理界面的增删改查与发放。
 */
class CouponController extends Controller
{
    /** 优惠券列表 */
    public function actionList()
    {
        $form = new CouponForm();
        $form->attributes = Yii::$app->request->get();
        return $this->asJson($form->getList());
    }

    /** 优惠券详情(编辑回显) */
    public function actionDetail()
    {
        $form = new CouponForm();
        $form->id = (int)Yii::$app->request->get('id');
        return $this->asJson($form->search());
    }

    /** 新建/编辑优惠券 */
    public function actionSave()
    {
        $form = new CouponForm();
        $form->attributes = Yii::$app->request->post();
        return $this->asJson($form->save());
    }

    /** 删除优惠券 */
    public function actionDelete()
    {
        $form = new CouponForm();
        $form->id = (int)Yii::$app->request->post('id');
        return $this->asJson($form->delete());
    }

    /** 发放优惠券给指定用户 */
    public function actionSend()
    {
        $form = new CouponSendForm();
        $form->attributes = Yii::$app->request->post();
        return $this->asJson($form->send());
    }
}
