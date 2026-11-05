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
use app\models\UserBankCard;
use Yii;

/**
 * 绑卡短信确认: 调具体网关的 verifyBindCard()
 * 成功后置 UserBankCard.bind_status = 1 (生效)
 */
class VerifyBindForm extends BaseModel
{
    public $card_id;
    public $smsCode;

    public function rules()
    {
        return [
            [['card_id', 'smsCode'], 'required'],
            [['card_id'], 'integer'],
            [['smsCode'], 'string', 'max' => 10],
        ];
    }

    public function verify()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $userId = UserHelper::getMyId();
        $card = UserBankCard::findOne([
            'id'        => $this->card_id,
            'user_id'   => $userId,
            'mall_id'   => $this->mall_id,
            'is_delete' => 0,
        ]);
        if (!$card) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '绑卡记录不存在');
        }
        if ($card->bind_status == UserBankCard::STATUS_ACTIVE) {
            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '已绑定成功');
        }

        try {
            $payment = BankCardGatewayResolver::resolve($card->gateway, $this->mall_id);
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $e->getMessage());
        }

        try {
            $result = $payment->verifyBindCard($card->bind_card_id, $this->smsCode);
        } catch (\Exception $e) {
            Yii::warning('[BankCard verifyBind] ' . $e->getMessage());
            return ResponseHelper::send(ApiCode::CODE_FAIL, $e->getMessage());
        }

        $status = $result['status'] ?? '';
        if ($status !== 'SUCCESS') {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $result['desc'] ?? '短信验证失败');
        }

        $card->bind_status = UserBankCard::STATUS_ACTIVE;
        if (!empty($result['bankName']) && empty($card->bank_name)) {
            $card->bank_name = $result['bankName'];
        }
        if (!empty($result['bankCode']) && empty($card->bank_code)) {
            $card->bank_code = $result['bankCode'];
        }

        // 若该用户此网关下还没有默认卡, 把本张设为默认
        $hasDefault = UserBankCard::find()->where([
            'user_id'    => $userId, 'mall_id' => $this->mall_id,
            'gateway'    => $card->gateway,
            'is_default' => 1, 'is_delete' => 0,
        ])->andWhere(['!=', 'id', $card->id])->exists();
        if (!$hasDefault) {
            $card->is_default = 1;
        }

        if (!$card->save()) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $card->getErrorMsg());
        }

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '绑卡成功', $card->toDisplayArray());
    }
}
