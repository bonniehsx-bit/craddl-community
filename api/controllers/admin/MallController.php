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

namespace app\controllers\admin;

use app\forms\admin\AdminDeleteForm;
use app\forms\admin\MallDeleteForm;
use app\forms\admin\MallEditForm;
use app\forms\admin\MallInfoForm;
use app\forms\admin\MallListForm;
use app\forms\admin\MallEntryListForm; // [新增]
use app\forms\admin\MallEntryForm;     // [新增]
use app\forms\admin\MallTransferForm;
use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\models\Admin;

class MallController extends Controller
{
    /**
     * 移交/转让商城归属（仅超管）
     */
    public function actionTransfer()
    {
        $form = new MallTransferForm();
        $form->attributes = $this->request->post();
        return $form->save();
    }

    /**
     * 候选商户主账号列表（移交时"选择已有"用）
     */
    public function actionFounders()
    {
        // 仅超管可枚举商户主账号（防跨租户 PII 泄漏）
        $admin = \Yii::$app->admin->identity;
        if (!$admin || $admin->admin_type != Admin::ADMIN_TYPE_SUPER) {
            return $this->asJson(ResponseHelper::send(ApiCode::CODE_FAIL, '无权限'));
        }
        return $this->asJson(ResponseHelper::send(
            ApiCode::CODE_SUCCESS, '', ['list' => MallTransferForm::founderList()]
        ));
    }

    /**
     * 商城管理列表 (系统管理员用的，带分页和搜索)
     */
    public function actionList()
    {
        $form = new MallListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    /**
     * [新增接口] 登录后的入口列表 (选择商城)
     * 获取当前用户可操作的商城列表
     */
    public function actionEntryList()
    {
        $form = new MallEntryListForm();
        // 这里不需要接收参数，直接根据 Token 获取列表
        return $this->asJson($form->getList());
    }

    /**
     * [修改接口] 进入商城
     * 校验权限，前端点击"进入"时调用
     */
    public function actionEnter()
    {
        // 以前是 request->get，建议改为 post 更安全，兼容 get 也可以
        $form = new MallEntryForm();
        $form->attributes = $this->request->post() ?: $this->request->get();
        return $this->asJson($form->enter());
    }

    /**
     * 商城新增或编辑
     */
    public function actionEdit()
    {
        $form = new MallEditForm();
        $form->attributes = $this->request->post();
        return $form->save();
    }

    /**
     * 删除商城
     */
    public function actionDelete()
    {
        $form = new MallDeleteForm();
        $form->attributes = $this->request->post();
        return $form->save();
    }

    /**
     * 商城详情 (用于编辑回显)
     */
    public function actionInfo()
    {
        $form = new MallInfoForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getInfo());
    }
}