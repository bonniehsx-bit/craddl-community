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
use app\plugins\mch\forms\admin\split\ApplyForm;
use app\plugins\mch\forms\admin\split\StatusForm;

/**
 * Class SplitController
 * @package app\plugins\mch\controllers\admin
 * 商家端 - 分账管理
 */
class SplitController extends ApiController
{

    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'mch' => ['class' => MchFilter::class],
        ]);
    }

    /**
     * 获取当前商家的分账进件状态
     */
    public function actionStatus()
    {
        $form = new StatusForm();
        return $this->asJson($form->get());
    }

    /**
     * 商家主动发起分账进件申请
     */
    public function actionApply()
    {
        $form = new ApplyForm();
        return $this->asJson($form->apply());
    }
}

