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

namespace app\forms\api\finance;

use app\core\ApiCode;
use app\helpers\OptionHelper;
use app\helpers\UserHelper;
use app\models\BaseModel;
use app\models\IntegralTransferFeeLog;
use app\models\User;
use Yii;

/**
 * C 端 - 积分转赠(核心旧版,读 cash_setting.other)
 *
 * @deprecated 已有功能相同的插件版 app\plugins\withdraw\forms\api\transfer\IntegralTransferForm
 *   (开关/费率读 withdraw_setting.finance,文案走词库)。本类因老前端仍挂路由而保留,新端走插件版;
 *   两份都已收口到核心积分网关 IntegralService::consume('transfer_out')/grant('transfer_in')。
 */
class IntegralTransferForm extends BaseModel
{
    public $recipient_id; // 收款人用户ID
    public $amount;       // 转赠的积分数量
    public $pay_password; // 支付密码

    public function rules()
    {
        return [
            [['recipient_id', 'amount'], 'required'],
            [['recipient_id'], 'integer'],
            [['amount'], 'number', 'min' => 0.01], // 转赠金额至少为0.01


            // 自定义验证规则
            ['recipient_id', 'validateRecipient'],

        ];
    }

    /**
     * 自定义验证：验证收款人
     */
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

    /**
     * 自定义验证：验证支付密码
     */
    public function validatePayPassword($attribute, $params)
    {
        if ($this->hasErrors()) return;

        $user = UserHelper::getUser();
        if (empty($user->pay_pwd)) {
            $this->addError($attribute, '您尚未设置支付密码');
            return;
        }
        if (UserHelper::authPayPwd($this->pay_password) === false) {
            $this->addError($attribute, '支付密码不正确');
        }
    }

    /**
     * 执行转赠的核心方法
     * @return array
     */
    public function transfer()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // 1. 获取转赠人和收款人模型
        /** @var User $sender */
        $sender = UserHelper::getUser();
        $recipient = User::findOne($this->recipient_id);

        // 2. 获取积分转赠配置
        $setting = OptionHelper::get('cash_setting', Yii::$app->mall->id, []);
        $transferSetting = $setting['other'] ?? [];

        if (empty($transferSetting['is_integral_transfer']) || $transferSetting['is_integral_transfer'] != 1) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => \app\helpers\NounHelper::parse('integral') . \app\helpers\NounHelper::parse('withdraw.transfer_action') . '功能已关闭'];
        }

        // 交易文案后台词库可配(默认=原文):动作词 + 积分词 + 标题
        $actionNoun = \app\helpers\NounHelper::parse('withdraw.transfer_action');
        $integralNoun = \app\helpers\NounHelper::parse('integral');
        $titleNoun = \app\helpers\NounHelper::parse('withdraw.integral_transfer_title');

        // 3. 计算手续费和总扣除额
        $servicePriceRate = (float)($transferSetting['integral_transfer_service_price'] ?? 0);
        $fee = 0;
        if ($servicePriceRate > 0) {
            // 手续费 = 转赠金额 * 手续费比例
            $fee = bcmul($this->amount, bcdiv($servicePriceRate, 100, 4), 2);
        }
        // 总扣除额 = 转赠金额 + 手续费
        $totalDeduction = bcadd($this->amount, $fee, 2);

        // 4. [核心] 检查转出方余额是否足够
        if (bccomp($sender->integral, $totalDeduction, 2) < 0) {
            $errorMsg = "积分余额不足。本次转赠共需 {$totalDeduction} 积分 (转赠 {$this->amount} + 手续费 {$fee})。";
            return ['code' => ApiCode::CODE_FAIL, 'msg' => $errorMsg];
        }

        // 5. [核心] 使用事务执行转账操作，确保原子性
        $transaction = Yii::$app->db->beginTransaction();
        try {
            // a. 从转出方扣除总额
            // 走核心积分网关:业务类型 transfer_out(转出方扣除本金+手续费)
            $subLogId = (new \app\services\IntegralService())->consume(
                $sender->id,
                $totalDeduction,
                'transfer_out',
                [
                    'title'   => $titleNoun,
                    'content' => "{$actionNoun}{$integralNoun}给用户(ID:{$recipient->id}),{$actionNoun}:{$this->amount},手续费:{$fee}",
                ]
            );
            if (!$subLogId) {
                // IntegralLogHelper::sub 内部会抛出异常或返回false，这里再确认一次
                throw new \Exception('扣除转出方积分失败');
            }

            // b. 为接收方增加转赠金额
            // 走核心积分网关:业务类型 transfer_in(接收方入账,不含手续费)
            $addResult = (new \app\services\IntegralService())->grant(
                $recipient->id,
                $this->amount,
                'transfer_in',
                [
                    'title'   => $titleNoun,
                    'content' => "收到来自用户(ID:{$sender->id})的{$integralNoun}{$actionNoun}",
                ]
            );
            if (!$addResult) {
                throw new \Exception('增加收款方积分失败');
            }

            // c. [可选] 记录一笔手续费流水 (如果需要财务对账)
            if ($fee > 0) {
                $feeLog = new IntegralTransferFeeLog();
                $feeLog->mall_id = Yii::$app->mall->id;
                $feeLog->sender_id = $sender->id;
                $feeLog->recipient_id = $recipient->id;
                $feeLog->transfer_amount = $this->amount;
                $feeLog->service_charge_rate = $servicePriceRate;
                $feeLog->fee = $fee;
                $feeLog->integral_log_id = $subLogId; // 关联到扣款的那笔总流水

                if (!$feeLog->save()) {
                    // 如果手续费日志保存失败，也应该回滚整个事务
                    throw new \Exception($this->getErrorMsg($feeLog));
                }
                Yii::warning("积分转赠手续费: {$fee}, from_user: {$sender->id}, to_user: {$recipient->id}");
            }

            $transaction->commit();
            return ['code' => ApiCode::CODE_SUCCESS, 'msg' => $actionNoun . '成功'];

        } catch (\Exception $e) {
            $transaction->rollBack();
            return ['code' => ApiCode::CODE_FAIL, 'msg' => $e->getMessage()];
        }
    }
}