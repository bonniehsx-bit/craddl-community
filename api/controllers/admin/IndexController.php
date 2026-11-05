<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-09-09
 * Time: 17:23
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

namespace app\controllers\admin;


use app\forms\admin\AdminDeleteForm;
use app\forms\admin\AdminEditForm;
use app\forms\admin\AdminListForm;
use app\forms\admin\IndexForm;
use app\forms\admin\LogoutForm;
use app\forms\admin\MallDeleteForm;
use app\forms\admin\MallEditForm;
use app\forms\admin\PasswordChangeForm;
use app\forms\admin\SystemEditForm;
use yii\web\Cookie;


class IndexController extends Controller
{

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-09-10
     * @Time: 21:30
     * @Note:商城首页
     * @return \yii\web\Response
     */
    public function actionIndex()
    {
        $form = new IndexForm();
        return $form->search();
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-09-29
     * @Time: 13:36
     * @Note:退出登录
     * @return \yii\web\Response
     */
    public function actionLogout()
    {
        $form = new LogoutForm();
        return $this->asJson($form->logout());
    }


    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-09-10
     * @Time: 21:30
     * @Note:管理员新增或编辑
     * @return \yii\web\Response
     */
    public function actionAdminEdit()
    {
        $form = new AdminEditForm();
        $form->attributes = $this->request->post();
        return $form->save();
    }


    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-09-29
     * @Time: 13:36
     * @Note: 修改密码
     */
    public function actionPasswordChange()
    {
        $form = new PasswordChangeForm();
        $form->attributes = $this->request->post();
        return $form->save();
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-09-12
     * @Time: 17:38
     * @Note:管理账户列表
     * @return \yii\web\Response
     */
    public function actionAdminList()
    {
        $form = new AdminListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }


    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-09-10
     * @Time: 21:45
     * @Note:系统设置
     * @return \yii\web\Response
     */
    public function actionSystemEdit()
    {
        $form = new SystemEditForm();
        $form->attributes = $this->request->post();
        return $form->save();
    }


    public function actionDelete()
    {
        $form = new AdminDeleteForm();
        $form->attributes = $this->request->post();
        return $form->save();
    }
}
