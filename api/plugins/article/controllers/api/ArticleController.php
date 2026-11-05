<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-12-31
 * Time: 17:48
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

namespace app\plugins\article\controllers\api;


use app\plugins\ApiController;
use app\plugins\article\forms\api\ArticleCatListForm;
use app\plugins\article\forms\api\ArticleForm;
use app\plugins\article\forms\api\ArticleListForm;

class ArticleController extends ApiController
{

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-12-31
     * @Time: 17:50
     * @Note:分类列表
     * @return \yii\web\Response
     */
    public function actionCatList()
    {
        $form = new ArticleCatListForm();
        return $form->getList();
    }


    public function actionList()
    {
        $form = new ArticleListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }


    public function actionArticle()
    {
        $form = new ArticleForm();
        $form->attributes = $this->request->get();
        return $form->search();
    }
}
