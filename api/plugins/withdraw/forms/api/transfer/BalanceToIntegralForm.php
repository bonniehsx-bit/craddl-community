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
use app\helpers\ResponseHelper;
use app\helpers\UserHelper;
use app\models\BaseModel;
use app\models\User;
use app\plugins\withdraw\forms\admin\setting\SettingForm;
use Yii;

/**
 * C 端 - 余额转积分（迁自核心 forms/api/finance/BalanceToIntegralForm）
 *
 * 开关/兑换比走插件 withdraw_setting.finance（balance_to_integral_enabled / one_rmb_to_integral）；
 * 余额走 BalanceLogHelper，积分走核心网关 IntegralService::grant（§22G）。
 * 修正老版缺陷：老 Form 读 $setting['is_balance_to_integral']（实际存于 other 下且未判 ==1），此处以 finance 为准。
 */
class BalanceToIntegralForm extends BaseModel
{
    public $balance;

    public function rules()
    {
        return [
            [['balance'], 'required'],
            [['balance'], 'number', 'min' => 0.01],
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $mallId = (int)Yii::$app->mall->id;
        $finance = SettingForm::financeConfig($mallId);

        $balanceNoun = NounHelper::parse('balance');
        $integralNoun = NounHelper::parse('integral');

        if (empty($finance['balance_to_integral_enabled'])) {
            return $this->apiResponse(ApiCode::CODE_FAIL, "{$balanceNoun}兑换{$integralNoun}功能已关闭");
        }

        $rate = (float)($finance['one_rmb_to_integral'] ?? 0);
        if ($rate <= 0) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '兑换比例未配置');
        }

        // 实时读库校验余额
        $user = User::findOne(UserHelper::getMyId());
        if (!$user) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '用户信息异常，请重新登录');
        }
        if (bccomp($user->money, $this->balance, 2) < 0) {
            return $this->apiResponse(ApiCode::CODE_FAIL, "{$balanceNoun}不足");
        }

        $totalIntegral = bcmul($this->balance, (string)$rate, 2);
        if (bccomp($totalIntegral, '0', 2) <= 0) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '兑换失败');
        }

        // 明细走可配文案模板({gain}兑得积分/{used}使用余额 数据变量,{integral}/{balance} 词库联动)
        // 🐛 补事务:原先扣余额与加积分各自独立,扣款成功后加分抛异常会导致余额没了积分没到(资损)
        $userId = UserHelper::getMyId();
        $tx = \Yii::$app->db->beginTransaction();
        try {
            BalanceLogHelper::sub($userId, $this->balance, NounHelper::render(NounHelper::parse('withdraw.b2i_out_content'), ['gain' => $totalIntegral]));
            // 走核心积分网关:业务类型 balance_to_integral
            (new \app\services\IntegralService())->grant($userId, $totalIntegral, 'balance_to_integral', [
                'title'   => '余额兑换积分',
                'content' => NounHelper::render(NounHelper::parse('withdraw.b2i_in_content'), ['used' => $this->balance]),
            ]);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            return ResponseHelper::send(ApiCode::CODE_FAIL, $e->getMessage());
        }
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '兑换成功');
    }
}
