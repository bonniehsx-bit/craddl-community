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
use app\plugins\withdraw\forms\admin\channel\ChannelDeleteForm;
use app\plugins\withdraw\forms\admin\channel\ChannelListForm;
use app\plugins\withdraw\forms\admin\channel\ChannelSaveForm;
use app\plugins\withdraw\services\FundRegistry;
use Yii;

/**
 * 平台后台 - 提现渠道管理
 *
 * 路由：plugin/withdraw/admin/channel/{action}
 */
class ChannelController extends ApiController
{
    /** 渠道列表 */
    public function actionIndex()
    {
        $form = new ChannelListForm();
        $form->attributes = Yii::$app->request->get();
        return $this->asJson($form->getList());
    }

    /** 渠道详情 */
    public function actionDetail()
    {
        $form = new ChannelListForm();
        $form->attributes = Yii::$app->request->get();
        return $this->asJson($form->getDetail());
    }

    /**
     * 可选网关列表 + 指定网关的进件资料字段
     * GET ?fund_class=app\funds\BankFund → 额外返回该网关的 onboarding_fields
     */
    public function actionFunds()
    {
        $mallId = (int)Yii::$app->mall->id;
        $fundClass = (string)Yii::$app->request->get('fund_class', '');

        $data = [
            'funds' => FundRegistry::list($mallId),
            // 可选资产 = 核心 4 + 本商城授权插件的可提现钱包(供渠道「资金来源」复选框动态渲染)
            'asset_options' => \app\plugins\withdraw\services\AssetService::assetOptions($mallId),
            'onboarding_fields' => $fundClass !== ''
                ? FundRegistry::onboardingFields($mallId, $fundClass)
                : [],
        ];
        return $this->asJson(['code' => 0, 'msg' => '成功', 'data' => $data]);
    }

    /** 新建 / 编辑渠道 */
    public function actionSave()
    {
        $form = new ChannelSaveForm();
        $form->attributes = Yii::$app->request->post();
        return $this->asJson($form->save());
    }

    /** 启用/停用 */
    public function actionSwitch()
    {
        $form = new ChannelSaveForm();
        $form->attributes = Yii::$app->request->post();
        return $this->asJson($form->switchStatus());
    }

    /** 删除渠道（软删） */
    public function actionDelete()
    {
        $form = new ChannelDeleteForm();
        $form->attributes = Yii::$app->request->post();
        return $this->asJson($form->delete());
    }
}
