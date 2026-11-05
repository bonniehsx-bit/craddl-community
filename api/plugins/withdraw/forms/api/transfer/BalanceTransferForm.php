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
use app\helpers\BalanceLogHelper;
use app\helpers\NounHelper;
use app\helpers\OptionHelper;
use app\helpers\ResponseHelper;
use app\models\BaseModel;
use app\models\User;
use app\plugins\withdraw\forms\admin\setting\SettingForm;

/**
 * C 端 - 余额转赠（迁自核心 forms/api/finance/BalanceTransferForm）
 *
 * 与老版区别：开关读插件 withdraw_setting.finance.balance_transfer_enabled（唯一权威源）；
 * 资金动作仍复用核心 BalanceLogHelper（§8，不另造钱包），扣款/加款各自事务 + 加款失败补偿回滚。
 * 支付密码沿用全局 mall_setting.is_transfer_password。
 */
class BalanceTransferForm extends BaseModel
{
    public $target_id;
    public $money;
    public $pay_password;

    public function rules()
    {
        return [
            [['target_id'], 'integer'],
            [['money'], 'number', 'min' => 0.01],
            [['target_id', 'money'], 'required'],
            [['pay_password'], 'string'],
        ];
    }

    public function transfer()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $mallId = (int)\Yii::$app->mall->id;

        // 开关：插件配置为准
        $finance = SettingForm::financeConfig($mallId);
        if (empty($finance['balance_transfer_enabled'])) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, NounHelper::parse('balance') . NounHelper::parse('withdraw.transfer_action') . '功能已关闭');
        }

        // 实时读库，避免读到请求级快照缓存
        \Yii::$app->db->schema->refreshTableSchema(User::tableName());
        $fromUser = User::findOne(\Yii::$app->user->id);
        if (!$fromUser) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '用户信息异常，请重新登录');
        }
        $targetUser = User::findOne($this->target_id);

        // 支付密码（全局 mall_setting）
        $isTransferPassword = OptionHelper::get('mall_setting', $mallId, [])['is_transfer_password'] ?? '0';
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
        if (bccomp($fromUser->money, $this->money, 2) === -1) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '您的余额不足！');
        }

        // 流水标题/明细走可配文案模板({nickname}/{id} 数据变量,标题词库联动)
        $title = NounHelper::parse('withdraw.balance_transfer_title');

        // 扣款（独立事务）
        try {
            BalanceLogHelper::sub(
                $fromUser->id,
                $this->money,
                NounHelper::render(NounHelper::parse('withdraw.balance_transfer_out_content'), ['nickname' => $targetUser->nickname, 'id' => $targetUser->id]),
                $fromUser->id,
                $targetUser->id,
                $title
            );
        } catch (\Exception $e) {
            \Yii::error('余额转账-扣款失败: ' . $e->getMessage());
            return ResponseHelper::send(ApiCode::CODE_FAIL, '转账失败：' . $e->getMessage());
        }

        // 加款（独立事务），失败则补偿回滚扣款
        try {
            BalanceLogHelper::add(
                $targetUser->id,
                $this->money,
                NounHelper::render(NounHelper::parse('withdraw.balance_transfer_in_content'), ['nickname' => $fromUser->nickname, 'id' => $fromUser->id]),
                $fromUser->id,
                $targetUser->id,
                $title
            );
            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '转账成功');
        } catch (\Exception $e) {
            \Yii::error("余额转账-加款失败: {$e->getMessage()}。即将为用户ID:{$fromUser->id} 执行资金回滚。");
            try {
                BalanceLogHelper::add(
                    $fromUser->id,
                    $this->money,
                    NounHelper::render(NounHelper::parse('withdraw.transfer_refund_content'), ['id' => $targetUser->id]),
                    $fromUser->id,
                    $targetUser->id,
                    NounHelper::parse('withdraw.transfer_refund_title')
                );
                \Yii::warning("用户ID:{$fromUser->id} 的资金已成功回滚。");
            } catch (\Exception $rollbackException) {
                \Yii::error("【！！！严重警告！！！】余额转账数据不一致！用户ID:{$fromUser->id} 被扣 {$this->money} 元后为收款方 {$targetUser->id} 加款失败且回滚亦失败！加款失败:{$e->getMessage()} | 回滚失败:{$rollbackException->getMessage()}");
            }
            return ResponseHelper::send(ApiCode::CODE_FAIL, '转账失败：' . $e->getMessage());
        }
    }
}
