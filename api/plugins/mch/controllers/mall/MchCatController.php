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
use app\plugins\mch\forms\mall\MchCatForm;
use app\plugins\mch\forms\mall\MchCatListForm;

class MchCatController extends Controller
{
    /**
     * 商户分类列表
     */
    public function actionIndex()
    {
        $form = new MchCatListForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getList());
    }

    /**
     * 编辑/新增商户分类
     */
    public function actionEdit()
    {
        $form = new MchCatForm();
        if (\Yii::$app->request->isPost) {
            $form->attributes = \Yii::$app->request->post('form');
            return $this->asJson($form->save());
        } else {
            $form->attributes = \Yii::$app->request->get();
            return $this->asJson($form->search());
        }
    }

    /**
     * 删除商户分类
     */
    public function actionDelete()
    {
        $form = new MchCatForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->delete());
    }
}