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
use app\services\order\actions\OrderShipmentService;
use Yii;

/**
 * 商户端:发货后修改快递公司/单号
 */
class MchOrderExpressUpdateForm extends BaseModel
{
    public $order_id;
    public $express_log_id;
    public $express_name;
    public $express_code;
    public $express_no;

    public function rules()
    {
        return [
            [['order_id', 'express_log_id', 'express_name', 'express_no'], 'required'],
            [['order_id', 'express_log_id'], 'integer'],
            [['express_name', 'express_code', 'express_no'], 'string'],
            [['express_code'], 'default', 'value' => ''],
        ];
    }

    public function attributeLabels()
    {
        return [
            'express_name' => '快递公司',
            'express_no' => '快递单号',
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
                'mch_id' => Yii::$app->mch->id,
                'is_delete' => 0,
            ]);
            if (!$order) {
                throw new \Exception('订单不存在或无权操作');
            }

            $user = Yii::$app->user->identity;
            $result = (new OrderShipmentService())->updateExpress(
                $order,
                (int)$this->express_log_id,
                (string)$this->express_name,
                (string)$this->express_code,
                (string)$this->express_no,
                [
                    'type' => 'mch',
                    'id' => (int)($user->id ?? 0),
                    'name' => (string)($user->nickname ?? ''),
                ]
            );

            return $this->apiResponse(ApiCode::CODE_SUCCESS, '修改成功', $result);
        } catch (\Throwable $e) {
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }
}
