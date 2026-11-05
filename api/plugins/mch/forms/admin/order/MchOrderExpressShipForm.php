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
use app\services\OrderService; // [核心] 引入 OrderService
use Yii;

/**
 * Class MchOrderExpressShipForm
 * @package app\plugins\mch\forms\admin
 * @desc 商家后台用于“快递订单”发货的表单 (重构版)
 */
class MchOrderExpressShipForm extends BaseModel
{
    public $order_id;
    public $express_code;
    public $express_name;
    public $express_no;

    public function rules()
    {
        return [
            [['order_id', 'express_name', 'express_no'], 'required'],
            [['order_id'], 'integer'],
            [['express_code', 'express_name', 'express_no'], 'string']
        ];
    }

    public function attributeLabels()
    {
        return [
            'express_name' => '快递公司',
            'express_no'   => '快递单号',
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        try {
            $currentMch = Yii::$app->mch;

            /** @var Order $order */
            $order = Order::findOne([
                'id'           => $this->order_id,
                'mch_id'       => $currentMch->id,
                'is_delete'    => 0,
                'status'       => Order::STATUS_TO_BE_SHIPPED,
                'deliver_type' => 'express'
            ]);

            if (!$order) {
                throw new \Exception('订单不存在、无权操作或状态不正确!');
            }

            // 人工发货必须先过插件按单封印(未开奖的拼团单、门店派单的艾灸单等)。
            // 后台只把按钮藏起来是不够的 —— 接口照样打得进来。
            (new \app\services\order\actions\OrderShipmentService())->assertManualShipAllowed($order);

            // ======================= [核心改造] 开始 =======================
            // 调用统一的发货服务，并明确指定 sendType 为 0 (整单发货)
            (new OrderService())->ship(
                $order,
                0, // sendType = 0, 代表整单发货
                [], // detailIds 为空
                $this->express_name,
                $this->express_code,
                $this->express_no
            );
            // ======================= [核心改造] 结束 =======================

            return $this->apiResponse(ApiCode::CODE_SUCCESS, '发货成功');

        } catch (\Exception $e) {
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }
}