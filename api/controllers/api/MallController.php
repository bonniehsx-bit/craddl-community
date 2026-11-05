<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-13
 * Time: 9:10
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


use app\forms\api\mall\AlertAdvForm;
use app\forms\api\mall\CatForm;
use app\forms\api\mall\MallSettingForm;
use app\forms\api\mall\OutsideLinkForm;
use app\forms\api\mall\TabbarForm;
use app\forms\api\mall\TabGoodsForm;
use app\forms\mall\mall\PagePathForm;

class MallController extends Controller
{
    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-10-13
     * @Time: 9:39
     * @Note:
     */
    public function actionTabbar()
    {
        $form = new TabbarForm();
        return $form->search();
    }


    public function actionAbout()
    {
        $form = new MallSettingForm();
        return $this->asJson($form->getAbout());
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-12-18
     * @Time: 10:00
     * @Note:弹窗广告
     */
    public function actionAlertAdv()
    {
        $form = new AlertAdvForm();
        return $form->search();
    }

    /**
     * Created by: ganxh
     * Date: 2021/12/3
     * Time: 13:51
     * Note:获取商城设置
     */
    public function actionSetting()
    {

        $form = new MallSettingForm();
        return $form->getSetting();
    }

    /**
     * Created by PhpStorm.
     * User：ganxi
     * Date：2022/3/23
     * Time：14:24
     * Note：获取版本审核
     */
    public function actionChecking()
    {
        $form = new MallSettingForm();
        return $form->getCheckingVersions();
    }

    /**
     * Created by PhpStorm.
     * User：ganxi
     * Date：2022/5/28
     * Time：11:13
     * Note：外部链接
     */
    public function actionOutsideLink()
    {
        $form = new OutsideLinkForm();
        $form->attributes = $this->request->get();
        return $form->search();
    }


    public function actionUserProtocol()
    {
        $form = new MallSettingForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getUserProtocol());
    }

    public function actionFreightList()
    {
        $form = new \app\forms\api\mall\FreightListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    public function actionTabGoods()
    {
        $form = new TabGoodsForm();
        $form->attributes = $this->request->get();
        return $form->search();
    }
}
