<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-29
 * Time: 0:46
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


use app\forms\admin\PluginForm;
use app\forms\admin\PluginListForm;
use app\forms\admin\UpdateForm;

class PluginController extends Controller
{

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-10-29
     * @Time: 0:46
     * @Note:插件列表
     */

    public function actionList()
    {
        $form = new PluginListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    public function actionInstall()
    {
        $form = new PluginForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->install());
    }

    public function actionUninstall()
    {
        $form = new PluginForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->uninstall());
    }


    /**
     * 彻底删除未安装插件的目录文件(危险操作)
     */
    public function actionDelete()
    {
        $form = new PluginForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->delete());
    }


    public function actionLogo()
    {
        $form = new PluginForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
        }
        return $this->asJson($form->setLogo());
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-11-22
     * @Time: 17:10
     * @Note:插件更新
     */
    public function actionUpdate()
    {
        $form = new PluginForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->update());
    }
}
