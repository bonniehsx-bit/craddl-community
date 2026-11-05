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
use app\plugins\mch\forms\mall\GoodsDeleteForm;
use app\plugins\mch\forms\mall\GoodsEditForm;
use app\plugins\mch\forms\mall\GoodsListForm;
use app\plugins\mch\forms\mall\GoodsReviewForm;
use app\plugins\mch\forms\mall\GoodsStatusForm;

/**
 * Class GoodsController
 * @package app\plugins\mch\controllers\mall
 * @desc 平台后台 -> 商品管理 -> 商户商品列表
 */
class GoodsController extends Controller
{
    /**
     * 获取所有商户的商品列表（带筛选）
     * @return \yii\web\Response
     */
    public function actionIndex()
    {
        $form = new GoodsListForm();
        $form->attributes = \Yii::$app->request->get();
        return $this->asJson($form->getList());
    }

    /**
     * 审核商品
     * @return \yii\web\Response
     */
    public function actionReview()
    {
        $form = new GoodsReviewForm();
        $form->attributes = \Yii::$app->request->post();
        return $this->asJson($form->save());
    }

    /**
     * 平台强制修改商品上下架状态
     * @return \yii\web\Response
     */
    public function actionUpdateStatus()
    {
        $form = new GoodsStatusForm();
        $form->attributes = \Yii::$app->request->post();
        return $this->asJson($form->update());
    }

    /**
     * 平台删除商品
     * @return \yii\web\Response
     */
    public function actionDelete()
    {
        $form = new GoodsDeleteForm();
        $form->attributes = \Yii::$app->request->post();
        return $this->asJson($form->delete());
    }

    /**
     * [新增] 平台获取商品详情 或 保存商品 (新增/编辑)
     * @return -yii-web-Response
     */
    public function actionEdit()
    {
        if (\Yii::$app->request->isGet) {
            // 获取详情
            $form = new GoodsEditForm();
            $form->attributes = \Yii::$app->request->get();
            return $this->asJson($form->getDetail());
        } else {
            // 保存数据
            $form = new GoodsEditForm();
            $form->attributes = \Yii::$app->request->post('form'); // 前端会将表单数据包在 'form' 键下
            return $this->asJson($form->save());
        }
    }
}