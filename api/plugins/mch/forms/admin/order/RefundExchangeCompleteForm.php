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

namespace app\plugins\mch\forms\admin\order;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\OrderRefund;
use app\services\RefundService;
use Yii;

class RefundExchangeCompleteForm extends BaseModel
{
    public $id;

    public function rules()
    {
        return [
            [['id'], 'required'],
            [['id'], 'integer'],
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $currentMch = Yii::$app->mch;

        /** @var OrderRefund $refund */
        $refund = OrderRefund::findOne([
            'id' => $this->id,
            'mch_id' => $currentMch->id,
            'is_delete' => 0,
        ]);

        if (!$refund) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '售后单不存在');
        }

        $service = new RefundService();
        if ($service->completeExchange($this->id)) {
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '换货完成');
        } else {
            return $this->apiResponse(ApiCode::CODE_FAIL, $service->getFirstErrorMsg() ?: '操作失败');
        }
    }
}
