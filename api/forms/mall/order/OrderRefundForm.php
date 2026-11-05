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

namespace app\forms\mall\order;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\OrderRefund;
use app\services\RefundService;

class OrderRefundForm extends BaseModel
{
    public $id;
    public $status;
    public $remark; // 拒绝原因(后台弹窗里必填),顾客在售后详情里看到

    // 新增：退货地址参数
    public $return_name;
    public $return_mobile;
    public $return_address;

    public function rules()
    {
        return [
            [['id'], 'required'],
            [['status', 'id'], 'integer'],
            // 新增：字符串类型，选填（仅同意退货时需要）
            [['return_name', 'return_mobile', 'return_address'], 'string', 'max' => 255],
            [['remark'], 'trim'],
            [['remark'], 'string', 'max' => 255],
        ];
    }

    /**
     * 商家审核售后申请 (同意/拒绝)
     */
    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        if (!$this->isOwnRefund()) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '售后单不存在');
        }

        $refundService = new RefundService();
        $result = false;

        // status=1 代表同意
        if ($this->status == 1) {
            // 收集退货地址参数
            $params = [
                'return_name' => $this->return_name,
                'return_mobile' => $this->return_mobile,
                'return_address' => $this->return_address,
            ];
            $result = $refundService->agree($this->id, $params);
        }
        // status=2 代表拒绝
        else if ($this->status == 2) {
            // 拒绝原因用后台填的(原来写死成下面这句,填了也被丢掉);接口没传才用默认说法
            $result = $refundService->reject($this->id, $this->remark ?: '商家拒绝了您的申请');
        } else {
            return $this->apiResponse(ApiCode::CODE_FAIL, '无效的操作状态');
        }

        if ($result) {
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '处理成功');
        } else {
            return $this->apiResponse(ApiCode::CODE_FAIL, $this->getErrorMsg($refundService));
        }
    }

    /**
     * 执行退款打款操作
     */
    public function pay()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        if (!$this->isOwnRefund()) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '售后单不存在');
        }

        $refundService = new RefundService();
        if ($refundService->completeRefund($this->id)) {
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '已发起退款,系统会按原支付方式自动退回,可在列表看退款进度');
        } else {
            return $this->apiResponse(ApiCode::CODE_FAIL, $this->getErrorMsg($refundService));
        }
    }

    /**
     * 【新增】确认换货完成
     */
    public function exchangeComplete()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        if (!$this->isOwnRefund()) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '售后单不存在');
        }

        $refundService = new RefundService();
        if ($refundService->completeExchange($this->id)) {
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '换货完成');
        } else {
            return $this->apiResponse(ApiCode::CODE_FAIL, $this->getErrorMsg($refundService));
        }
    }

    /**
     * 售后单是不是本商城的。只凭 id 就操作的话,改个 id 就能同意 / 拒绝 / 打款别的商城的售后
     * (商家后台那边的表单按当前商户核对过了,平台后台这边原来没核对)
     */
    private function isOwnRefund(): bool
    {
        return OrderRefund::find()->where(['id' => (int)$this->id, 'mall_id' => (int)\Yii::$app->mall->id, 'is_delete' => 0])->exists();
    }
}
