<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-12-31
 * Time: 14:41
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

namespace app\plugins\article\controllers\mall;


use app\plugins\article\forms\mall\ArticleCatForm;
use app\plugins\article\forms\mall\ArticleCatListForm;
use app\plugins\article\forms\mall\ArticleForm;
use app\plugins\article\forms\mall\ArticleListForm;
use app\plugins\Controller;

class ArticleController extends Controller
{

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-12-31
     * @Time: 14:42
     * @Note:文章列表
     */
    public function actionList()
    {

        $form = new ArticleListForm();

        $form->attributes = $this->request->get();
        return $form->getList();
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-12-31
     * @Time: 14:42
     * @Note:文章编辑
     */
    public function actionArticle()
    {


        $form = new ArticleForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        $form->attributes = $this->request->get();
        return $form->search();
    }

    public function actionAllCat()
    {
        $form = new ArticleCatListForm();

        return $form->getList();
    }


    public function actionCat()
    {
        $form = new ArticleCatForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        $form->attributes = $this->request->get();

        return $form->search();
    }


    public function actionCatList()
    {

        $form = new ArticleCatListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-12-31
     * @Time: 16:28
     * @Note:删除
     * @return \yii\web\Response
     */
    public function actionCatDelete()
    {
        $form = new ArticleCatForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $this->asJson($form->delete());
        }
    }


    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-12-31
     * @Time: 16:10
     * @Note:文章删除
     * @return \yii\web\Response
     */
    public function actionDelete()
    {


        $form = new ArticleForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $this->asJson($form->delete());
        }
    }
}
