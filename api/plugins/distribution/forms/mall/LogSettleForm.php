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

namespace app\plugins\distribution\forms\mall;

use app\models\BaseModel;
use app\models\CommissionLog;
use app\plugins\distribution\models\DistributionCommissionType;
use app\plugins\distribution\services\DistributionSettleService;

/**
 * 后台手动结算 / 作废单条分销佣金（结算方式=手动 时的操作入口）
 */
class LogSettleForm extends BaseModel
{
    public $log_id;

    public function rules()
    {
        return [
            [['log_id'], 'required'],
            [['log_id'], 'integer'],
        ];
    }

    public function attributeLabels()
    {
        return ['log_id' => '分润记录'];
    }

    public function settle()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        if (!$this->belongsToThisMall()) {
            return ['code' => 1, 'msg' => '记录不存在或无权操作'];
        }

        [$ok, $msg] = (new DistributionSettleService())->settleOne((int) $this->log_id);
        return ['code' => $ok ? 0 : 1, 'msg' => $msg];
    }

    public function invalid()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        if (!$this->belongsToThisMall()) {
            return ['code' => 1, 'msg' => '记录不存在或无权操作'];
        }

        [$ok, $msg] = (new DistributionSettleService())->invalidOne((int) $this->log_id);
        return ['code' => $ok ? 0 : 1, 'msg' => $msg];
    }

    /**
     * 越权闸门：只能操作本商城、且确属分销插件的分润流水。
     * 少了这一层，传个别家商城的 log_id 就能把钱发出去。
     */
    private function belongsToThisMall(): bool
    {
        return CommissionLog::find()
            ->where([
                'id'        => (int) $this->log_id,
                'mall_id'   => \Yii::$app->mall->id,
                'type'      => DistributionCommissionType::DISTRIBUTION,
                'is_delete' => 0,
            ])
            ->exists();
    }
}
