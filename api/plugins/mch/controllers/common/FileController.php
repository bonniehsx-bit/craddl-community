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

namespace app\plugins\mch\controllers\common;

use app\plugins\ApiController;
use app\plugins\mch\filters\MchFilter;
use app\plugins\mch\forms\common\file\MchFileDeleteForm;
use app\plugins\mch\forms\common\file\MchFileGroupDeleteForm;
use app\plugins\mch\forms\common\file\MchFileGroupEditForm;
use app\plugins\mch\forms\common\file\MchFileGroupListForm;
use app\plugins\mch\forms\common\file\MchFileListForm;
use app\plugins\mch\forms\common\file\MchFileUploadForm; // 之前写的上传类
use Yii;

class FileController extends ApiController
{
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'mch' => [
                'class' => MchFilter::class,
            ],
        ]);
    }

    /**
     * 文件列表
     */
    public function actionList()
    {
        $form = new MchFileListForm();
        $form->attributes = Yii::$app->request->post();
        if (empty($form->attributes)) {
            $form->attributes = Yii::$app->request->get();
        }
        $form->mch_id = Yii::$app->mch->id;
        return $this->asJson($form->getList());
    }

    /**
     * 上传文件
     */
    public function actionUpload()
    {
        $form = new MchFileUploadForm();
        $form->attributes = Yii::$app->request->post();
        $form->file = \yii\web\UploadedFile::getInstanceByName('file');
        $form->mch_id = Yii::$app->mch->id;
        return $this->asJson($form->saveFile());
    }

    /**
     * 删除文件
     */
    public function actionDelete()
    {
        $form = new MchFileDeleteForm();
        $form->attributes = Yii::$app->request->post();
        $form->mch_id = Yii::$app->mch->id;
        return $this->asJson($form->delete());
    }

    /**
     * 分组列表
     */
    public function actionGroupList()
    {
        $form = new MchFileGroupListForm();
        $form->attributes = Yii::$app->request->post();
        if (empty($form->attributes)) {
            $form->attributes = Yii::$app->request->get();
        }
        $form->mch_id = Yii::$app->mch->id;
        return $this->asJson($form->getList());
    }

    /**
     * 编辑/新增分组
     */
    public function actionGroupEdit()
    {
        $form = new MchFileGroupEditForm();
        $form->attributes = Yii::$app->request->post();
        $form->mch_id = Yii::$app->mch->id;
        return $this->asJson($form->save());
    }

    /**
     * 删除分组
     */
    public function actionGroupDelete()
    {
        $form = new MchFileGroupDeleteForm();
        $form->attributes = Yii::$app->request->post();
        $form->mch_id = Yii::$app->mch->id;
        return $this->asJson($form->delete());
    }
}