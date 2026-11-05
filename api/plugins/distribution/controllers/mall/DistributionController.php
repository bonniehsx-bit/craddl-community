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
namespace app\plugins\distribution\controllers\mall;

use app\plugins\distribution\forms\mall\CommisionListForm;
use app\plugins\Controller;
use app\plugins\distribution\forms\mall\LogSettleForm;

class DistributionController extends Controller
{
    public function actionIndex()
    {
        $form = new CommisionListForm();
        // 接收并验证前端的所有 GET 参数
        $form->attributes = \Yii::$app->request->get();
        return $this->asJson($form->getList());
    }


    /** 导出当前筛选结果为 CSV（同步下载） */
    public function actionExport()
    {
        $form = new CommisionListForm();
        // 走 admin 的 download() 通道(POST + blob),它才会带上 x-admin-token;
        // 直接 window.open 是没有请求头的,会被 MallFilter 挡成未登录
        $form->attributes = array_merge(\Yii::$app->request->get(), \Yii::$app->request->post());
        $file = $form->exportCsv();

        $response = \Yii::$app->response;
        $response->format = \yii\web\Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set(
            'Content-Disposition',
            'attachment; filename="' . rawurlencode($file['filename']) . '"'
        );
        $response->content = $file['content'];
        return $response;
    }

    /** 手动结算单条分销佣金（结算方式=手动 时使用） */
    public function actionSettle()
    {
        $form = new LogSettleForm();
        $form->attributes = \Yii::$app->request->post();
        return $this->asJson($form->settle());
    }

    /** 手动作废单条待结算佣金 */
    public function actionInvalid()
    {
        $form = new LogSettleForm();
        $form->attributes = \Yii::$app->request->post();
        return $this->asJson($form->invalid());
    }
}