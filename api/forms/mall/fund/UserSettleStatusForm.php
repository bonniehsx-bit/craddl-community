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

namespace app\forms\mall\fund;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\UserSettlementInfo;

/**
 * [新增] 负责手动修改用户进件状态的 Form
 */
class UserSettleStatusForm extends BaseModel
{
    public $user_settlement_id;
    public $channel_class;

    public function rules()
    {
        return [
            [['user_settlement_id', 'channel_class'], 'required'],
            [['user_settlement_id'], 'integer'],
            [['channel_class'], 'string'],
        ];
    }

    /**
     * 将指定渠道的状态重置为“失败”
     */
    public function changeStatus()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $model = UserSettlementInfo::findOne([
            'id' => $this->user_settlement_id,
            'mall_id' => \Yii::$app->mall->id,
            'is_delete' => 0
        ]);

        if (!$model) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '未找到该用户的结算记录');
        }

        $extraInfo = $model->getExtraInfo();
        if (empty($extraInfo['onboarding_status'][$this->channel_class])) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '未找到该渠道的进件状态，无法重置');
        }

        // 核心逻辑：将状态更新为“失败”，并添加备注
        $model->updateOnboardingStatus($this->channel_class, [
            'status' => UserSettlementInfo::FUND_STATUS_FAILED,
            'remark' => '管理员于 ' . date('Y-m-d H:i') . ' 手动重置为失败'
        ]);

        if (!$model->save()) {
            return $this->responseErrorMsg($model);
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '状态已重置为“失败”');
    }
}