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

namespace app\controllers\mall;

use app\forms\mall\menu\MenuListForm;
use app\forms\mall\menu\MenuOptionSaveForm;
use app\forms\mall\menu\RouterListForm;

class MenuController extends Controller
{
    /**
     * 获取商户专属动态路由
     */
    public function actionRouters()
    {
        $form = new RouterListForm();
        return $this->asJson($form->getRouters());
    }

    /**
     * 获取商户专属菜单列表（装修页面使用）
     */
    public function actionList()
    {
        $form = new MenuListForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getList());
    }

    /**
     * 保存商户个性化配置（沙盒修改）
     */
    public function actionOptionSave()
    {
        $form = new MenuOptionSaveForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->save());
    }

    public function actionOptionReset()
    {
        $form = new \app\forms\mall\menu\MenuOptionResetForm();
        return $this->asJson($form->reset());
    }
}
