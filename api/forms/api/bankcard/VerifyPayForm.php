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

namespace app\forms\api\bankcard;

use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\helpers\UserHelper;
use app\models\BaseModel;
use app\models\CommonPayOrder;
use app\payments\contracts\QuickPaymentInterface;
use Yii;

/**
 * 快捷支付短信确认: 对应具体网关的 verifyPayment()
 * 成功后订单转已支付 (由第三方异步回调 handleCallback 最终落库,
 * 但这里也把同步回包里的 SUCCESS 立即落一次, 防止回调丢失)
 *
 * gateway 从 CommonPayOrder.payment 字段反推(订单在 sendPaySms 阶段已经写死为具体支付类 FQN),
 * 前端无需重传 gateway, 同时也避免伪造。
 */
class VerifyPayForm extends BaseModel
{
    public $order_no;
    public $smsCode;

    public function rules()
    {
        return [
            [['order_no', 'smsCode'], 'required'],
            [['order_no'], 'string', 'max' => 64],
            [['smsCode'], 'string', 'max' => 10],
        ];
    }

    public function verify()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $order = CommonPayOrder::findOne(['order_no' => $this->order_no]);
        if (!$order) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '订单不存在');
        }
        if ($order->is_pay) {
            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '订单已支付', ['is_pay' => 1]);
        }
        if ($order->user_id && $order->user_id != UserHelper::getMyId()) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '订单不存在');
        }

        try {
            $payment = BankCardGatewayResolver::resolve($order->payment, $this->mall_id);
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '该订单未使用快捷支付');
        }

        try {
            $result = $payment->verifyPayment($order->order_no, $this->smsCode);
        } catch (\Exception $e) {
            Yii::warning('[BankCard verifyPay] ' . $e->getMessage());
            return ResponseHelper::send(ApiCode::CODE_FAIL, $e->getMessage());
        }

        $status = $result['status'] ?? '';
        if ($status === 'SUCCESS') {
            // ⚠ 原来是「先 $fresh->is_pay = 1 并保存,再 $fresh->pay()」—— 那是空转:
            // pay() 转调 paySuccess(),而它第一句 `if ($this->is_pay == 1) return true;`
            // 会立刻命中刚存进去的 1,**业务回调(发货/分润/事件)一次都不执行**,
            // 同时绕过并发锁与 is_cancel 检查。直接交给 paySuccess,它自带幂等。
            $fresh = CommonPayOrder::findOne($order->id);
            if ($fresh) {
                $txn = $result['atuOrderNo'] ?? ($result['channelFlowNo'] ?? '');
                if (!$fresh->paySuccess((string) $txn, $fresh->payment ?: null)) {
                    Yii::warning('[BankCard verifyPay] 置已支付失败(可能已取消/并发锁),'
                        . "pay_order_id={$fresh->id}");
                }
            }
            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '支付成功', ['is_pay' => 1]);
        }

        if ($status === 'DOING' || $status === 'PROCESS') {
            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '支付处理中', ['is_pay' => 0]);
        }

        return ResponseHelper::send(ApiCode::CODE_FAIL, $result['desc'] ?? '短信验证失败');
    }
}
