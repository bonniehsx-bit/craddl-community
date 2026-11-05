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

namespace app\plugins\marketing\forms\api;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Coupon;
use app\models\UserCoupon;
use app\services\CouponService;

/**
 * C 端 - 我的优惠券(展示层由插件承接;数据仍读核心 user_coupon,不重造发放/转赠逻辑)
 */
class MyCouponForm extends BaseModel
{
    public $user_id;
    public $status; // 0未使用 1已使用 2已过期
    public $page;
    public $limit;

    public function rules()
    {
        return [
            [['user_id', 'status', 'page', 'limit'], 'integer'],
        ];
    }

    public function search()
    {
        $now = time();
        $query = UserCoupon::find()
            ->where(['mall_id' => $this->mall_id, 'user_id' => (int)$this->user_id, 'is_delete' => 0]);

        // 状态过滤:未使用需未过期;过期=未用但已过期限
        if ($this->status !== null && $this->status !== '') {
            $st = (int)$this->status;
            if ($st === UserCoupon::STATUS_UNUSE) {
                $query->andWhere(['status' => UserCoupon::STATUS_UNUSE])
                    ->andWhere(['or', ['end_at' => 0], ['>', 'end_at', $now]]);
            } elseif ($st === UserCoupon::STATUS_EXPIRE) {
                $query->andWhere(['or',
                    ['status' => UserCoupon::STATUS_EXPIRE],
                    ['and', ['status' => UserCoupon::STATUS_UNUSE], ['>', 'end_at', 0], ['<=', 'end_at', $now]],
                ]);
            } else {
                $query->andWhere(['status' => $st]);
            }
        }

        $pagination = null;
        $list = $query->orderBy('id DESC')->page($pagination, $this->limit, $this->page)->all();

        // 批量取券名
        $couponIds = array_map(function ($x) { return $x->coupon_id; }, $list);
        $coupons = Coupon::find()->where(['id' => $couponIds])->indexBy('id')->all();

        $rows = [];
        /** @var UserCoupon $uc */
        foreach ($list as $uc) {
            $expired = $uc->status == UserCoupon::STATUS_UNUSE && $uc->end_at > 0 && $uc->end_at <= $now;
            $status = $expired ? UserCoupon::STATUS_EXPIRE : (int)$uc->status;
            $rows[] = [
                'id' => $uc->id,
                'name' => $coupons[$uc->coupon_id]->name ?? '优惠券',
                'desc' => CouponService::getCouponDescription($uc->toArray()),
                'type' => (int)$uc->type,
                'min_price' => (float)$uc->min_price,
                'sub_price' => (float)$uc->sub_price,
                'discount' => (float)$uc->discount,
                'status' => $status,
                'status_text' => [0 => '未使用', 1 => '已使用', 2 => '已过期'][$status] ?? '',
                'start_time' => $uc->start_at ? date('Y.m.d', $uc->start_at) : '',
                'end_time' => $uc->end_at ? date('Y.m.d', $uc->end_at) : '长期有效',
            ];
        }
        return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '', 'data' => ['list' => $rows, 'pagination' => $pagination]];
    }
}
