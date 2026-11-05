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

namespace app\plugins\withdraw\controllers\admin;

use app\plugins\ApiController;
use app\plugins\withdraw\forms\admin\goods\GoodsListForm;
use Yii;

/**
 * 平台后台 - 充值商品列表
 *
 * 路由:plugin/withdraw/admin/goods/index
 *
 * ⚠️ 这里**只有列表**。商品的新建/编辑一律走核心 `/mall/goods/edit`
 *   (前端 GoodsEditDrawer 传 goods-type="recharge"),核心保存管线会在末尾触发
 *   GOODS_AFTER_SAVE → RechargeGoodsListener 落到账钱包与档位。
 *   插件自建一套编辑接口就绕开了核心的校验 / 图片 / 分类 / 事件派发全链路,
 *   最直接的后果是**分润插件再也挂不上来** —— 那正是商品化要解决的问题。
 */
class GoodsController extends ApiController
{
    public function actionIndex()
    {
        $form = new GoodsListForm();
        $form->attributes = Yii::$app->request->get();
        return $this->asJson($form->getList());
    }
}
