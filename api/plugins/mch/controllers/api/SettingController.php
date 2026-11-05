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
namespace app\plugins\mch\controllers\api;

use app\plugins\ApiController;
use app\plugins\mch\forms\api\setting\SettingForm; // 引入正确的Form

class SettingController extends ApiController
{
    // 注意：这个接口通常不需要登录就可以访问
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'login' => [
                'class' => \app\controllers\api\filters\LoginFilter::class,
                'ignore' => ['index'], // 忽略登录验证
            ],
        ]);
    }

    /**
     * 获取商家入驻页面所需的预设配置
     * API: GET /mch/api/setting/index
     * @return \yii\web\Response
     */
    public function actionIndex()
    {
        $form = new SettingForm();
        return $this->asJson($form->getIndex());
    }
}