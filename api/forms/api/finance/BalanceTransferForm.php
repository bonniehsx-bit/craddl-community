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
use app\helpers\BalanceLogHelper;
use app\helpers\ResponseHelper;
use app\models\BaseModel;
use app\models\User;
use yii\base\Exception;

class BalanceTransferForm extends BaseModel
{
    public $target_id;
    public $money;
    public $pay_password;

    public function rules()
    {
        return [
            [['target_id'], 'integer'],
            [['money'], 'number', 'min' => 0.01], // 增加最小金额校验
            [['target_id', 'money'], 'required'],
            [['pay_password'], 'string'],
        ];
    }

    /**
     * [优化版] 余额转赠，带有补偿机制
     */
    public function transfer()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // 实时从数据库读取，避免读到 Yii::$app->user->identity 的请求级快照缓存
        \Yii::$app->db->schema->refreshTableSchema(User::tableName());
        $fromUser = User::findOne(\Yii::$app->user->id);
        if (!$fromUser) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '用户信息异常，请重新登录');
        }

        $targetUser = User::findOne($this->target_id);

        // --- 1. 前置校验 ---
        $isTransferPassword = \app\helpers\OptionHelper::get('mall_setting', \Yii::$app->mall->id, [])['is_transfer_password'] ?? '0';
        if ($isTransferPassword == '1') {
            if (!$this->pay_password) {
                return ResponseHelper::send(ApiCode::CODE_FAIL, '请输入支付密码！');
            }
            if (empty($fromUser->pay_pwd)) {
                return ResponseHelper::send(ApiCode::CODE_FAIL, '您尚未设置支付密码，请先前往安全设置中心设置！');
            }
            if (!\Yii::$app->getSecurity()->validatePassword($this->pay_password, $fromUser->pay_pwd)) {
                return ResponseHelper::send(ApiCode::CODE_FAIL, '支付密码错误！');
            }
        }

        if (!$targetUser) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '接收方用户不存在！');
        }
        if ($fromUser->id == $targetUser->id) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '不能给自己转账！');
        }
        // [健壮性] 校验转出方余额是否足够
        if (bccomp($fromUser->money, $this->money, 2) === -1) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '您的余额不足！');
        }

        // --- 2. 尝试扣款 ---
        try {
            // 这一步有自己的事务，成功后钱就被扣了
            BalanceLogHelper::sub(
                $fromUser->id,
                $this->money,
                \app\helpers\NounHelper::parse('withdraw.transfer_to') . '：' . $targetUser->nickname . '(ID:' . $targetUser->id . ')',
                $fromUser->id,
                $targetUser->id,
                \app\helpers\NounHelper::parse('withdraw.balance_transfer_title')
            );
        } catch (\Exception $e) {
            // 如果扣款本身就失败了（例如并发情况下余额不足），直接返回错误
            \Yii::error("余额转账-扣款失败: " . $e->getMessage());
            return ResponseHelper::send(ApiCode::CODE_FAIL, '转账失败：' . $e->getMessage());
        }

        // --- 3. 尝试加款 ---
        try {
            // 这一步也有自己的事务
            BalanceLogHelper::add(
                $targetUser->id,
                $this->money,
                \app\helpers\NounHelper::parse('withdraw.transfer_from') . '：' . $fromUser->nickname . '(ID:' . $fromUser->id . ')',
                $fromUser->id,
                $targetUser->id,
                \app\helpers\NounHelper::parse('withdraw.balance_transfer_title')
            );

            // 如果加款也成功，整个流程完成
            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '转账成功');

        } catch (\Exception $e) {
            // [核心补偿逻辑] 如果加款失败，必须把钱还给转出方！
            \Yii::error("余额转账-加款失败: " . $e->getMessage() . "。即将为用户ID:{$fromUser->id} 执行资金回滚。");

            try {
                // 执行补偿操作
                BalanceLogHelper::add(
                    $fromUser->id,
                    $this->money,
                    '转账失败，资金退回。收款方用户ID：' . $targetUser->id,
                    $fromUser->id,
                    $targetUser->id,
                    '转账失败退款'
                );

                \Yii::warning("用户ID:{$fromUser->id} 的资金已成功回滚。");

            } catch (\Exception $rollbackException) {
                // [灾难性错误] 如果连补偿操作都失败了，必须记录严重错误日志，并通知管理员手动干预！
                $errorMessage = "【！！！严重警告！！！】余额转账出现数据不一致！用户ID:{$fromUser->id} 被扣除 {$this->money} 元后，为收款方 {$targetUser->id} 增加余额失败，并且资金回滚也失败！请立即手动核查并处理！加款失败原因: {$e->getMessage()} | 回滚失败原因: {$rollbackException->getMessage()}";
                \Yii::error($errorMessage);
                // 也可以在这里触发邮件、短信等紧急通知
            }

            // 最终向上层返回加款失败的原始错误信息
            return ResponseHelper::send(ApiCode::CODE_FAIL, '转账失败：' . $e->getMessage());
        }
    }
}