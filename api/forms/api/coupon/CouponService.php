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

use app\models\Coupon;
use app\models\User;
use app\models\UserCoupon;
use app\services\UserCouponTransferLog;
use Yii;

class CouponService
{
    /**
     * 用户领取优惠券的核心逻辑 (前端调用)
     * @param int $couponId
     * @param int $userId
     * @return UserCoupon
     * @throws \Exception
     */
    public function receive(int $couponId, int $userId): UserCoupon
    {
        // 使用事务确保数据一致性
        $transaction = Yii::$app->db->beginTransaction();
        try {
            // 关键：使用 forUpdate() 对优惠券模板加锁，防止并发超发
            $coupon = Coupon::find()
                ->where(['id' => $couponId, 'is_delete' => 0, 'mall_id' => Yii::$app->mall->id])
                ->forUpdate()
                ->one();

            if (!$coupon) {
                throw new \Exception(\app\helpers\NounHelper::parse('coupon') . '不存在');
            }
            if (!$coupon->is_enabled) {
                throw new \Exception(\app\helpers\NounHelper::parse('coupon') . '已失效');
            }
            if (!$coupon->can_be_claimed) {
                throw new \Exception('该' . \app\helpers\NounHelper::parse('coupon') . '无法主动领取');
            }

            // 直接调用 sendToUser 方法，该方法内部已包含所有检查逻辑
            $userCoupon = $this->sendToUser($coupon, $userId);

            $transaction->commit();
            return $userCoupon;

        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e; // 将异常向上抛出，由调用方(Form)处理
        }
    }

    /**
     * 批量为用户发放优惠券 (后台使用)
     * @param Coupon $coupon 要发放的优惠券模型
     * @param array $userIds 用户ID数组
     * @return array 发放结果
     */
    public function sendCoupons(Coupon $coupon, array $userIds): array
    {
        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        // 优化：一次性查询所有有效用户
        $users = User::find()->where(['id' => $userIds, 'is_delete' => 0])->all();

        foreach ($users as $user) {
            try {
                // 注意：批量发放时不应该加锁，否则可能导致长时间锁表
                // sendToUser 方法需要能区分是用户领取还是后台发放
                $this->sendToUser($coupon, $user->id, false); // 传入第三个参数 false 表示不检查总库存
                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = "用户 {$user->id}({$user->nickname}): " . $e->getMessage();
            }
        }

        return [
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'errors' => $errors,
        ];
    }

    /**
     * 为单个用户发放优惠券的核心方法 (可被多种场景复用)
     * @param Coupon $coupon 优惠券模板对象
     * @param int $userId 用户ID
     * @param bool $checkTotalStock 是否检查总库存 (用户主动领取时应为true, 后台批量发放时可为false)
     * @return UserCoupon
     * @throws \Exception
     */
    public function sendToUser(Coupon $coupon, int $userId, bool $checkTotalStock = true): UserCoupon
    {
        // 1. 检查总库存 (根据调用场景决定是否检查)
        if ($checkTotalStock && $coupon->total_count != -1) {
            // 使用 count(*) 进行计数，比 find()->count() 更高效
            $sentCount = UserCoupon::find()->where(['coupon_id' => $coupon->id, 'is_delete' => 0])->count();
            if ($sentCount >= $coupon->total_count) {
                throw new \Exception(\app\helpers\NounHelper::parse('coupon') . '已被领完');
            }
        }

        // 2. 检查每人限领
        if ($coupon->limit_per_user > 0) {
            $userClaimedCount = UserCoupon::find()
                ->where(['user_id' => $userId, 'coupon_id' => $coupon->id, 'is_delete' => 0])
                ->count();
            if ($userClaimedCount >= $coupon->limit_per_user) {
                throw new \Exception('您已达到该' . \app\helpers\NounHelper::parse('coupon') . '的领取上限');
            }
        }

        // 3. 创建并保存用户优惠券记录 (保留了您的数据冗余设计)
        $userCoupon = new UserCoupon();
        $userCoupon->mall_id = $coupon->mall_id;
        $userCoupon->mch_id = $coupon->mch_id;
        $userCoupon->user_id = $userId;
        $userCoupon->coupon_id = $coupon->id;

        // --- 完整复制规则作为数据冗余 ---
        $userCoupon->type = $coupon->type;
        $userCoupon->min_price = $coupon->min_price;
        $userCoupon->sub_price = $coupon->sub_price;
        $userCoupon->discount = $coupon->discount;
        $userCoupon->appoint_type = $coupon->appoint_type;
        $userCoupon->appoint_relation = $coupon->appoint_relation;
        // ---------------------------------

        $userCoupon->status = UserCoupon::STATUS_UNUSE;

        if ($coupon->time_type == 0) { // 领取后N天有效
            $userCoupon->start_at = time();
            // 修正：您的代码中 end_at 逻辑可以优化，strtotime("+{$coupon->day} day") 会从当天的0点开始计算
            // 使用 time() + N * 86400 更精确
            $userCoupon->end_at = $coupon->day > 0 ? (time() + $coupon->day * 86400) : 0;
        } else { // 固定日期范围
            $userCoupon->start_at = $coupon->start_at;
            $userCoupon->end_at = $coupon->end_at;
        }

        if (!$userCoupon->save()) {
            // 抛出更具体的错误信息
            throw new \Exception('保存用户优惠券失败: ' . current($userCoupon->getFirstErrors()));
        }

        return $userCoupon;
    }

