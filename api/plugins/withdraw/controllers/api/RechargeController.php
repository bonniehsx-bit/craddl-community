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

namespace app\plugins\withdraw\controllers\api;

use app\controllers\api\filters\LoginFilter;
use app\plugins\ApiController;
use app\plugins\withdraw\forms\api\recharge\PlanListForm;
use app\plugins\withdraw\forms\api\recharge\RecordListForm;
use Yii;

/**
 * C 端 - 账户充值
 *
 * 路由：plugin/withdraw/api/recharge/{action}
 *   - plans    可购买的充值档位 + 下单占位商品 id
 *   - records  我的充值记录
 *
 * ⚠️ 这里**没有**下单接口：下单一律走核心 /api/order/submit，
 *   plugin=withdraw & type=withdraw_recharge，由 WithdrawRechargeOrderHandler 接管。
 *   插件自建下单入口就绕开了核心的支付/结算/分润全链路。
 */
class RechargeController extends ApiController
{
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'login' => ['class' => LoginFilter::class],
        ]);
    }

    public function actionPlans()
    {
        $form = new PlanListForm();
        $form->attributes = Yii::$app->request->get();
        return $this->asJson($form->search());
    }

    public function actionRecords()
    {
        $form = new RecordListForm();
        $form->attributes = Yii::$app->request->get();
        return $this->asJson($form->search());
    }
}
