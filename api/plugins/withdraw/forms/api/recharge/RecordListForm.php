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

namespace app\plugins\withdraw\forms\api\recharge;

use app\core\ApiCode;
use app\helpers\UserHelper;
use app\models\BaseModel;
use app\plugins\withdraw\models\RechargeOrder;
use Yii;

/**
 * C 端 —— 我的充值记录。
 *
 * 默认只给「已到账」的：待支付单对用户没有意义（他要么去付了，要么放弃了），
 * 混在记录里只会让人以为"充了没到账"。运营排查用后台看全量。
 */
class RecordListForm extends BaseModel
{
    public $page = 1;
    public $limit = 15;
    /** 传 1 则连待支付的一起返回 */
    public $include_pending = 0;

    public function rules()
    {
        return [
            [['page', 'limit', 'include_pending'], 'integer'],
        ];
    }

    public function search()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $query = RechargeOrder::find()
            ->where([
                'mall_id'   => (int)Yii::$app->mall->id,
                'user_id'   => (int)UserHelper::getMyId(),
                'is_delete' => 0,
            ]);
        if (!$this->include_pending) {
            $query->andWhere(['status' => RechargeOrder::STATUS_CREDITED]);
        }

        $limit = max(1, min(50, (int)$this->limit));
        $page  = max(1, (int)$this->page);
        $count = (int)$query->count();

        $rows = $query->orderBy(['id' => SORT_DESC])
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->all();

        $list = [];
        foreach ($rows as $r) {
            $list[] = [
                'id'          => (int)$r->id,
                'plan_title'  => (string)$r->plan_title,
                'asset_label' => $r->assetLabel(),
                'pay_amount'  => (string)$r->pay_amount,
                'total_gain'  => $r->totalGain(),
                'gift_amount' => (string)$r->gift_amount,
                'status'      => (int)$r->status,
                'status_text' => $r->statusText(),
                'order_no'    => (string)$r->order_no,
                'created_at'  => date('Y-m-d H:i', (int)$r->created_at),
                'credited_at' => $r->credited_at ? date('Y-m-d H:i', (int)$r->credited_at) : '',
            ];
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'list'  => $list,
            'count' => $count,
            'page'  => $page,
        ]);
    }
}
