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
use Yii;

class MchOrderAddressModifyForm extends BaseModel
{
    public $name;
    public $mobile;
    public $address;
    public $order_id;

    public function rules()
    {
        return [
            [['mobile', 'address', 'name', 'order_id'], 'required'],
            [['order_id'], 'integer'],
            [['address', 'name', 'mobile'], 'string'],
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        // ⛔ 不能用 Yii::$app->mchId:商户端请求里从来没人设置它,恒为 0 ——
        //    按 mch_id=0 查,商家改不了自己的单,反而能改平台自营单(且原来不带 mall_id,跨商城)。
        //    当前商户以 MchFilter 注入的 Yii::$app->mch 为准。
        $order = Order::findOne([
            'is_delete' => 0,
            'id' => $this->order_id,
            'mall_id' => Yii::$app->mall->id,
            'mch_id' => Yii::$app->mch->id,
        ]);
        if (!$order) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '订单不存在，或无权操作');
        }
        // 发货后包裹已经按旧地址寄出,再改只会让订单地址和实际收件地址对不上
        if (!in_array((int)$order->status, [Order::STATUS_UNPAID, Order::STATUS_TO_BE_SHIPPED], true)
            || (int)$order->delivery_status !== Order::DELIVERY_STATUS_UNSHIPPED) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '订单已发货，不能再修改收货信息');
        }
        
        $order->name = $this->name;
        $order->mobile = $this->mobile;
        $order->address = $this->address;

        if (!$order->save()) {
            return $this->responseErrorMsg($order);
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '修改收货人信息成功');
    }
}
