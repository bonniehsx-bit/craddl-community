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
use app\models\OrderRefund;
use app\plugins\mch\models\Mch;
use app\services\RefundService;
use Yii;

/**
 * 商家审核 (同意/拒绝)
 */
class RefundActionForm extends BaseModel
{
    public $id;
    public $status; // 1=同意, 2=拒绝
    public $remark;
    public $return_name;
    public $return_mobile;
    public $return_address;

    public function rules()
    {
        return [
            [['id', 'status'], 'required'],
            [['id', 'status'], 'integer'],
            [['remark', 'return_name', 'return_mobile', 'return_address'], 'string'],
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $currentMch = Yii::$app->mch;

        /** @var OrderRefund $refund */
        $refund = OrderRefund::findOne([
            'id' => $this->id,
            'mch_id' => $currentMch->id,
            'is_delete' => 0,
        ]);

        if (!$refund) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '售后单不存在');
        }

        // 状态校验：只有“待审核”状态可以操作
        if ($refund->status != OrderRefund::STATUS_PENDING) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '当前状态不可审核');
        }

        $service = new RefundService();
        $result = false;

        // === 同意 ===
        if ($this->status == 1) { // 这里的前端传参通常不做常量映射，保持1即可
            $params = [];

            // 只有 "退货退款(2)" 或 "换货(3)" 才需要退货地址
            // "仅退款(1)" 不需要地址
            if ($refund->type != 1) {
                // 如果前端传了地址，优先使用；否则取店铺全局设置
                if ($this->return_name && $this->return_mobile && $this->return_address) {
                    $params = [
                        'return_name' => $this->return_name,
                        'return_mobile' => $this->return_mobile,
                        'return_address' => $this->return_address,
                    ];
                } else {
                    $mchInfo = Mch::findOne($currentMch->id);
                    if (!$mchInfo || !$mchInfo->refund_address || !$mchInfo->refund_name || !$mchInfo->refund_mobile) {
                        return $this->apiResponse(ApiCode::CODE_FAIL, '请先在店铺设置中配置【退货地址】或填写临时退货地址');
                    }
                    $params = [
                        'return_name' => $mchInfo->refund_name,
                        'return_mobile' => $mchInfo->refund_mobile,
                        'return_address' => $mchInfo->refund_address,
                    ];
                }
            }

            $result = $service->agree($this->id, $params);
        }

        // === 拒绝 ===
        elseif ($this->status == 2) {
            $reason = $this->remark ?: '商家拒绝申请';
            $result = $service->reject($this->id, $reason);
        }

        if ($result) {
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '操作成功');
        } else {
            return $this->apiResponse(ApiCode::CODE_FAIL, $service->getFirstErrorMsg() ?: '操作失败');
        }
    }
}