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

namespace app\plugins\marketing\forms\mall;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\User;
use app\models\Coupon;
use app\models\UserCoupon;

class UserCouponListForm extends BaseModel
{
    public $page;
    public $limit;
    public $keyword; // 用于搜索用户昵称或优惠券名称(旧参数,保留兼容)
    public $status;  // 状态筛选
    public $user_keyword;   // 用户:昵称 / 手机号 / 用户ID
    public $coupon_keyword; // 券名称
    public $type;           // 0满减 1折扣
    public $owner;          // platform=平台券 mch=商家券
    public $date_start;     // 领取时间起(Y-m-d)
    public $date_end;       // 领取时间止(Y-m-d)

    public function rules()
    {
        return [
            [['page', 'limit', 'status', 'type'], 'integer'],
            [['user_keyword', 'coupon_keyword'], 'trim'],
            [['user_keyword', 'coupon_keyword'], 'string', 'max' => 60],
            [['owner'], 'in', 'range' => ['platform', 'mch']],
            [['date_start', 'date_end'], 'date', 'format' => 'php:Y-m-d'],
            [['page'], 'default', 'value' => 1],
            [['limit'], 'default', 'value' => 20],
            [['keyword'], 'trim'],
            [['keyword'], 'string'],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // 建立查询：主表是 user_coupon
        $query = UserCoupon::find()
            ->alias('uc')
            ->where(['uc.mall_id' => \Yii::$app->mall->id, 'uc.is_delete' => 0]);

        // 关联查询：我们需要 User 的昵称 和 Coupon 的名称
        $query->joinWith(['user u', 'coupon c']);

        // 关键字搜索：支持搜 用户昵称 或 优惠券名称
        if ($this->keyword) {
            $query->andWhere(['or',
                ['like', 'u.nickname', $this->keyword],
                ['like', 'c.name', $this->keyword]
            ]);
        }

        if ($this->user_keyword !== null && $this->user_keyword !== '') {
            $cond = ['or', ['like', 'u.nickname', $this->user_keyword], ['like', 'u.mobile', $this->user_keyword]];
            if (ctype_digit($this->user_keyword)) {
                $cond[] = ['uc.user_id' => (int)$this->user_keyword];
            }
            $query->andWhere($cond);
        }
        if ($this->coupon_keyword !== null && $this->coupon_keyword !== '') {
            $query->andWhere(['like', 'c.name', $this->coupon_keyword]);
        }
        if ($this->type !== null && $this->type !== '') {
            $query->andWhere(['uc.type' => (int)$this->type]);
        }
        if ($this->owner === 'platform') {
            $query->andWhere(['uc.mch_id' => 0]);
        } elseif ($this->owner === 'mch') {
            $query->andWhere(['>', 'uc.mch_id', 0]);
        }
        if ($this->date_start) {
            $query->andWhere(['>=', 'uc.created_at', strtotime($this->date_start . ' 00:00:00')]);
        }
        if ($this->date_end) {
            $query->andWhere(['<=', 'uc.created_at', strtotime($this->date_end . ' 23:59:59')]);
        }

        // 状态筛选：0未使用 1已使用 2已过期
        // 与下方展示口径一致:未使用但已过有效期的(定时任务还没改状态)算已过期
        if (isset($this->status) && $this->status !== '') {
            $now = time();
            $status = (int)$this->status;
            if ($status === UserCoupon::STATUS_UNUSE) {
                $query->andWhere(['uc.status' => $status])
                    ->andWhere(['or', ['uc.end_at' => 0], ['uc.end_at' => null], ['>=', 'uc.end_at', $now]]);
            } elseif ($status === UserCoupon::STATUS_EXPIRE) {
                $query->andWhere(['or',
                    ['uc.status' => $status],
                    ['and', ['uc.status' => UserCoupon::STATUS_UNUSE], ['>', 'uc.end_at', 0], ['<', 'uc.end_at', $now]],
                ]);
            } else {
                $query->andWhere(['uc.status' => $status]);
            }
        }

        // 排序：按领取时间倒序
        $list = $query->page($pagination, $this->limit, $this->page)
            ->orderBy(['uc.created_at' => SORT_DESC])
            ->select(['uc.*']) // 确保取的是 user_coupon 的字段
            ->with(['user', 'coupon']) // 预加载避免 N+1
            ->all();

        $newList = [];
        foreach ($list as $item) {
            // 格式化有效期显示
            $timeText = '';
            if ($item->start_at && $item->end_at) {
                $timeText = date('Y-m-d H:i', $item->start_at) . ' 至 ' . date('Y-m-d H:i', $item->end_at);
            } else {
                $timeText = '永久有效'; // 理论上 UserCoupon 都有具体时间，这里兜底
            }

            // 状态文案
            $statusText = '未使用';
            if ($item->status == 1) $statusText = '已使用';
            if ($item->status == 2) $statusText = '已过期';
            // 动态判断一下过期（防止定时任务没跑，显示看起来还是未使用的情况）
            if ($item->status == 0 && $item->end_at > 0 && $item->end_at < time()) {
                $statusText = '已过期';
            }

            $newList[] = [
                'id' => $item->id,
                'user_id' => $item->user_id,
                'coupon_name' => $item->coupon ? $item->coupon->name : '优惠券已失效',
                'user_name' => $item->user ? $item->user->nickname : '未知用户',
                'user_avatar' => $item->user ? $item->user->avatar_url : '',
                'type' => $item->type, // 0满减 1折扣
                'min_price' => floatval($item->min_price),
                'sub_price' => floatval($item->sub_price),
                'discount' => floatval($item->discount),
                'time_text' => $timeText,
                'status' => $item->status, // 数据库实际状态
                'status_text' => $statusText, // 前端显示文案
                'created_at' => date('Y-m-d H:i:s', $item->created_at),
                'used_at' => $item->used_at ? date('Y-m-d H:i:s', $item->used_at) : '-',
                'is_transferable' => $item->is_transferable
            ];
        }

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => '获取成功',
            'data' => [
                'list' => $newList,
                'pagination' => $pagination
            ]
        ];
    }
}