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

/**
 * 商家确认退款 (打款)
 */
class RefundPayForm extends BaseModel
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

        // 【核心修复】使用常量进行打款的前置状态判断
        // 1. 仅退款单 (type=1)：状态为 STATUS_AGREED (商家已同意)
        // 2. 退货退款单 (type=2)：状态为 STATUS_USER_SHIPPED (用户已发货/商家已收货)

        $canPay = false;

        if ($refund->type == 1 && $refund->status == OrderRefund::STATUS_AGREED) {
            $canPay = true; // 仅退款，同意后直接打款
        } elseif (($refund->type == 2) && $refund->status == OrderRefund::STATUS_USER_SHIPPED) {
            $canPay = true; // 退货退款，用户发货后商家确认打款
        }

        if (!$canPay) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '当前状态无法执行退款操作，请检查是否已收货');
        }

        $service = new RefundService();

        // 调用服务层执行退款
        if ($service->completeRefund($this->id)) {
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '已发起退款,系统会按原支付方式自动退回,可在列表看退款进度');
        } else {
            return $this->apiResponse(ApiCode::CODE_FAIL, $service->getFirstErrorMsg() ?: '退款失败');
        }
    }
}