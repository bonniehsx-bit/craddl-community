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
namespace app\controllers\api;

use app\controllers\api\filters\LoginFilter;
use app\forms\api\cart\GoodsShareForm;
use yii\filters\VerbFilter;

class GoodsShareController extends Controller
{
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'login' => ['class' => LoginFilter::class, 'ignore' => ['detail']],
            'verbs' => ['class' => VerbFilter::class, 'actions' => [
                'create' => ['POST'], 'import' => ['POST'], 'view' => ['POST'],
                'detail' => ['GET'], 'mine' => ['GET'],
            ]],
        ]);
    }

    public function actionCreate() { return $this->runForm('create'); }
    public function actionDetail() { return $this->runForm('detail', true); }
    public function actionImport() { return $this->runForm('import'); }
    public function actionView() { return $this->runForm('view'); }
    public function actionMine() { return $this->runForm('mine', true); }

    private function runForm(string $operation, bool $get = false)
    {
        $form = new GoodsShareForm();
        $form->attributes = $get ? $this->request->get() : $this->request->post();
        return $form->run($operation);
    }
}
