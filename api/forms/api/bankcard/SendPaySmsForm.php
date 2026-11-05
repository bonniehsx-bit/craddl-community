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
use app\models\Order;
use app\models\UserBankCard;
use Yii;

/**
 * 快捷支付 下单+发短信: 走具体网关 placeOrder() 的"申请"阶段
 * 返回成功后前端弹短信验证码输入框, 再调 VerifyPayForm
 *
 * 本 Form 不接受前端传 gateway, gateway 跟随用户选中的 UserBankCard.gateway
 * (避免伪造 —— 用户只能用自己真实绑过的卡支付)。
 */
class SendPaySmsForm extends BaseModel
{
    public $order_no;
    public $card_id;

    public function rules()
    {
        return [
            [['order_no', 'card_id'], 'required'],
            [['order_no'], 'string', 'max' => 64],
            [['card_id'], 'integer'],
        ];
    }

    public function send()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $userId = UserHelper::getMyId();

        $order = CommonPayOrder::findOne(['order_no' => $this->order_no]);
        if (!$order) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '订单不存在');
        }
        if ($order->is_cancel) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '订单已关闭');
        }
        if ($order->is_pay) {
            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '订单已支付', ['is_pay' => 1]);
        }
        if ($order->user_id && $order->user_id != $userId) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '订单不存在');
        }
        $mallOrder = Order::findOne(['order_no' => $this->order_no]);
        if ($mallOrder && $mallOrder->status != Order::STATUS_UNPAID) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '订单当前状态不可支付');
        }

        $card = UserBankCard::findOne([
            'id'          => $this->card_id,
            'user_id'     => $userId,
            'mall_id'     => $this->mall_id,
            'bind_status' => UserBankCard::STATUS_ACTIVE,
            'is_delete'   => 0,
        ]);
        if (!$card) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '所选银行卡不存在或未生效');
        }

        try {
            $payment = BankCardGatewayResolver::resolve($card->gateway, $this->mall_id);
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $e->getMessage());
        }

        $order->user_id  = $userId;
        $order->platform = Yii::$app->platform;
        $order->payment  = $card->gateway;
        $order->save();

        try {
            $result = $payment->placeOrder([
                'body'         => '订单支付',
                'order_no'     => $order->order_no,
                'pay_price'    => $order->pay_price,
                'user_id'      => (string)$userId,
                'bind_card_id' => $card->bind_card_id,
            ]);
        } catch (\Exception $e) {
            Yii::warning('[BankCard sendPaySms] ' . $e->getMessage());
            return ResponseHelper::send(ApiCode::CODE_FAIL, $e->getMessage());
        }

        $status = $result['status'] ?? '';
        if ($status === 'SUCCESS') {
            // ⚠ 原来是「先 $order->is_pay = 1 并保存,再 $order->pay()」—— 那是空转:
            // pay() 转调 paySuccess(),而它第一句 `if ($this->is_pay == 1) return true;`
            // 会立刻命中刚存进去的 1,**业务回调(发货/分润/事件)一次都不执行**,
            // 同时绕过并发锁与 is_cancel 检查。直接交给 paySuccess,它自带幂等。
            // (与 VerifyPayForm / PayForm / FastPayQuickPayment 三处同一修法)
            // 重新查一遍:placeOrder 内部 updateAll 过 payment 字段,手里这个实例已经旧了。
            $fresh = CommonPayOrder::findOne($order->id);
            if ($fresh && !$fresh->is_pay) {
                $txn = $result['atuOrderNo'] ?? ($result['channelFlowNo'] ?? '');
                if (!$fresh->paySuccess((string) $txn, $fresh->payment ?: null)) {
                    Yii::warning('[BankCard sendPaySms] 置已支付失败(可能已取消/并发锁),'
                        . "pay_order_id={$fresh->id}");
                }
            }
            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '支付成功', [
                'need_sms' => 0,
                'is_pay'   => 1,
            ]);
        }

        if ($status !== 'DOING' && $status !== 'PROCESS') {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $result['desc'] ?? '下单失败');
        }

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '短信验证码已下发', [
            'need_sms'    => 1,
            'order_no'    => $order->order_no,
            'mobile_mask' => substr($card->mobile, 0, 3) . '****' . substr($card->mobile, -4),
        ]);
    }
}
