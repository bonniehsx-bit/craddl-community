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
use app\plugins\mch\forms\mall\apply\ApplyFieldForm;
use app\plugins\mch\forms\mall\apply\CertDefForm;
use Yii;

/**
 * 商家入驻「字段可配置 + 证照库」后台配置接口(平台侧 mall 命名空间)。
 *   GET  /plugin/mch/mall/apply-config/field-config?type=1     字段配置(按主体类型)
 *   POST /plugin/mch/mall/apply-config/save-field-config       保存字段配置
 *   GET  /plugin/mch/mall/apply-config/cert-list               证照库列表
 *   POST /plugin/mch/mall/apply-config/cert-save               新增/编辑证照
 *   POST /plugin/mch/mall/apply-config/cert-delete             删除证照(预置不可删)
 */
class ApplyConfigController extends Controller
{
    public function actionFieldConfig()
    {
        $form = new ApplyFieldForm();
        return $this->asJson($form->getConfig((int)Yii::$app->request->get('type')));
    }

    public function actionSaveFieldConfig()
    {
        $form = new ApplyFieldForm();
        return $this->asJson($form->save(Yii::$app->request->post()));
    }

    public function actionCertList()
    {
        $form = new CertDefForm();
        return $this->asJson($form->getList());
    }

    public function actionCertSave()
    {
        $form = new CertDefForm();
        return $this->asJson($form->save(Yii::$app->request->post()));
    }

    public function actionCertDelete()
    {
        $form = new CertDefForm();
        return $this->asJson($form->remove((int)Yii::$app->request->post('id')));
    }
}
