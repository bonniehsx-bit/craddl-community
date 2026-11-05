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

namespace app\plugins\withdraw\forms\admin\wallet;

use app\core\ApiCode;
use app\helpers\LockHelper;
use app\models\BaseModel;
use app\models\User;
use app\services\WalletRegistry;
use app\services\WalletService;
use Yii;

/**
 * 后台 - 通用钱包手动充值/扣除
 *
 * 经核心 WalletService 原子存取,落 user_wallet + user_wallet_log(后台「钱包流水」可见)。
 * 扣除走 sub() 原子校验余额,不足抛异常(不允许扣成负,避免误操作)。
 */
class WalletAdjustForm extends BaseModel
{
    public $user_id;
    public $wallet_key;
    public $amount;
    public $type;     // 1=增加(充值)  2=扣除
    public $remark;

    public function rules()
    {
        return [
            [['user_id', 'wallet_key', 'amount', 'type'], 'required'],
            [['user_id', 'type'], 'integer'],
            [['wallet_key', 'remark'], 'string'],
            [['amount'], 'number', 'min' => 0.01],
            [['type'], 'in', 'range' => [1, 2], 'message' => '类型只能是增加或扣除'],
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $mallId = (int)Yii::$app->mall->id;

        // 钱包必须是本商城【授权插件】的可提现钱包(防越权调整任意 key)
        $keys = array_column(WalletRegistry::all($mallId), 'key');
        if (!in_array($this->wallet_key, $keys, true)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '无效或未授权的钱包');
        }

        // 用户必须属于本商城
        $user = User::findOne(['id' => (int)$this->user_id, 'mall_id' => $mallId, 'is_delete' => 0]);
        if (!$user) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '用户不存在');
        }

        $amount = number_format((float)$this->amount, 2, '.', '');
        $remark = trim((string)$this->remark) ?: '后台手动调整';

        // 幂等防重:同一(商城/用户/钱包/方向/金额)调整 8s 内只生效一次,挡双击 / 网络重放 / 多标签页
        $lockKey = "wallet:adjust:{$mallId}:{$this->user_id}:{$this->wallet_key}:{$this->type}:{$amount}";
        $lockToken = LockHelper::lock($lockKey, 8);
        if ($lockToken === false) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '操作太频繁,请勿重复提交');
        }

        try {
            if ((int)$this->type === 1) {
                WalletService::add((int)$this->user_id, $this->wallet_key, $amount, $remark, '后台手动充值', 'manual_adjust');
            } else {
                WalletService::sub((int)$this->user_id, $this->wallet_key, $amount, $remark, '后台手动扣除', 'manual_adjust');
            }
        } catch (\Throwable $e) {
            LockHelper::unlock($lockKey, $lockToken); // 失败即释放,允许立即重试
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
        // 成功不释放:留 8s 窗口继续挡重复,到期自动清

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '操作成功');
    }
}
