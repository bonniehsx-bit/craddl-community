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
use app\plugins\withdraw\models\WithdrawOrder;
use app\plugins\withdraw\services\AssetService;

/**
 * C 端 - 我的提现记录（列表 / 详情）
 */
class RecordsForm extends BaseModel
{
    public $page = 1;
    public $limit = 20;
    public $status;
    public $asset_type;
    public $order_no;

    public function rules()
    {
        return [
            [['page', 'limit', 'status'], 'integer'],
            [['asset_type', 'order_no'], 'string'],
            [['page'], 'default', 'value' => 1],
            [['limit'], 'default', 'value' => 20],
        ];
    }

    public function getList()
    {
        /** @var User $user */
        $user = UserHelper::getUser();
        if (!$user) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '请先登录');
        }

        $query = WithdrawOrder::find()
            ->where(['user_id' => $user->id, 'mall_id' => $this->mall_id, 'is_delete' => 0]);

        if ($this->status !== null && $this->status !== '') {
            $query->andWhere(['status' => (int)$this->status]);
        }
        if ($this->asset_type) {
            $query->andWhere(['asset_type' => $this->asset_type]);
        }

        $rows = $query->orderBy('id DESC')->page($pagination, $this->limit, $this->page)->all();
        $list = array_map([$this, 'format'], $rows);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'list' => $list,
            'pagination' => $this->getPaginationInfo($pagination),
        ]);
    }

    public function getDetail()
    {
        /** @var User $user */
        $user = UserHelper::getUser();
        if (!$user) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '请先登录');
        }
        $order = WithdrawOrder::findOne(['order_no' => $this->order_no, 'user_id' => $user->id, 'is_delete' => 0]);
        if (!$order) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '记录不存在');
        }
        $data = $this->format($order);
        $data['payout_info'] = $order->getPayoutInfoList();
        $data['fail_reason'] = $order->fail_reason;
        $data['audit_remark'] = $order->audit_remark;
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', $data);
    }

    private function format(WithdrawOrder $o): array
    {
        return [
            'order_no'      => $o->order_no,
            'channel_name'  => $o->channel_name,
            'asset_type'    => $o->asset_type,
            'asset_label'   => AssetService::label($o->asset_type),
            'amount'        => $o->amount,
            'fee'           => $o->fee,
            'tax'           => $o->tax,
            'actual_amount' => $o->actual_amount,
            'status'        => (int)$o->status,
            'status_text'   => $o->getStatusText(),
            'created_at'    => $o->created_at ? date('Y-m-d H:i:s', (int)$o->created_at) : '',
            'finish_at'     => $o->finish_at ? date('Y-m-d H:i:s', (int)$o->finish_at) : '',
        ];
    }
}
