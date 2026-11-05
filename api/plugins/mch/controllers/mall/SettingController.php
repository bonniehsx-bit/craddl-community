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
use app\plugins\mch\forms\mall\SettingForm;
use app\plugins\mch\forms\mall\WorkspaceSettingForm;
use app\plugins\mch\forms\mall\WxTemplateForm;

class SettingController extends Controller
{
    /**
     * 多商户插件的全局配置
     */
    public function actionSetting()
    {
        $form = new SettingForm(); // 你有一个 SettingForm
        if (\Yii::$app->request->isPost) {
            $form->form = $this->request->post();
            return $this->asJson($form->saveSetting());
        } else {
            return $this->asJson($form->getSetting());
        }
    }

    /**
     * 移动端工作台自定义菜单配置
     */
    public function actionWorkspace()
    {
        $form = new WorkspaceSettingForm();
        if (\Yii::$app->request->isPost) {
            $form->attributes = \Yii::$app->request->post();
            return $this->asJson($form->save());
        } else {
            return $this->asJson($form->get());
        }
    }

    /**
     * 微信推送模板配置（店铺入驻审核通知）
     * GET  /plugin/mch/mall/setting/wx-template
     * POST /plugin/mch/mall/setting/wx-template
     */
    public function actionWxTemplate()
    {
        $form = new WxTemplateForm();
        if (\Yii::$app->request->isPost) {
            $form->templates = \Yii::$app->request->post('templates');
            return $this->asJson($form->save());
        }
        return $this->asJson($form->get());
    }
}