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
use app\core\ApiCode;
use app\helpers\UserHelper;
use app\plugins\ApiController;
use app\plugins\withdraw\forms\api\ApplyForm;
use app\plugins\withdraw\forms\api\PrepareForm;
use app\plugins\withdraw\forms\api\RecordsForm;
use app\plugins\withdraw\models\Channel;
use app\plugins\withdraw\services\WithdrawService;
use Yii;

/**
 * C 端 - 提现
 *
 * 路由：plugin/withdraw/api/withdraw/{action}
 */
class WithdrawController extends ApiController
{
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'login' => ['class' => LoginFilter::class],
        ]);
    }

    /** 提现准备页（可用资金来源 + 渠道） */
    public function actionPrepare()
    {
        $form = new PrepareForm();
        $form->attributes = Yii::$app->request->get();
        return $this->asJson($form->getData());
    }

    /**
     * 资料/资格校验（提交前调用）。
     * GET ?channel_id=xxx → {is_eligible, message, require_onboarding, redirect_url, channel}
     * 前端：不通过且 require_onboarding → 跳 redirect_url?channel=xxx 填收款资料
     */
    public function actionCheckEligibility()
    {
        $user = UserHelper::getUser();
        if (!$user) {
            return $this->asJson(['code' => ApiCode::CODE_FAIL, 'msg' => '请先登录', 'data' => null]);
        }
        $channelId = (int)Yii::$app->request->get('channel_id');
        $channel = Channel::findOne(['id' => $channelId, 'mall_id' => Yii::$app->mall->id, 'is_delete' => 0, 'status' => Channel::STATUS_ON]);
        if (!$channel) {
            return $this->asJson(['code' => ApiCode::CODE_FAIL, 'msg' => '提现渠道不存在', 'data' => null]);
        }
        $result = (new WithdrawService())->checkEligibility($user, $channel);
        return $this->asJson(['code' => ApiCode::CODE_SUCCESS, 'msg' => '查询成功', 'data' => $result]);
    }

    /** 提交提现申请 */
    public function actionApply()
    {
        $form = new ApplyForm();
        $form->attributes = Yii::$app->request->post();
        return $this->asJson($form->submit());
    }

    /**
     * 某资金来源的提现入口是否开启（中心页据此显隐「提现」按钮）。
     * GET ?asset_type=balance → {enabled: 0|1}
     */
    public function actionEntry()
    {
        $asset = (string)Yii::$app->request->get('asset_type', '');
        $mallId = (int)Yii::$app->mall->id;
        // 审核版(小程序 / App 提审中的版本号)不开放提现入口,与首页、个人中心的审核版同一个判断
        $enabled = !\app\helpers\VersionHelper::checkIsAuditMode($mallId)
            && \app\plugins\withdraw\forms\admin\setting\SettingForm::entryEnabled($mallId, $asset);
        return $this->asJson(['code' => ApiCode::CODE_SUCCESS, 'msg' => '', 'data' => ['enabled' => $enabled ? 1 : 0]]);
    }


    /** 我的提现记录 */
    public function actionRecords()
    {
        $form = new RecordsForm();
        $form->attributes = Yii::$app->request->get();
        return $this->asJson($form->getList());
    }

    /** 提现记录详情 */
    public function actionDetail()
    {
        $form = new RecordsForm();
        $form->attributes = Yii::$app->request->get();
        return $this->asJson($form->getDetail());
    }
}
