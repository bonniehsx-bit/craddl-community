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
use app\plugins\mch\forms\api\index\CommentListForm;

/**
 * Class CommentController
 * @package app\plugins\mch\controllers\api
 * @desc 用户端获取商家评价相关接口
 */
class CommentController extends ApiController
{
    /**
     * 获取指定商家的评价列表
     * API: GET /plugin/mch/api/comment/list
     * @return \yii\web\Response
     */
    public function actionList()
    {
        $form = new CommentListForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getList());
    }
}