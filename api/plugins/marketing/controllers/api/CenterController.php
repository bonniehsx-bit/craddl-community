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

namespace app\plugins\marketing\controllers\api;

use app\controllers\api\filters\LoginFilter;
use app\core\ApiCode;
use app\helpers\UserHelper;
use app\plugins\ApiController;
use app\plugins\marketing\forms\api\MyCouponForm;
use app\plugins\marketing\services\ClaimService;
use app\plugins\marketing\services\CumulativeService;
use app\plugins\marketing\services\OrderRewardService;
use Yii;

/**
 * C 端 - 营销领券中心 + 我的优惠券
 *
 * 路由:plugin/marketing/api/center/{action}
 */
class CenterController extends ApiController
{
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'login' => ['class' => LoginFilter::class],
        ]);
    }

    /** 领券中心:进行中的领取型活动 */
    public function actionList()
    {
        $user = UserHelper::getUser();
        if (!$user) {
            return $this->asJson(['code' => ApiCode::CODE_FAIL, 'msg' => '请先登录', 'data' => null]);
        }
        return $this->asJson(ClaimService::listForUser((int)Yii::$app->mall->id, (int)$user->id));
    }

    /** 领取 */
    public function actionReceive()
    {
        $user = UserHelper::getUser();
        if (!$user) {
            return $this->asJson(['code' => ApiCode::CODE_FAIL, 'msg' => '请先登录', 'data' => null]);
        }
        $campaignId = (int)Yii::$app->request->post('campaign_id');
        return $this->asJson(ClaimService::claim((int)Yii::$app->mall->id, (int)$user->id, $campaignId));
    }

    /** 奖励进度:累计下单阶梯奖在当前周期的进度 */
    public function actionProgress()
    {
        $user = UserHelper::getUser();
        if (!$user) {
            return $this->asJson(['code' => ApiCode::CODE_FAIL, 'msg' => '请先登录', 'data' => null]);
        }
        $mallId = (int)Yii::$app->mall->id;
        $list = array_merge(
            CumulativeService::progress($mallId, (int)$user->id),
            OrderRewardService::progress($mallId, (int)$user->id)
        );
        return $this->asJson(['code' => ApiCode::CODE_SUCCESS, 'msg' => '', 'data' => ['list' => $list]]);
    }

    /** 我的优惠券(读核心 user_coupon 数据,展示层由插件承接) */
    public function actionMyCoupons()
    {
        $user = UserHelper::getUser();
        if (!$user) {
            return $this->asJson(['code' => ApiCode::CODE_FAIL, 'msg' => '请先登录', 'data' => null]);
        }
        $form = new MyCouponForm();
        $form->attributes = Yii::$app->request->get();
        $form->user_id = (int)$user->id;
        return $this->asJson($form->search());
    }
}
