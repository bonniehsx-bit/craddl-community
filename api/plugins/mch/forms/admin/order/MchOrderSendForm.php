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

class MchOrderSendForm extends BaseModel
{
    public $order_id;
    public $express_code;
    public $express_name;
    public $express_no;
    public $send_type;
    public $send_list;

    public function rules()
    {
        return [
            [['order_id', 'send_type'], 'required'],
            [['order_id', 'send_type'], 'integer'],
            [['send_list'], 'safe'],
            [['express_code', 'express_name', 'express_no'], 'string']
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        try {
            /** @var Order $order */
            $order = Order::findOne([
                'id' => $this->order_id,
                'is_delete' => 0,
                'mall_id' => Yii::$app->mall->id,
                'mch_id' => Yii::$app->mch->id, // Yii::$app->mchId 在商户端恒为 0,见 MchOrderAddressModifyForm
            ]);
            if (!$order) {
                throw new \Exception('订单不存在或无权操作');
            }

            // 人工发货必须先过插件按单封印(未开奖的拼团单、门店派单的艾灸单等)。
            // 后台只把按钮藏起来是不够的 —— 接口照样打得进来。
            (new \app\services\order\actions\OrderShipmentService())->assertManualShipAllowed($order);

            // 调用总后台统一的发货核心管线进行发货 (支持快递、自提同城、部分发货等)
            $orderService = new OrderService();
            $orderService->ship(
                $order,
                $this->send_type,
                $this->send_list ?: [],
                $this->express_name,
                $this->express_code,
                $this->express_no
            );

            return $this->apiResponse(ApiCode::CODE_SUCCESS, '发货成功');

        } catch (\Throwable $e) {
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }
}
