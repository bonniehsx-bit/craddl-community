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

use app\plugins\ApiController;
use app\plugins\mch\filters\MchFilter;
use app\plugins\mch\forms\admin\clerk\ClerkEditForm;
use app\plugins\mch\forms\admin\clerk\ClerkListForm;
use app\plugins\mch\forms\admin\clerk\ClerkRecordForm;
use app\plugins\mch\forms\admin\clerk\MyClerkRecordForm;
use app\plugins\mch\forms\admin\clerk\UserSearchForm;

/**
 * Class ClerkController
 * @package app\plugins\mch\controllers\admin
 * @desc 店长后台的店员管理
 */
class ClerkController extends ApiController
{
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'mch' => ['class' => MchFilter::class], // MchFilter会确保只有店长能访问此控制器
        ]);
    }

    /**
     * 获取店员列表
     * API: GET /plugin/mch/admin/clerk/index
     * @return \yii\web\Response
     */
    public function actionIndex()
    {
        $form = new ClerkListForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getList());
    }

    /**
     * 添加店员
     * API: POST /plugin/mch/admin/clerk/add
     * @return \yii\web\Response
     */
    public function actionAdd()
    {
        $form = new ClerkEditForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->add());
    }

    /**
     * 移除店员
     * API: POST /plugin/mch/admin/clerk/delete
     * @return \yii\web\Response
     */
    public function actionDelete()
    {
        $form = new ClerkEditForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->delete());
    }

    /**
     * 搜索用户 (用于添加店员)
     * API: GET /plugin/mch/admin/clerk/search-user
     * @return \yii\web\Response
     */
    public function actionSearchUser()
    {
        $form = new UserSearchForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->search());
    }

    /**
     * [新增] 获取核销记录列表
     * API: GET /plugin/mch/admin/clerk/records
     * @return \yii\web\Response
     */
    public function actionRecords()
    {
        $form = new ClerkRecordForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getList());
    }

    /**
     * [新增] 获取店铺下所有可用的核销员 (用于筛选)
     * API: GET /plugin/mch/admin/clerk/all-clerks
     * @return \yii\web\Response
     */
    public function actionAllClerks()
    {
        $form = new ClerkRecordForm();
        return $this->asJson($form->getAllClerks());
    }

    public function actionMyRecords()
    {
        $form = new MyClerkRecordForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getList());
    }
}