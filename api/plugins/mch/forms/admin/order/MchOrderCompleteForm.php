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
use app\models\Order;
use app\services\OrderService;
use Yii;

// [核心] 引入服务层

/**
 * Class MchOrderCompleteForm
 * @package app\plugins\mch\forms\admin
 * @desc 商家后台用于“自行配送”订单，点击“确认送达”的表单
 */
class MchOrderCompleteForm extends BaseModel
{
    public $order_id;

    public function rules()
    {
        return [
            [['order_id'], 'required'],
            [['order_id'], 'integer'],
        ];
    }

    public function complete()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        try {
            $currentMch = Yii::$app->mch;

            /** @var Order $order */
            $order = Order::find()->where([
                'id' => $this->order_id,
                'mch_id' => $currentMch->id,
                'is_delete' => 0
            ])->one();

            if (!$order) {
                throw new \Exception('订单不存在或无权操作');
            }

            // [核心校验] 必须是“待收货”状态
            if ($order->status !== Order::STATUS_SHIPPED) {
                throw new \Exception('订单不是待收货状态，无法操作');
            }

            // [业务委托] 调用核心 OrderService 的 confirm 方法
            $orderService = new OrderService();
            if ($orderService->confirm($order)) {
                // confirm 方法成功执行，订单状态已更新为“已完成”
                return $this->apiResponse(ApiCode::CODE_SUCCESS, '操作成功，订单已完成');
            } else {
                // 如果 confirm 方法失败，从 service 中获取错误信息
                throw new \Exception($this->getErrorMsg($orderService));
            }

        } catch (\Exception $e) {
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }
}