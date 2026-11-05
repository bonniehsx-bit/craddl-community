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

namespace app\plugins\withdraw\forms\api\transfer;

use app\core\ApiCode;
use app\helpers\NounHelper;
use app\helpers\UserHelper;
use app\models\BaseModel;
use app\models\IntegralTransferFeeLog;
use app\models\User;
use app\plugins\withdraw\forms\admin\setting\SettingForm;
use Yii;

/**
 * C 端 - 积分转赠（迁自核心 forms/api/finance/IntegralTransferForm）
 *
 * 开关/手续费率读插件 withdraw_setting.finance（integral_transfer_enabled / integral_transfer_fee_rate）；
 * 积分走核心网关 IntegralService::consume/grant（§22G）；事务内扣款+加款+手续费流水，保证原子性。
 */
class IntegralTransferForm extends BaseModel
{
    public $recipient_id;
    public $amount;
    public $pay_password;

    public function rules()
    {
        return [
            [['recipient_id', 'amount'], 'required'],
            [['recipient_id'], 'integer'],
            [['amount'], 'number', 'min' => 0.01],
            ['recipient_id', 'validateRecipient'],
        ];
    }

    public function validateRecipient($attribute, $params)
    {
        if ($this->hasErrors()) return;
        if ($this->recipient_id == Yii::$app->user->id) {
            $this->addError($attribute, '不能转赠给自己');
            return;
        }
        $recipient = User::findOne(['id' => $this->recipient_id, 'is_delete' => 0]);
        if (!$recipient) {
            $this->addError($attribute, '收款用户不存在');
        }
    }

    public function transfer()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $mallId = (int)Yii::$app->mall->id;

        /** @var User $sender */
        $sender = UserHelper::getUser();
        $recipient = User::findOne($this->recipient_id);

        // 开关 + 手续费率：插件配置为准
        $finance = SettingForm::financeConfig($mallId);
        if (empty($finance['integral_transfer_enabled'])) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => NounHelper::parse('integral') . NounHelper::parse('withdraw.transfer_action') . '功能已关闭'];
        }

        // 支付密码（全局 mall_setting）
        $isTransferPassword = \app\helpers\OptionHelper::get('mall_setting', $mallId, [])['is_transfer_password'] ?? '0';
        if ($isTransferPassword == '1') {
            if (!$this->pay_password) {
                return ['code' => ApiCode::CODE_FAIL, 'msg' => '请输入支付密码！'];
            }
            if (empty($sender->pay_pwd)) {
                return ['code' => ApiCode::CODE_FAIL, 'msg' => '您尚未设置支付密码，请先前往安全设置中心设置！'];
            }
            if (!Yii::$app->getSecurity()->validatePassword($this->pay_password, $sender->pay_pwd)) {
                return ['code' => ApiCode::CODE_FAIL, 'msg' => '支付密码错误！'];
            }
        }

        $actionNoun = NounHelper::parse('withdraw.transfer_action');
        $integralNoun = NounHelper::parse('integral');
        $titleNoun = NounHelper::parse('withdraw.integral_transfer_title');

        // 手续费
        $servicePriceRate = (float)($finance['integral_transfer_fee_rate'] ?? 0);
        $fee = 0;
        if ($servicePriceRate > 0) {
            $fee = bcmul($this->amount, bcdiv($servicePriceRate, 100, 4), 2);
        }
        $totalDeduction = bcadd($this->amount, $fee, 2);

        if (bccomp($sender->integral, $totalDeduction, 2) < 0) {
            $errorMsg = "{$integralNoun}不足。本次{$actionNoun}共需 {$totalDeduction} (含手续费 {$fee})。";
            return ['code' => ApiCode::CODE_FAIL, 'msg' => $errorMsg];
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // 明细走可配文案模板({id}/{amount}/{fee} 数据变量,{integral} 词库联动)
            // 走核心积分网关:业务类型 transfer_out(转出方扣本金+手续费)
            $subLogId = (new \app\services\IntegralService())->consume(
                $sender->id,
                $totalDeduction,
                'transfer_out',
                [
                    'title'   => $titleNoun,
                    'content' => NounHelper::render(NounHelper::parse('withdraw.integral_transfer_out_content'), ['id' => $recipient->id, 'amount' => $this->amount, 'fee' => $fee]),
                ]
            );
            if (!$subLogId) {
                throw new \Exception("扣除转出方{$integralNoun}失败");
            }

            // 走核心积分网关:业务类型 transfer_in(接收方入账,不含手续费)
            $addResult = (new \app\services\IntegralService())->grant(
                $recipient->id,
                $this->amount,
                'transfer_in',
                [
                    'title'   => $titleNoun,
                    'content' => NounHelper::render(NounHelper::parse('withdraw.integral_transfer_in_content'), ['id' => $sender->id]),
                ]
            );
            if (!$addResult) {
                throw new \Exception("增加收款方{$integralNoun}失败");
            }

            if ($fee > 0) {
                $feeLog = new IntegralTransferFeeLog();
                $feeLog->mall_id = $mallId;
                $feeLog->sender_id = $sender->id;
                $feeLog->recipient_id = $recipient->id;
                $feeLog->transfer_amount = $this->amount;
                $feeLog->service_charge_rate = $servicePriceRate;
                $feeLog->fee = $fee;
                $feeLog->integral_log_id = $subLogId;
                if (!$feeLog->save()) {
                    throw new \Exception($this->getErrorMsg($feeLog));
                }
            }

            $transaction->commit();
            return ['code' => ApiCode::CODE_SUCCESS, 'msg' => $actionNoun . '成功'];
        } catch (\Exception $e) {
            $transaction->rollBack();
            return ['code' => ApiCode::CODE_FAIL, 'msg' => $e->getMessage()];
        }
    }
}
