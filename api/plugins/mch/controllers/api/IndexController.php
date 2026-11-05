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
/**
 * Created by PhpStorm.
 * User: dujxmall
 * Date: 2022/7/4
 * Time: 14:56
 */

namespace app\plugins\mch\controllers\api;

use app\plugins\ApiController;

use app\plugins\mch\forms\api\index\AvailableMchListForm;
use app\plugins\mch\forms\api\index\CatListForm;
use app\plugins\mch\forms\api\index\MchForm;
use app\plugins\mch\forms\api\index\MchGoodsListForm;
use app\plugins\mch\forms\api\index\MchInfoForm;
use app\plugins\mch\forms\api\index\NearbyMchListForm;
use app\plugins\mch\forms\api\index\DiySelectShopsForm;


class IndexController extends ApiController
{

    // ======================= [核心新增] 开始 =======================
    /**
     * 获取附近商家列表
     * API: GET /mch/api/index/nearby-list
     * @return \yii\web\Response
     */
    public function actionNearbyList()
    {
        $form = new NearbyMchListForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getList());
    }

    public function actionRecommendMch()
    {
        $form = new MchForm();
        $form->attributes = $this->request->get();

        return $form->getRecommend();
    }

    /**
     * DIY「精选商家」组件数据：按后台勾选的 mch_ids（逗号分隔）返回这些商户的展示卡片
     * API: GET /plugin/mch/api/index/diy-select-shops?mch_ids=1,2,3
     */
    public function actionDiySelectShops()
    {
        $form = new DiySelectShopsForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getList());
    }

    // ======================= [核心新增] 结束 =======================

    public function actionInfo()
    {
        $form = new MchInfoForm();
        $form->attributes = $this->request->get();
        return $form->search();
    }

    public function actionQrcode()
    {
        $form = new MchInfoForm();
        $form->attributes = $this->request->get();
        return $form->getQrcode();
    }

    /**
     * 某个商家的商品列表
     */
    public function actionMchGoodsList()
    {
        $form = new MchGoodsListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    public function actionCatList()
    {

        $form = new CatListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    /**
     * 获取在售平台商品的商家列表
     */
    public function actionAvailableMch()
    {
        $form = new AvailableMchListForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->search());
    }

}