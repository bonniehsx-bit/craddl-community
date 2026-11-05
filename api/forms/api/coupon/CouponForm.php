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

// 引入统一响应助手
use app\models\BaseModel;
use app\models\Coupon;
use app\models\Goods;
use app\models\UserCoupon;
use app\services\CouponService;

// 引入服务层
use Yii;

class CouponForm extends BaseModel
{
    public $page;
    public $limit;
    public $coupon_id;
    public $status;
    public $goods_id;

    public function rules()
    {
        return [
            [['coupon_id', 'page', 'limit', 'status', 'goods_id'], 'integer'],
            [['limit'], 'default', 'value' => 20],
            [['page'], 'default', 'value' => 1],
            ['status', 'in', 'range' => [0, 1, 2], 'message' => '无效的状态值'],
        ];
    }

    /**
     * 领取优惠券
     */
    public function receive()
    {
        if (!$this->validate(['coupon_id'])) {
            return $this->responseErrorInfo();
        }

        try {
            // 将核心逻辑委托给服务层处理
            $couponService = new CouponService();
            $couponService->receive($this->coupon_id, Yii::$app->user->id);

            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '领取成功');
        } catch (\Exception $e) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }

    /**
     * 获取我的优惠券列表
     */
    public function getUserCouponList()
    {
        if (!$this->validate(['status', 'page', 'limit'])) {
            return $this->responseErrorInfo();
        }

        $query = UserCoupon::find()
            ->with(['mch'])
            ->where([
                'uc.mall_id' => Yii::$app->mall->id,
                'uc.user_id' => Yii::$app->user->id,
                'uc.is_delete' => 0,
            ])->alias('uc')->innerJoinWith('coupon c'); // 使用 INNER JOIN 确保优惠券模板存在

        if ($this->status !== null) {
            $query->andWhere(['uc.status' => $this->status]);
        }

        // 强制刷新状态，处理已过期的优惠券
        UserCoupon::updateAll(
            ['status' => UserCoupon::STATUS_EXPIRE],
            ['and', ['user_id' => Yii::$app->user->id], ['status' => UserCoupon::STATUS_UNUSE], ['<', 'end_at', time()]]
        );

        $list = $query->select([
            'uc.id',
            'uc.status',
            'uc.start_at',
            'uc.end_at',
            'c.name',
            'uc.type',
            'uc.min_price',
            'uc.sub_price',
            'uc.discount',
            'uc.appoint_type',
            'uc.appoint_relation',
            'uc.mch_id',
            'uc.is_transferable'
        ])
            ->page($pagination, $this->limit, $this->page)
            ->orderBy('uc.status ASC, uc.end_at ASC, uc.id DESC')
            ->asArray()
            ->all();

        foreach ($list as &$item) {
            // 格式化数据，提供更友好的字段
            $item['start_at_text'] = date('Y.m.d', $item['start_at']);
            $item['end_at_text'] = date('Y.m.d', $item['end_at']);
            $item['description'] = CouponService::getCouponDescription($item);
            $item['is_expired'] = $item['end_at'] < time();
        }

        $data = [
            'list' => $list,
            'pagination' => $this->getPaginationInfo($pagination)
        ];

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '获取成功', $data);
    }

    /**
     * 获取领券中心可领取的优惠券列表
     */
    public function getCouponList()
    {
        if (!$this->validate(['goods_id', 'page', 'limit'])) {
            return $this->responseErrorInfo();
        }

        $query = Coupon::find()
            ->with(['mch'])
            ->where([
                'mall_id' => Yii::$app->mall->id,
                'is_delete' => 0,
                'is_enabled' => 1,
                'can_be_claimed' => 1,
            ])->andWhere(['or', ['total_count' => -1], ['>', 'total_count', 0]]); // -1为不限量

        // 如果传入了商品ID，则筛选该商品可用的优惠券
        if ($this->goods_id) {
            $goods = Goods::findOne($this->goods_id);
            if ($goods) {
                $catIds = array_column($goods->getCatList(), 'id');
                $catCondition = empty($catIds) ? '0=1' : "JSON_CONTAINS(appoint_relation, '" . json_encode($catIds) . "')";

                $query->andWhere([
                    'or',
                    ['appoint_type' => 'all'],
                    ['and', ['appoint_type' => 'goods'], "JSON_CONTAINS(appoint_relation, '\"{$this->goods_id}\"')"],
                    ['and', ['appoint_type' => 'cat'], $catCondition]
                ]);
            }
        }

        $list = $query->page($pagination, $this->limit, $this->page)
            ->orderBy('id DESC')
            ->asArray()
            ->all();

        if (!empty($list)) {
            $couponIds = array_column($list, 'id');

            // 优化N+1查询：一次性查出所有相关优惠券的用户领取数量和总发放数量
            $userClaimedCounts = UserCoupon::find()
                ->where(['user_id' => Yii::$app->user->id, 'coupon_id' => $couponIds, 'is_delete' => 0])
                ->select('coupon_id, COUNT(id) as count')
                ->groupBy('coupon_id')
                ->asArray()
                ->all();
            $userClaimedMap = array_column($userClaimedCounts, 'count', 'coupon_id');

            $totalSentCounts = UserCoupon::find()
                ->where(['coupon_id' => $couponIds, 'is_delete' => 0])
                ->select('coupon_id, COUNT(id) as count')
                ->groupBy('coupon_id')
                ->asArray()
                ->all();
            $totalSentMap = array_column($totalSentCounts, 'count', 'coupon_id');

            foreach ($list as &$item) {
                $userClaimedCount = $userClaimedMap[$item['id']] ?? 0;
                $totalSentCount = $totalSentMap[$item['id']] ?? 0;

                $item['is_claimed'] = $userClaimedCount > 0;

                $isStockOut = ($item['total_count'] != -1 && $totalSentCount >= $item['total_count']);
                $isLimitReached = ($item['limit_per_user'] > 0 && $userClaimedCount >= $item['limit_per_user']);

                $item['can_claim_more'] = !$isStockOut && !$isLimitReached;
                $item['description'] = CouponService::getCouponDescription($item);
            }
        }

        $data = [
            'list' => $list,
            'pagination' => $this->getPaginationInfo($pagination)
        ];

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '获取成功', $data);
    }
}
