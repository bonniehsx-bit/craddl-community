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

namespace app\plugins\withdraw\controllers\api;

use app\controllers\api\filters\LoginFilter;
use app\core\ApiCode;
use app\helpers\OptionHelper;
use app\plugins\ApiController;
use app\plugins\withdraw\forms\admin\setting\SettingForm;
use app\plugins\withdraw\forms\api\transfer\BalanceToIntegralForm;
use app\plugins\withdraw\forms\api\transfer\BalanceTransferForm;
use app\plugins\withdraw\forms\api\transfer\IntegralTransferForm;
use Yii;

/**
 * C 端 - 转赠（余额 / 积分）
 *
 * 路由：plugin/withdraw/api/transfer/{action}
 *   - config    转赠开关/手续费/是否需支付密码（转赠页初始化读）
 *   - balance   余额转赠
 *   - integral  积分转赠
 *
 * 资金动作复用核心 Balance/IntegralLogHelper（§8），配置走 withdraw_setting.finance。
 */
class TransferController extends ApiController
{
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'login' => ['class' => LoginFilter::class],
        ]);
    }

    /** 转赠配置（页面初始化）：开关 + 手续费率 + 是否需支付密码 */
    public function actionConfig()
    {
        $mallId = (int)Yii::$app->mall->id;
        $finance = SettingForm::financeConfig($mallId);
        // 审核版(小程序 / App 提审中的版本号)不开放转赠、转积分入口,与首页、个人中心的审核版同一个判断
        if (\app\helpers\VersionHelper::checkIsAuditMode($mallId)) {
            $finance['balance_transfer_enabled'] = 0;
            $finance['integral_transfer_enabled'] = 0;
            $finance['balance_to_integral_enabled'] = 0;
        }
        $isTransferPassword = (int)((OptionHelper::get('mall_setting', $mallId, [])['is_transfer_password'] ?? 0) == 1);
        return $this->asJson([
            'code' => ApiCode::CODE_SUCCESS,
            'msg'  => '',
            'data' => [
                'balance_transfer_enabled'    => (int)$finance['balance_transfer_enabled'],
                'integral_transfer_enabled'   => (int)$finance['integral_transfer_enabled'],
                'integral_transfer_fee_rate'  => (float)$finance['integral_transfer_fee_rate'],
                'balance_to_integral_enabled' => (int)$finance['balance_to_integral_enabled'],
                'one_rmb_to_integral'         => (float)$finance['one_rmb_to_integral'],
                'hide_balance_detail'         => (int)$finance['hide_balance_detail'],
                'is_transfer_password'        => $isTransferPassword,
            ],
        ]);
    }

    /** 余额转赠 */
    public function actionBalance()
    {
        $form = new BalanceTransferForm();
        $form->attributes = Yii::$app->request->post();
        return $this->asJson($form->transfer());
    }

    /** 积分转赠 */
    public function actionIntegral()
    {
        $form = new IntegralTransferForm();
        $form->attributes = Yii::$app->request->post();
        return $this->asJson($form->transfer());
    }

    /** 余额转积分 */
    public function actionBalanceToIntegral()
    {
        $form = new BalanceToIntegralForm();
        $form->attributes = Yii::$app->request->post();
        return $this->asJson($form->save());
    }
}
