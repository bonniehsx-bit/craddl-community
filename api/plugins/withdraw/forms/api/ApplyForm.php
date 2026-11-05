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

namespace app\plugins\withdraw\forms\api;

use app\core\ApiCode;
use app\helpers\UserHelper;
use app\models\BaseModel;
use app\models\User;
use app\plugins\withdraw\models\Channel;
use app\plugins\withdraw\services\WithdrawService;

/**
 * C 端 - 提交提现申请
 *
 * 仅做基础格式校验；金融硬防线（资格/限额/倍数/余额/防重/扣减）全部由 WithdrawService 在后端重做。
 */
class ApplyForm extends BaseModel
{
    public $channel_id;
    public $asset_type;
    public $amount;
    public $account; // array：收款账户原始字段（bank/alipay 等需要）+ 可含 remark

    public function rules()
    {
        return [
            [['channel_id', 'asset_type', 'amount'], 'required'],
            [['channel_id'], 'integer'],
            [['amount'], 'number'],
            [['asset_type'], 'string'],
            [['account'], 'safe'],
        ];
    }

    public function submit()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        /** @var User $user */
        $user = UserHelper::getUser();
        if (!$user) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '请先登录');
        }

        // 逐用户可见性硬拦截:扩展钱包对该用户不可见时,禁止提现(防绕过入口直接 POST apply 隐藏钱包)
        if (\app\services\WalletRegistry::exists((string)$this->asset_type)
            && !\app\services\WalletRegistry::isVisible((string)$this->asset_type, (int)$user->id)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '该资金来源不可提现');
        }

        $channel = Channel::findOne([
            'id' => $this->channel_id,
            'mall_id' => $this->mall_id,
            'is_delete' => 0,
        ]);
        if (!$channel) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '提现渠道不存在');
        }

        $account = is_array($this->account) ? $this->account : [];

        try {
            $service = new WithdrawService();
            $order = $service->apply($user, (int)$this->mall_id, $channel, (string)$this->asset_type, $this->amount, $account);
        } catch (\Throwable $e) {
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '提现申请已提交', [
            'order_no'      => $order->order_no,
            'status'        => (int)$order->status,
            'status_text'   => $order->getStatusText(),
            'amount'        => $order->amount,
            'fee'           => $order->fee,
            'tax'           => $order->tax,
            'actual_amount' => $order->actual_amount,
        ]);
    }
}
