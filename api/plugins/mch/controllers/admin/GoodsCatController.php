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

namespace app\plugins\mch\controllers\admin;
// [核心] 命名空间已更正

use app\controllers\api\filters\LoginFilter;
use app\plugins\ApiController;
use app\plugins\mch\filters\MchFilter;
use app\plugins\mch\forms\admin\goods\MchGoodsCatEditForm;
use app\plugins\mch\forms\admin\goods\MchGoodsCatListForm;

// [注意] 店长后台的基类控制器可能不是 ApiController，您可能需要一个 AdminController
// 我暂时使用 ApiController 作为占位符
class GoodsCatController extends ApiController
{
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'login' => ['class' => LoginFilter::class],
            // ======================= [核心新增] 开始 =======================
            'mch' => [
                'class' => MchFilter::class,
                // [可选] 如果某些 action 不需要商家身份，可以在这里排除
                // 'except' => ['action-public'],
            ],
        ]);
    }

    /**
     * 获取店长自己的商品分类列表
     */
    public function actionIndex()
    {
        $form = new MchGoodsCatListForm();
        return $this->asJson($form->getList());
    }

    /**
     * 新增或编辑商品分类
     */
    public function actionEdit()
    {
        $form = new MchGoodsCatEditForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->save());
    }

    /**
     * 删除商品分类
     */
    public function actionDelete()
    {
        $form = new MchGoodsCatEditForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->delete());
    }

    /**
     * 商家端分类树形列表 (兼容CatPicker要求的数据结构)
     * 支持获取私有分类 (mch_id != 0) 和 系统分类 (mch_id = 0)
     */
    public function actionCatList()
    {
        $form = new \app\plugins\mch\forms\admin\goods\CatListForm();
        $form->attributes = \Yii::$app->request->get();
        return $this->asJson($form->getList());
    }
}