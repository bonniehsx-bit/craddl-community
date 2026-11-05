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

namespace app\plugins\withdraw\forms\admin\channel;

use app\core\ApiCode;
use app\models\BaseModel;
use app\plugins\withdraw\models\Channel;
use app\plugins\withdraw\models\WithdrawOrder;
use Yii;

/**
 * 提现渠道 - 删除（软删）
 *
 * 安全约束：渠道下若存在"未完成"的提现单，禁止删除，避免历史单失去渠道上下文。
 */
class ChannelDeleteForm extends BaseModel
{
    public $id;

    public function rules()
    {
        return [
            [['id'], 'required'],
            [['id'], 'integer'],
        ];
    }

    public function delete()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $mallId = (int)Yii::$app->mall->id;
        $channel = Channel::findOne(['id' => $this->id, 'mall_id' => $mallId, 'is_delete' => 0]);
        if (!$channel) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '渠道不存在');
        }

        // 存在进行中的提现单则拦截
        $pending = WithdrawOrder::find()
            ->where(['channel_id' => $channel->id, 'is_delete' => 0])
            ->andWhere(['in', 'status', [
                WithdrawOrder::STATUS_PENDING_AUDIT,
                WithdrawOrder::STATUS_PENDING_PAYOUT,
                WithdrawOrder::STATUS_PAYING,
            ]])
            ->exists();
        if ($pending) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '该渠道下存在进行中的提现单，无法删除');
        }

        $channel->is_delete = 1;
        $channel->deleted_at = time();
        if (!$channel->save(false)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '删除失败');
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '删除成功');
    }
}
