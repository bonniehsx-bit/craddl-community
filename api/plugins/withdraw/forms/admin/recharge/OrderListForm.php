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

namespace app\plugins\withdraw\forms\admin\recharge;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\User;
use app\plugins\withdraw\models\RechargeOrder;
use app\plugins\withdraw\services\RechargeService;
use Yii;

/**
 * 后台 —— 充值记录（含卡单重发）。
 *
 * 「重新发放」按钮是给极端卡单兜底的：支付成功但结算链被异常打断，
 * 订单已付款而充值单还停在待到账。RechargeService::credit() 自带幂等锁，
 * 运营连点也只会到账一次。
 */
class OrderListForm extends BaseModel
{
    public $id;
    public $page = 1;
    public $limit = 20;
    public $status;
    public $keyword;

    public function rules()
    {
        return [
            [['id', 'page', 'limit'], 'integer'],
            [['status'], 'integer'],
            [['keyword'], 'string', 'max' => 64],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $mallId = (int)Yii::$app->mall->id;

        $query = RechargeOrder::find()->alias('r')
            ->where(['r.mall_id' => $mallId, 'r.is_delete' => 0]);

        if ($this->status !== null && $this->status !== '') {
            $query->andWhere(['r.status' => (int)$this->status]);
        }
        if ($this->keyword) {
            // 订单号精确一点，昵称模糊
            $query->leftJoin(User::tableName() . ' u', 'u.id = r.user_id')
                ->andWhere(['or',
                    ['r.order_no' => $this->keyword],
                    ['like', 'u.nickname', $this->keyword],
                ]);
        }

        $count = (int)$query->count();
        $limit = max(1, min(100, (int)$this->limit));
        $page  = max(1, (int)$this->page);

        $rows = $query->orderBy(['r.id' => SORT_DESC])
            ->offset(($page - 1) * $limit)->limit($limit)->all();

        $userIds = array_unique(array_map(function ($r) { return (int)$r->user_id; }, $rows));
        $users = $userIds
            ? User::find()->where(['id' => $userIds])->indexBy('id')->all()
            : [];

        $list = [];
        foreach ($rows as $r) {
            $u = $users[(int)$r->user_id] ?? null;
            $list[] = [
                'id'          => (int)$r->id,
                'user_id'     => (int)$r->user_id,
                'nickname'    => $u ? (string)$u->nickname : '(已注销)',
                'avatar_url'  => $u ? (string)$u->avatar_url : '',
                'plan_title'  => (string)$r->plan_title,
                'asset_label' => $r->assetLabel(),
                'pay_amount'  => (string)$r->pay_amount,
                'gain_amount' => (string)$r->gain_amount,
                'gift_amount' => (string)$r->gift_amount,
                'total_gain'  => $r->totalGain(),
                'order_no'    => (string)$r->order_no,
                'status'      => (int)$r->status,
                'status_text' => $r->statusText(),
                'created_at'  => $r->created_at ? date('Y-m-d H:i:s', (int)$r->created_at) : '',
                'credited_at' => $r->credited_at ? date('Y-m-d H:i:s', (int)$r->credited_at) : '',
            ];
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'list'  => $list,
            'count' => $count,
        ]);
    }

    /** 卡单重发 */
    public function recredit()
    {
        $mallId = (int)Yii::$app->mall->id;
        // ⚠ 必须带 mall_id：不带的话平台管理员传任意 id 能给别的商城的用户发钱（越权/资损）
        $r = RechargeOrder::findOne(['id' => (int)$this->id, 'mall_id' => $mallId, 'is_delete' => 0]);
        if (!$r) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '充值单不存在');
        }
        if ((int)$r->status === RechargeOrder::STATUS_CREDITED) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '该笔已到账，无需重发');
        }

        try {
            $done = RechargeService::credit((int)$r->order_id);
        } catch (\Throwable $e) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '发放失败：' . $e->getMessage());
        }

        return $done
            ? $this->apiResponse(ApiCode::CODE_SUCCESS, '已发放到账')
            : $this->apiResponse(ApiCode::CODE_FAIL, '未发放（可能已被其他操作处理）');
    }
}
