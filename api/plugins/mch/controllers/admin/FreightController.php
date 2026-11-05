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
use app\plugins\mch\forms\admin\freight\MchFreightForm;

// <-- 引入 Form

class FreightController extends ApiController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['mch'] = [
            'class' => MchFilter::class,
        ];
        $behaviors['login'] = ['class' => LoginFilter::class];
        return $behaviors;
    }

    /**
     * 商家运费设置
     * (GET)  获取设置
     * (POST) 保存设置
     */
    public function actionSetting()
    {
        // 注入当前的商家信息
        $form = new MchFreightForm();
        $form->mch = \Yii::$app->mch;
        if (\Yii::$app->request->isGet) {
            return $this->asJson($form->getDetail());
        }
        $form->attributes = \Yii::$app->request->post();
        return $this->asJson($form->save());
    }

    public function actionList()
    {
        $form = new MchFreightForm();
        return $form->getList();
    }
}