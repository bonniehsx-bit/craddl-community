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

class UnbindForm extends BaseModel
{
    public $card_id;

    public function rules()
    {
        return [
            [['card_id'], 'required'],
            [['card_id'], 'integer'],
        ];
    }

    public function unbind()
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

        // 即使远端解绑失败也允许本地软删, 避免死卡卡位
        try {
            $payment = BankCardGatewayResolver::resolve($card->gateway, $this->mall_id);
            $payment->unbindCard($card->bind_card_id);
        } catch (\Exception $e) {
            Yii::warning('[BankCard unbind] 远端解绑失败但继续本地删除: ' . $e->getMessage());
        }

        $card->bind_status = UserBankCard::STATUS_UNBOUND;
        $card->is_default  = 0;
        $card->is_delete   = 1;
        if (!$card->save()) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $card->getErrorMsg());
        }

        // 若删的是默认卡, 自动把最近一张生效卡置为默认 (同网关下)
        $next = UserBankCard::find()->where([
            'user_id'     => $userId,
            'mall_id'     => $this->mall_id,
            'gateway'     => $card->gateway,
            'bind_status' => UserBankCard::STATUS_ACTIVE,
            'is_delete'   => 0,
        ])->orderBy(['id' => SORT_DESC])->one();
        if ($next) {
            $hasDefault = UserBankCard::find()->where([
                'user_id'    => $userId, 'mall_id' => $this->mall_id,
                'gateway'    => $card->gateway,
                'is_default' => 1, 'is_delete' => 0,
            ])->exists();
            if (!$hasDefault) {
                $next->is_default = 1;
                $next->save();
            }
        }

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '解绑成功');
    }
}
