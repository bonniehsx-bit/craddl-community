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

namespace app\forms\api\coupon;

use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\models\BaseModel;
use app\models\Coupon;
use app\models\User;
use app\models\UserCoupon;
use app\models\UserCouponTransferLog;
use app\services\CouponService;
use Yii;

class CouponTransferLogForm extends BaseModel
{
    public $page;
    public $page_size;

    public function getLogs()
    {
        $userId = Yii::$app->user->id;
        $mallId = Yii::$app->mall->id;

        $page     = max(1, intval($this->page));
        $pageSize = intval($this->page_size) ?: 10;

        $query = UserCouponTransferLog::find()
            ->where(['mall_id' => $mallId])
            ->andWhere(['or', ['from_user_id' => $userId], ['to_user_id' => $userId]])
            ->orderBy(['created_at' => SORT_DESC]);

        $total = $query->count();
        $logs  = $query->offset(($page - 1) * $pageSize)->limit($pageSize)->all();

        // 批量预加载 UserCoupon 和 User，减少 N+1 查询
        $userCouponIds  = array_unique(array_column($logs, 'user_coupon_id'));
        $counterpartIds = [];
        foreach ($logs as $log) {
            $counterpartIds[] = $log->from_user_id == $userId ? $log->to_user_id : $log->from_user_id;
        }
        $counterpartIds = array_unique($counterpartIds);

        $userCouponsMap = [];
        if ($userCouponIds) {
            $userCoupons = UserCoupon::find()->where(['id' => $userCouponIds])->all();
            foreach ($userCoupons as $uc) {
                $userCouponsMap[$uc->id] = $uc;
            }
        }

        $couponIds = [];
        foreach ($userCouponsMap as $uc) {
            $couponIds[] = $uc->coupon_id;
        }
        $couponIds = array_unique($couponIds);

        $couponsMap = [];
        if ($couponIds) {
            $coupons = Coupon::find()->where(['id' => $couponIds])->all();
            foreach ($coupons as $c) {
                $couponsMap[$c->id] = $c;
            }
        }

        $usersMap = [];
        if ($counterpartIds) {
            $users = User::find()->where(['id' => $counterpartIds])->all();
            foreach ($users as $u) {
                $usersMap[$u->id] = $u;
            }
        }

        $list = [];
        foreach ($logs as $log) {
            $isSent       = $log->from_user_id == $userId;
            $counterpartId = $isSent ? $log->to_user_id : $log->from_user_id;

            $counterpart = $usersMap[$counterpartId] ?? null;
            $userCoupon  = $userCouponsMap[$log->user_coupon_id] ?? null;

            $couponName = '优惠券';
            $couponDesc = '';
            if ($userCoupon) {
                $coupon     = $couponsMap[$userCoupon->coupon_id] ?? null;
                $couponName = $coupon ? $coupon->name : '优惠券';
                $couponDesc = CouponService::getCouponDescription([
                    'type'      => $userCoupon->type,
                    'min_price' => $userCoupon->min_price,
                    'sub_price' => $userCoupon->sub_price,
                    'discount'  => $userCoupon->discount,
                ]);
            }

            $list[] = [
                'id'          => $log->id,
                'type'        => $isSent ? 'sent' : 'received',
                'coupon_name' => $couponName,
                'coupon_desc' => $couponDesc,
                'counterpart' => $counterpart ? [
                    'id'         => $counterpart->id,
                    'nickname'   => $counterpart->nickname,
                    'avatar_url' => $counterpart->avatar_url ?: '',
                ] : [
                    'id'         => 0,
                    'nickname'   => '已注销用户',
                    'avatar_url' => '',
                ],
                'remark'     => $log->remark,
                'created_at' => date('m-d H:i', $log->created_at),
            ];
        }

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, 'ok', [
            'list'      => $list,
            'total'     => (int)$total,
            'page'      => $page,
            'page_size' => $pageSize,
            'has_more'  => ($page * $pageSize) < $total,
        ]);
    }
}
