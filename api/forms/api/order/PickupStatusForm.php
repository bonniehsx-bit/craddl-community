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

namespace app\forms\api\order;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Order;
use Yii;

/**
 * C端 - 轮询自提核销状态（轻量接口）
 *
 * 仅返回核销相关的几个字段，供前端展示核销码时每 5 秒静默轮询。
 * 核销完成（status 从 20 变走）后前端停止轮询。
 */
class PickupStatusForm extends BaseModel
{
    public $order_id;

    public function rules(): array
    {
        return [
            [['order_id'], 'required'],
            [['order_id'], 'integer'],
        ];
    }

    public function check(): array
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $userId = (int)Yii::$app->user->id;
        $mallId = (int)Yii::$app->mall->id;

        $order = Order::find()
            ->select(['id', 'status', 'deliver_type', 'pickup_code', 'clerk_name'])
            ->where([
                'id'      => $this->order_id,
                'user_id' => $userId,
                'mall_id' => $mallId,
            ])
            ->asArray()
            ->one();

        if (!$order) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '订单不存在'];
        }

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'data' => [
                'status'       => (int)$order['status'],
                'deliver_type' => $order['deliver_type'],
                'pickup_code'  => $order['pickup_code'],
                'clerk_name'   => $order['clerk_name'] ?: '',
                'verified'     => (int)$order['status'] !== 20,
            ],
        ];
    }
}
