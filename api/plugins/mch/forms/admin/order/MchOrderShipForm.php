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

// [核心] 引入 OrderService
use Yii;

class MchOrderShipForm extends BaseModel
{
    public $order_id;
    public $expected_arrival_time;

    public function rules()
    {
        return [
            [['order_id'], 'required'],
            [['order_id'], 'integer'],
            [['expected_arrival_time'], 'safe'], // 简单配送:预计送达时间选填,不填则直接开始配送
        ];
    }

    public function attributeLabels()
    {
        return ['expected_arrival_time' => '预计送达时间'];
    }

    public function ship()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        try {
            $currentMch = Yii::$app->mch;

            /** @var Order $order */
            $order = Order::find()->where([
                'id' => $this->order_id,
                'mch_id' => $currentMch->id, // 权限校验依然保留在Form层
                'is_delete' => 0
            ])->one();

            if (!$order) {
                throw new \Exception('订单不存在或无权操作');
            }
            // 商家自己配送也是人工发货:有售后还在处理就不能开始配送(与快递发货同一个守门人)
            (new \app\services\order\actions\OrderShipmentService())->assertManualShipAllowed($order);

            // [核心] 所有业务逻辑都委托给 OrderService(预计送达时间选填,空则传空串)
            (new OrderService())->shipByMerchantSelf($order, (string)$this->expected_arrival_time);

            return $this->apiResponse(ApiCode::CODE_SUCCESS, '操作成功，订单已发货');

        } catch (\Exception $e) {
            // Service层抛出的任何异常都会在这里被捕获并统一返回
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }
}