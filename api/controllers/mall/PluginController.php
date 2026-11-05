<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-29
 * Time: 0:45
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

namespace app\controllers\mall;


use app\forms\mall\plugin\PluginListForm;

class PluginController extends Controller
{

    /**
     * Created by: ganxh
     * Date: 2021/10/9
     * Time: 16:46
     * Note:应用页面
     * @return \yii\web\Response
     */
    public function actionIndex()
    {
        $form = new PluginListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    /**
     * 加入/取消极速工作台星标
     */
    public function actionToggleShortcut()
    {
        $form = new \app\forms\mall\plugin\PluginShortcutToggleForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->toggle());
    }

    /**
     * 取极速工作台的收藏插件列表
     */
    public function actionShortcuts()
    {
        $form = new \app\forms\mall\plugin\PluginShortcutListForm();
        return $this->asJson($form->getList());
    }
}
