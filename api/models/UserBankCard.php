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

namespace app\models;

/**
 * 用户快捷支付绑卡记录
 *
 * @property int         $id
 * @property int         $mall_id
 * @property int         $user_id
 * @property string      $gateway           支付网关类 FQN (例: app\payments\FastPayQuickPayment)
 * @property string      $bind_card_id      网关侧返回的绑卡凭证
 * @property string      $holder_name       持卡人姓名(明文)
 * @property string|null $mobile_enc        手机号
 * @property string      $id_type           证件类型 00=身份证
 * @property string|null $id_no_enc         证件号
 * @property string      $id_period_begin   证件有效期开始 yyyyMMdd
 * @property string      $id_period_end     证件有效期结束 yyyyMMdd, 长期=29991231
 * @property string|null $bank_card_no_enc  银行卡号
 * @property string      $bank_card_last4   卡号末4位
 * @property string      $bank_name
 * @property string      $bank_code
 * @property string      $card_type         D储蓄 / C信用
 * @property string|null $valid_date_enc    有效期 YYYYMM
 * @property string|null $cvv2_enc          CVV2
 * @property int         $bind_status       0待验证 1已生效 2已解绑 3失效
 * @property int         $is_default
 * @property string      $remark
 * @property int         $is_delete
 * @property int         $created_at
 * @property int         $updated_at
 *
 * 虚拟字段(透传, 不加密)：mobile / id_no / bank_card_no / valid_date / cvv2
 */
class UserBankCard extends BaseActiveRecord
{
    const STATUS_PENDING  = 0;
    const STATUS_ACTIVE   = 1;
    const STATUS_UNBOUND  = 2;
    const STATUS_INVALID  = 3;

    public static function tableName()
    {
        return '{{%user_bank_card}}';
    }

    public function rules()
    {
        return [
            [['mall_id', 'user_id', 'bind_status', 'is_default', 'is_delete', 'created_at', 'updated_at', 'deleted_at'], 'integer'],
            [['mobile_enc', 'id_no_enc', 'bank_card_no_enc', 'valid_date_enc', 'cvv2_enc'], 'string'],
            [['gateway'], 'string', 'max' => 128],
            [['bind_card_id', 'remark'], 'string', 'max' => 255],
            [['holder_name', 'bank_name'], 'string', 'max' => 64],
            [['bank_card_last4'], 'string', 'max' => 4],
            [['bank_code'], 'string', 'max' => 16],
            [['id_type'], 'string', 'max' => 8],
            [['id_period_begin', 'id_period_end'], 'string', 'max' => 8],
            [['card_type'], 'string', 'max' => 1],
            [['created_time', 'updated_time', 'deleted_time'], 'safe'],
        ];
    }

    public function getMobile(): string
    {
        return (string)($this->getAttribute('mobile_enc') ?? '');
    }

    public function setMobile($value)
    {
        $this->setAttribute('mobile_enc', (string)$value);
    }

    public function getIdNo(): string
    {
        return (string)($this->getAttribute('id_no_enc') ?? '');
    }

    public function setIdNo($value)
    {
        $this->setAttribute('id_no_enc', (string)$value);
    }

    public function getBankCardNo(): string
    {
        return (string)($this->getAttribute('bank_card_no_enc') ?? '');
    }

    public function setBankCardNo($value)
    {
        $value = (string)$value;
        $this->setAttribute('bank_card_no_enc', $value);
        $this->bank_card_last4 = $value === '' ? '' : substr($value, -4);
    }

    public function getValidDate(): string
    {
        return (string)($this->getAttribute('valid_date_enc') ?? '');
    }

    public function setValidDate($value)
    {
        $this->setAttribute('valid_date_enc', (string)$value);
    }

    public function getCvv2(): string
    {
        return (string)($this->getAttribute('cvv2_enc') ?? '');
    }

    public function setCvv2($value)
    {
        $this->setAttribute('cvv2_enc', (string)$value);
    }

    /**
     * 返回一张卡的脱敏展示信息(给前端用)。
     */
    public function toDisplayArray(): array
    {
        return [
            'id'              => (int)$this->id,
            'gateway'         => $this->gateway,
            'bind_card_id'    => $this->bind_card_id,
            'holder_name'     => $this->holder_name,
            'bank_name'       => $this->bank_name,
            'bank_code'       => $this->bank_code,
            'card_type'       => $this->card_type,
            'card_type_label' => $this->card_type === 'C' ? '信用卡' : '储蓄卡',
            'bank_card_last4' => $this->bank_card_last4,
            'mobile_mask'     => $this->maskMobile(),
            'bind_status'     => (int)$this->bind_status,
            'is_default'      => (int)$this->is_default,
            'created_time'    => $this->created_time,
        ];
    }

    private function maskMobile(): string
    {
        $mobile = $this->mobile;
        if (strlen($mobile) !== 11) {
            return $mobile;
        }
        return substr($mobile, 0, 3) . '****' . substr($mobile, -4);
    }

}
