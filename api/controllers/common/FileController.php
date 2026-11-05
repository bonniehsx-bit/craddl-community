<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-09-22
 * Time: 22:13
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

namespace app\controllers\common;

use app\controllers\BaseController;
use app\forms\common\file\FileDeleteForm;
use app\forms\common\file\FileGroupDeleteForm;
use app\forms\common\file\FileGroupEditForm;
use app\forms\common\file\FileGroupListForm;
use app\forms\common\file\FileListForm;
use app\forms\common\file\FileMoveForm;
use app\forms\common\file\FileUploadForm;
use app\models\AccessToken;
use app\models\Admin;
use Yii;

/**
 * Class FileController
 * @package app\controllers\common
 *
 * [重构版] 通用文件管理控制器
 * 1. User 和 Mall 的身份初始化已移交 AppBootstrap 统一处理。
 * 2. 此控制器专注于文件业务逻辑分发。
 */
class FileController extends BaseController
{
    public $enableCsrfValidation = false;

    /**
     * 前置操作
     * 清理了原有的 User/Mall 初始化逻辑，避免与 AppBootstrap 冲突或重复查询
     */
    public function beforeAction($action)
    {
        $headers = $this->request->headers;

        // [保留] Admin 登录逻辑
        // 如果你的系统还没有 AdminBootstrap，这段逻辑需要保留，否则后台管理员无法上传图片
        // 如果未来有了 AdminBootstrap，这里也可以删除
        if ($headers && !empty($headers['x-admin-token'])) {
            if (Yii::$app->admin->isGuest) {
                $admin = Admin::find()->alias('u')
                    ->innerJoin(['a' => AccessToken::tableName()], 'a.model_id=u.id')
                    ->where([
                        'a.token' => $headers['x-admin-token'],
                        'a.model' => Admin::class,
                        'u.is_delete' => 0
                    ])
                    ->andWhere(['>', 'a.expire_at', time()]) // 增加过期时间检查
                    ->select('u.*')
                    ->one();

                if ($admin) {
                    Yii::$app->admin->login($admin);
                }
            }
        }

        // [移除] User 和 Mall 的初始化逻辑
        // Reason: AppBootstrap 已经完美处理了 User 登录 (支持 Redis 缓存) 和 Mall ID 解析。
        // 在这里重复写不仅浪费性能，还可能覆盖掉 AppBootstrap 的正确逻辑。

        // 后台账号按请求头里的商城看素材库,同 MallFilter:必须是他能管的商城,否则能翻、能删别的商城的素材
        if ($denied = Admin::mallScopeDenied()) {
            Yii::$app->response->data = $denied;
            return false;
        }

        return parent::beforeAction($action);
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-09-23
     * @Time: 12:15
     * @Note: 文件列表
     * @return \yii\web\Response
     */
    public function actionList()
    {
        $form = new FileListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    /**
     * 删除文件
     */
    public function actionDelete()
    {
        $form = new FileDeleteForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->delete());
    }

    /**
     * 移动文件
     */
    public function actionMove()
    {
        $form = new FileMoveForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->move());
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-09-22
     * @Time: 22:32
     * @Note: 图片上传 (通常用于后台 Admin)
     */
    public function actionUpload()
    {
        $form = new FileUploadForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->saveFile());
    }

    /**
     * 用户端上传
     * 这里的 User 身份直接来自 AppBootstrap -> Yii::$app->user->identity
     */
    public function actionUserUpload()
    {
        $form = new FileUploadForm();
        $form->attributes = $this->request->post();
        // 这里的 saveUserFile 内部应该直接使用 Yii::$app->user->id
        return $this->asJson($form->saveUserFile());
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-09-23
     * @Time: 11:04
     * @Note: 编辑文件组
     */
    public function actionGroupEdit()
    {
        $form = new FileGroupEditForm();
        $form->attributes = $this->request->post();
        return $form->save();
    }

    /**
     * 文件组列表
     */
    public function actionGroupList()
    {
        $form = new FileGroupListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    /**
     * 删除文件组
     */
    public function actionGroupDelete()
    {
        $form = new FileGroupDeleteForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->delete());
    }
}
