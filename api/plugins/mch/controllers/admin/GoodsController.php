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
use app\forms\goods\ServiceTagForm;
use app\plugins\mch\forms\admin\goods\GoodsEditForm;
use app\plugins\mch\forms\admin\goods\GoodsListForm;
use app\plugins\mch\forms\admin\goods\MchGoodsSettingForm;

class GoodsController extends ApiController
{
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'login' => ['class' => LoginFilter::class],
            'mch' => ['class' => MchFilter::class],
        ]);
    }

    /**
     * 服务标签(详情页价格下面那排「✓ 全国包邮」):店长自建的那批,平台铺的标签他也能选但改不了。
     *
     * ⛔ 归属一律取登录的商户(Yii::$app->mch->id),不认前端传的 mch_id。
     */
    private function serviceTagForm(array $params = []): ServiceTagForm
    {
        unset($params['mch_id'], $params['goods_mch_id']);
        $form = (new ServiceTagForm())->asOwner((int)\Yii::$app->mch->id);
        $form->attributes = $params;
        return $form;
    }

    public function actionServiceTagList()
    {
        return $this->asJson($this->serviceTagForm()->getList());
    }

    /** 商品编辑器的可选标签:平台的 + 自己自建的 */
    public function actionServiceTagSelectable()
    {
        return $this->asJson($this->serviceTagForm()->getSelectable());
    }

    public function actionServiceTagEdit()
    {
        return $this->asJson($this->serviceTagForm($this->request->post())->save());
    }

    public function actionServiceTagDelete()
    {
        return $this->asJson($this->serviceTagForm($this->request->post())->delete());
    }

    /**
     * 店长获取自己的商品列表
     */
    public function actionIndex()
    {
        $form = new GoodsListForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getList());
    }

    /**
     * 店长获取单个商品详情 (用于编辑) 或 保存商品 (创建/编辑)
     */
    public function actionEdit()
    {
        $form = new GoodsEditForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $this->asJson($form->save());
        } else {
            $form->attributes = $this->request->get();
            return $this->asJson($form->getDetail());
        }
    }

    /**
     * 店长删除自己的商品
     */
    public function actionDelete()
    {
        $form = new GoodsEditForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->delete());
    }

    /**
     * 店长修改自己商品的上下架状态
     */
    public function actionUpdateStatus()
    {
        $form = new GoodsEditForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->updateStatus());
    }

    /**
     * 店长批量上/下架自己的商品
     */
    /** 我的商品评价(只读) —— 商家不能回复、不能删除,详见 GoodsCommentListForm 类注释 */
    public function actionCommentList()
    {
        $form = new \app\plugins\mch\forms\admin\goods\GoodsCommentListForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getList());
    }

    public function actionBatchStatusChange()
    {
        $ids = (array)$this->request->post('ids', []);
        $status = $this->request->post('status', 0);
        $form = new GoodsEditForm();
        return $this->asJson($form->batchUpdateStatus($ids, $status));
    }

    public function actionGoodsProperty()
    {
        $form = new \app\forms\mall\goods\GoodsPropertyForm();
        return $form->getSetting();
    }

    public function actionSetting()
    {
        $form = new MchGoodsSettingForm();
        $form->attributes = $this->request->get();
        return $form->getSettings();

    }
}