    /**
     * 生成优惠券描述文本 (静态方法，方便多处调用)
     * @param array $couponData (可以是 Coupon 或 UserCoupon 的数组)
     * @return string
     */
    public static function getCouponDescription(array $couponData): string
    {
        $minPrice = (float)($couponData['min_price'] ?? 0);
        if ($minPrice > 0) {
            return "满{$minPrice}元可用";
        }
        return '无门槛';
    }

    /**
     * 优惠券转赠核心逻辑
     * @param int $userCouponId 用户持有的优惠券ID (user_coupon表的主键)
     * @param int $fromUserId 转出方用户ID
     * @param int $toUserId 接收方用户ID
     * @return bool
     * @throws \Exception
     */
    public function transfer(int $userCouponId, int $fromUserId, int $toUserId): bool
    {
        if ($fromUserId == $toUserId) {
            throw new \Exception('不能转赠给自己');
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // 1. 查找并锁定该优惠券 (forUpdate防止并发)
            $userCoupon = UserCoupon::find()
                ->where(['id' => $userCouponId, 'user_id' => $fromUserId, 'is_delete' => 0])
                ->forUpdate()
                ->one();

            if (!$userCoupon) {
                throw new \Exception(\app\helpers\NounHelper::parse('coupon') . '不存在或不属于您');
            }

            // 2. 状态检查
            if ($userCoupon->status != UserCoupon::STATUS_UNUSE) {
                throw new \Exception('该' . \app\helpers\NounHelper::parse('coupon') . '已使用或已过期，无法转赠');
            }
            if ($userCoupon->end_at > 0 && $userCoupon->end_at < time()) {
                throw new \Exception('该' . \app\helpers\NounHelper::parse('coupon') . '已过期，无法转赠');
            }

            // 3. 检查接收方是否存在
            $targetUser = User::findOne(['id' => $toUserId, 'mall_id' => Yii::$app->mall->id, 'is_delete' => 0]);
            if (!$targetUser) {
                throw new \Exception('接收方用户不存在');
            }

            // 4. 执行所有权转移
            // 方式一：直接修改归属人 (简单，高效)
            $userCoupon->user_id = $toUserId;
            // 方式二：如果系统要求严格，可以将原券设为已失效/已转赠，给新用户发一张新券。这里采用方式一。

            if (!$userCoupon->save()) {
                throw new \Exception('转赠失败: 保存数据出错');
            }

            // 5. 记录日志
            $log = new UserCouponTransferLog();
            $log->mall_id = Yii::$app->mall->id;
            $log->user_coupon_id = $userCoupon->id;
            $log->from_user_id = $fromUserId;
            $log->to_user_id = $toUserId;
            $log->created_at = time();
            $log->remark = "用户主动转赠";
            if (!$log->save()) {
                throw new \Exception('转赠失败: 保存日志出错');
            }

            $transaction->commit();
            return true;

        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }
}