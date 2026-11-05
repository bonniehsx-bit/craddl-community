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
use app\models\OrderRefund;
use app\services\RefundService;
use Yii;

/**
 * Class RefundFillExpressForm
 * @package app\forms\api\order
 *
 * 处理用户填写退货运单的表单
 */
class RefundFillExpressForm extends BaseModel
{
    public $order_detail_id;
    public $express;
    public $express_no;

    public function rules()
    {
        return [
            [['order_detail_id', 'express', 'express_no'], 'required'],
            [['order_detail_id'], 'integer'],
            [['express', 'express_no'], 'trim'],
            [['express', 'express_no'], 'string', 'max' => 255],
        ];
    }

    public function attributeLabels()
    {
        return [
            'order_detail_id' => '订单详情ID',
            'express' => '快递公司',
            'express_no' => '快递单号',
        ];
    }

    /**
     * 保存用户提交的运单信息
     * @return array
     */
    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        try {
            // 通过 order_detail_id 找到对应的、状态为“商家已同意”的售后单
            // 这是一个非常严谨的查询，确保用户只能操作正确的售后单
            $refund = OrderRefund::findOne([
                'order_detail_id' => $this->order_detail_id,
                'user_id' => Yii::$app->user->id,
                'status' => OrderRefund::STATUS_AGREED, // 必须是“已同意”状态
                'is_delete' => 0,
            ]);

            if (!$refund) {
                return $this->apiResponse(ApiCode::CODE_FAIL, '售后申请不存在或当前状态无法操作');
            }

            // 实例化服务层
            $service = new RefundService();

            // 准备需要传递给服务的参数
            $params = [
                'express' => $this->express,
                'express_no' => $this->express_no,
            ];

            // 调用服务层来处理核心业务逻辑
            $result = $service->fillReturnExpress($refund->id, Yii::$app->user->id, $params);

            if ($result) {
                return $this->apiResponse(ApiCode::CODE_SUCCESS, '运单号提交成功');
            } else {
                // 如果服务层返回失败，获取详细错误信息
                return $this->apiResponse(ApiCode::CODE_FAIL, $service->getFirstErrorMsg() ?: '提交失败，请稍后重试');
            }

        } catch (\Exception $e) {
            Yii::error("填写售后运单失败: " . $e->getMessage());
            return $this->apiResponse(ApiCode::CODE_FAIL, '服务器内部错误，请联系管理员');
        }
    }
}