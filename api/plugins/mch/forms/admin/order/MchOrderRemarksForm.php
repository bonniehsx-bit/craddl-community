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

class MchOrderRemarksForm extends BaseModel
{
    public $order_id;
    public $remarks;

    public function rules()
    {
        return [
            [['order_id'], 'required'],
            [['order_id'], 'integer'],
            [['remarks'], 'string'],
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $order = Order::findOne([
            'is_delete' => 0, 
            'id' => $this->order_id,
            'mall_id' => Yii::$app->mall->id,
            'mch_id' => Yii::$app->mch->id, // Yii::$app->mchId 在商户端恒为 0,见 MchOrderAddressModifyForm
        ]);
        if (!$order) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '订单不存在，或无权操作');
        }
        // ⛔ 写 seller_remarks,**不能写 remarks** ——
        //    Order 模型注释白纸黑字:remarks=买家备注 / seller_remarks=商家备注。
        //    原来这里写的是 remarks,商家一填备注就把买家写的"不要辣、放门口"整段覆盖掉,
        //    不可恢复。两端都没接这个入口,大概率就是有人发现不对劲没敢接。
        $order->seller_remarks = (string) $this->remarks;

        if (!$order->save()) {
            return $this->responseErrorMsg($order);
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '备注保存成功');
    }
}
