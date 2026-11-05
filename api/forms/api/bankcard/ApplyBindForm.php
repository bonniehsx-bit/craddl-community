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
 * 申请绑卡: 透传明文敏感字段给具体 QuickPaymentInterface 实现类的 applyBindCard(),
 * 由支付类内部按各自规范加密后上送第三方。第三方成功返回 bindCardId 后,
 * 本地落一条 bind_status=0 (待短信验证) 记录, 等用户输入验证码后在 VerifyBindForm 置为生效。
 *
 * gateway 必须是实现 QuickPaymentInterface 的支付类完整类名 (与 CommonPayOrder.payment 字段保持一致)。
 */
class ApplyBindForm extends BaseModel
{
    public $gateway;

    public $userName;
    public $identityNo;
    public $identityType;   // 一期仅允许 '00' 大陆居民身份证
    public $bankCardNo;
    public $mobile;
    public $cDFlag;
    public $validDate;      // 信用卡有效期, 格式 MMYY
    public $cvv2;
    public $idDocPeriodBegin; // 证件有效期开始 yyyyMMdd
    public $idDocPeriodEnd;   // 证件有效期结束 yyyyMMdd, 长期固定 29991231

    public function rules()
    {
        return [
            [['gateway', 'userName', 'identityNo', 'bankCardNo', 'mobile', 'idDocPeriodBegin', 'idDocPeriodEnd'], 'required'],
            [['gateway'], 'string', 'max' => 128],
            [['userName'], 'string', 'max' => 64],
            [['identityNo'], 'match', 'pattern' => '/^\d{15}(\d{2}[0-9Xx])?$/', 'message' => '身份证号格式错误'],
            [['bankCardNo'], 'match', 'pattern' => '/^\d{12,19}$/', 'message' => '银行卡号格式错误'],
            [['mobile'], 'match', 'pattern' => '/^1[3-9]\d{9}$/', 'message' => '手机号格式错误'],
            [['identityType'], 'in', 'range' => ['00'], 'message' => '一期仅支持大陆居民身份证'],
            [['cDFlag'], 'in', 'range' => ['D', 'C']],
            [['validDate'], 'match', 'pattern' => '/^(0[1-9]|1[0-2])\d{2}$/', 'message' => '有效期格式应为 MMYY'],
            [['cvv2'], 'match', 'pattern' => '/^\d{3,4}$/', 'message' => 'CVV2 格式错误'],
            [['validDate', 'cvv2'], 'required', 'when' => function ($model) {
                return $model->cDFlag === 'C';
            }, 'whenClient' => null],
            [['idDocPeriodBegin'], 'match', 'pattern' => '/^\d{8}$/', 'message' => '证件开始日期格式应为 yyyyMMdd'],
            [['idDocPeriodEnd'], 'match', 'pattern' => '/^\d{8}$/', 'message' => '证件结束日期格式应为 yyyyMMdd'],
            [['idDocPeriodBegin', 'idDocPeriodEnd'], 'validateIdPeriod'],
        ];
    }

    public function validateIdPeriod($attribute)
    {
        $begin = $this->idDocPeriodBegin;
        $end = $this->idDocPeriodEnd;
        if (!$begin || !$end) return;
        if ($end !== '29991231' && $end <= $begin) {
            $this->addError($attribute, '证件结束日期必须晚于开始日期');
        }
    }

    public function apply()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $userId = UserHelper::getMyId();
        if (!$userId) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '请先登录');
        }

        try {
            $payment = BankCardGatewayResolver::resolve($this->gateway, $this->mall_id);
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $e->getMessage());
        }

        $cDFlag = $this->cDFlag ?: 'D';

        try {
            $result = $payment->applyBindCard([
                'user_id'          => (string)$userId,
                'userName'         => $this->userName,
                'identityNo'       => $this->identityNo,
                'identityType'     => $this->identityType ?: '00',
                'bankCardNo'       => $this->bankCardNo,
                'mobile'           => $this->mobile,
                'cDFlag'           => $cDFlag,
                'validDate'        => $this->validDate,
                'cvv2'             => $this->cvv2,
                'idDocPeriodBegin' => $this->idDocPeriodBegin,
                'idDocPeriodEnd'   => $this->idDocPeriodEnd,
            ]);
        } catch (\Exception $e) {
            Yii::warning('[BankCard applyBind] ' . $e->getMessage());
            return ResponseHelper::send(ApiCode::CODE_FAIL, $e->getMessage());
        }

        $status = $result['status'] ?? '';
        $desc = $result['desc'] ?? '';
        Yii::warning('[BankCard applyBind] response status=' . $status . ' desc=' . $desc);

        if ($status === 'FAIL') {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $desc ?: '绑卡失败');
        }
        if ($status !== 'DOING' && $status !== 'SUCCESS') {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '绑卡申请异常: ' . ($desc ?: $status));
        }

        $bindCardId = $result['bindCardId'] ?? ($result['bind_card_id'] ?? '');
        if (!$bindCardId) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '绑卡申请失败: 未获取到 bindCardId');
        }

        $card = UserBankCard::findOne([
            'user_id' => $userId, 'mall_id' => $this->mall_id,
            'gateway' => $this->gateway, 'bind_card_id' => $bindCardId,
            'is_delete' => 0,
        ]);
        if (!$card) {
            $card = new UserBankCard();
            $card->mall_id = $this->mall_id;
            $card->user_id = $userId;
            $card->gateway = $this->gateway;
            $card->bind_card_id = $bindCardId;
        }
        $card->holder_name = $this->userName;
        $card->mobile = $this->mobile;
        $card->id_type = $this->identityType ?: '00';
        $card->idNo = $this->identityNo;
        $card->id_period_begin = $this->idDocPeriodBegin;
        $card->id_period_end   = $this->idDocPeriodEnd;
        $card->bankCardNo = $this->bankCardNo;
        $card->card_type = $cDFlag;
        $card->validDate = $this->validDate ?: '';
        $card->cvv2 = $this->cvv2 ?: '';
        $card->bank_name = $result['bankName'] ?? '';
        $card->bank_code = $result['bankCode'] ?? '';
        $card->bind_status = UserBankCard::STATUS_PENDING;
        if (!$card->save()) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $card->getErrorMsg());
        }

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '短信验证码已下发', [
            'card_id'      => (int)$card->id,
            'bind_card_id' => $bindCardId,
            'mobile_mask'  => substr($this->mobile, 0, 3) . '****' . substr($this->mobile, -4),
        ]);
    }
}
