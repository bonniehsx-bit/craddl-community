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
use app\plugins\mch\forms\admin\coupon\CouponEditForm;
use app\plugins\mch\forms\admin\coupon\CouponHoldersForm;
use app\plugins\mch\forms\admin\coupon\CouponListForm;
use app\plugins\mch\forms\admin\coupon\CouponSendForm;

// [新增] 引入发券逻辑

/**
 * Class CouponController
 * @package app\plugins\mch\controllers\admin
 * @desc 商家后台的优惠券管理
 */
class CouponController extends ApiController
{
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'login' => ['class' => LoginFilter::class],
            'mch' => ['class' => MchFilter::class], // 确保安全
        ]);
    }

    /**
     * 获取商家自己的优惠券列表
     * API: GET /plugin/mch/admin/coupon/index
     * @return \yii\web\Response
     */
    public function actionIndex()
    {
        $form = new CouponListForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getList());
    }

    /**
     * 获取单张优惠券详情 (用于编辑页加载数据)
     * API: GET /plugin/mch/admin/coupon/detail
     * @return \yii\web\Response
     */
    public function actionDetail()
    {
        $form = new CouponEditForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getDetail());
    }

    /**
     * 保存 (新建 / 编辑) 优惠券
     * API: POST /plugin/mch/admin/coupon/edit
     * @return \yii\web\Response
     */
    public function actionEdit()
    {
        $form = new CouponEditForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->save());
    }

    /**
     * 删除优惠券
     * API: POST /plugin/mch/admin/coupon/delete
     * @return \yii\web\Response
     */
    public function actionDelete()
    {
        $form = new CouponEditForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->delete());
    }

    /**
     * [新增] 搜索用户 (用于手动发券页面)
     * API: GET /plugin/mch/admin/coupon/search-user
     * @return \yii\web\Response
     */
    public function actionSearchUser()
    {
        $form = new CouponSendForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->searchUser());
    }

    /**
     * [新增] 执行手动发券
     * API: POST /plugin/mch/admin/coupon/send
     * @return \yii\web\Response
     */
    public function actionSend()
    {
        $form = new CouponSendForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->send());
    }

    /**
     * [新增] 获取优惠券的持有会员列表
     * API: GET /plugin/mch/admin/coupon/holders
     * @return \yii\web\Response
     */
    public function actionHolders()
    {
        $form = new CouponHoldersForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getList());
    }
}