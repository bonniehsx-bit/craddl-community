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

namespace app\services;

use app\models\Coupon;
use app\models\User;
use app\models\UserCoupon;
use app\models\UserCouponTransferLog;
use Yii;

class CouponService
{
    // 定义发放来源常量
    const SOURCE_UNKNOWN = 0;          // 未知/旧数据
    const SOURCE_USER_CLAIM = 1;       // 用户主动领取 (领券中心/商品详情)
    const SOURCE_ADMIN_SEND = 2;       // 后台管理员发放
    const SOURCE_VOUCHER_EXCHANGE = 3; // 礼品券包兑换
    const SOURCE_LOTTERY = 4;          // 抽奖获取 (预留)
    const SOURCE_MARKETING = 5;        // 营销插件自动发放 (触发/群发)

    /**
     * [核心新增] 批量发放多种优惠券 (适配券包兑换场景)
     * 给【同一个用户】一次性发放【多种、多张】优惠券
     * * @param int $userId 用户ID
     * @param array $couponList 结构示例: [['coupon_id' => 1, 'num' => 2], ['coupon_id' => 5, 'num' => 1]]
     * @param int $sourceType 来源类型
     * @return array 成功发放的 UserCoupon 对象数组
     * @throws \Exception
     */
    public function sendBatchToUser(int $userId, array $couponList, int $sourceType = self::SOURCE_VOUCHER_EXCHANGE): array
    {
        $sentCoupons = [];

        // 开启事务 (非常重要：券包兑换要么全部成功，要么全部失败回滚)
        $transaction = Yii::$app->db->beginTransaction();

        try {
            foreach ($couponList as $item) {
                $couponId = intval($item['coupon_id']);
                $num = intval($item['num']);

                if ($couponId <= 0 || $num <= 0) {
                    continue;
                }

                // 1. 查出模板 (需检查 mall_id 防止越权)
                $coupon = Coupon::findOne([
                    'id' => $couponId,
                    'mall_id' => Yii::$app->mall->id,
                    'is_delete' => 0
                ]);

                if (!$coupon) {
                    // 策略：如果是付费购买的券包，配置的券不存在属于严重配置错误，应报错中断
                    throw new \Exception("配置异常：优惠券ID {$couponId} 不存在或已删除");
                }

                // 2. 循环发放指定数量
                for ($i = 0; $i < $num; $i++) {
                    // 调用底层发放方法
                    // $checkTotalStock = false: 券包兑换通常视为已锁定权益(或虚拟库存)，不受普通库存限制
                    // $checkUserLimit = false: 付费购买的权益通常不受"每人限领1张"的限制
                    $userCoupon = $this->sendToUser($coupon, $userId, false, false, $sourceType);
                    $sentCoupons[] = $userCoupon;
                }
            }

            $transaction->commit();
            return $sentCoupons;

        } catch (\Exception $e) {
            $transaction->rollBack();
            // 抛出异常由上层控制器捕获处理
            throw $e;
        }
    }

    /**
     * 用户主动领取优惠券 (前端调用)
     * @param int $couponId
     * @param int $userId
     * @return UserCoupon
     * @throws \Exception
     */
    public function receive(int $couponId, int $userId): UserCoupon
    {
        // 开启事务
        $transaction = Yii::$app->db->beginTransaction();
        try {
            // 关键：使用 forUpdate() 对优惠券模板加锁，防止高并发超发
            $coupon = Coupon::find()
                ->where(['id' => $couponId, 'is_delete' => 0, 'mall_id' => Yii::$app->mall->id])
                ->forUpdate()
                ->one();

            if (!$coupon) {
                throw new \Exception(\app\helpers\NounHelper::parse('coupon') . '不存在');
            }
            if (!$coupon->is_enabled) {
                throw new \Exception(\app\helpers\NounHelper::parse('coupon') . '已失效/未启用');
            }
            if (!$coupon->can_be_claimed) {
                throw new \Exception('该' . \app\helpers\NounHelper::parse('coupon') . '不支持主动领取');
            }

            // 调用底层方法：检查总库存(true)，检查个人限领(true)，标记来源为主动领取
            $userCoupon = $this->sendToUser($coupon, $userId, true, true, self::SOURCE_USER_CLAIM);

            $transaction->commit();
            return $userCoupon;

        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * 批量为多个用户发放同一张优惠券 (后台发放)
     * @param Coupon $coupon
     * @param array $userIds
     * @return array
     */
    public function sendCoupons(Coupon $coupon, array $userIds): array
    {
        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        // 一次性查询所有有效用户
        $users = User::find()->where(['id' => $userIds, 'is_delete' => 0])->all();

        foreach ($users as $user) {
            try {
                // 后台发放通常不检查总库存(false)，不检查个人限领(false)，标记来源为后台发放
                $this->sendToUser($coupon, $user->id, false, false, self::SOURCE_ADMIN_SEND);
                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = "用户ID {$user->id}: " . $e->getMessage();
            }
        }

        return [
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'errors' => $errors,
        ];
    }

    /**
     * [底层核心] 为单个用户发放一张优惠券
     * 包含：快照复制、库存检查、限领检查、时间计算
     * * @param Coupon $coupon 优惠券模板对象
     * @param int $userId 用户ID
     * @param bool $checkTotalStock 是否检查总库存 (用户领取:true, 后台/兑换:false)
     * @param bool $checkUserLimit 是否检查每人限领 (用户领取:true, 后台/兑换:false)
     * @param int $sourceType 来源类型
     * @return UserCoupon
     * @throws \Exception
     */
    public function sendToUser(Coupon $coupon, int $userId, bool $checkTotalStock = true, bool $checkUserLimit = true, int $sourceType = self::SOURCE_UNKNOWN): UserCoupon
    {
        // 1. 检查总库存
        if ($checkTotalStock && $coupon->total_count != -1) {
            // 使用 count(*) 比查询实体更高效
            $sentCount = UserCoupon::find()->where(['coupon_id' => $coupon->id, 'is_delete' => 0])->count();
            if ($sentCount >= $coupon->total_count) {
                throw new \Exception(\app\helpers\NounHelper::parse('coupon') . "[{$coupon->name}]已被领完");
            }
        }

        // 2. 检查每人限领
        if ($checkUserLimit && $coupon->limit_per_user > 0) {
            $userClaimedCount = UserCoupon::find()
                ->where(['user_id' => $userId, 'coupon_id' => $coupon->id, 'is_delete' => 0])
                ->count();
            if ($userClaimedCount >= $coupon->limit_per_user) {
                throw new \Exception('您已达到' . \app\helpers\NounHelper::parse('coupon') . "[{$coupon->name}]的领取上限");
            }
        }

        // 3. 实例化 UserCoupon 并填充基础信息
        $userCoupon = new UserCoupon();
        $userCoupon->mall_id = $coupon->mall_id;
        $userCoupon->mch_id = $coupon->mch_id; // 支持商家券
        $userCoupon->user_id = $userId;
        $userCoupon->coupon_id = $coupon->id;

        // 4. [数据快照] 完整复制规则，防止模板修改影响已发优惠券
        $userCoupon->type = $coupon->type;
        $userCoupon->min_price = $coupon->min_price;
        $userCoupon->sub_price = $coupon->sub_price;
        $userCoupon->discount = $coupon->discount;
        $userCoupon->appoint_type = $coupon->appoint_type;
        $userCoupon->appoint_relation = $coupon->appoint_relation;

        // 复制新字段 (根据之前迁移文件添加的字段)
        $userCoupon->is_transferable = $coupon->is_transferable;

        // 记录来源 (建议在 user_coupon 表添加 receive_type 字段，如果没有该字段请注释掉下面这行)
        if ($userCoupon->hasAttribute('receive_type')) {
            $userCoupon->receive_type = $sourceType;
        }

        // 5. 设置状态和时间
        $userCoupon->status = UserCoupon::STATUS_UNUSE;

        if ($coupon->time_type == 1) {
            // 固定日期范围
            $userCoupon->start_at = $coupon->start_at;
            $userCoupon->end_at = $coupon->end_at;
        } else {
            // 领取后N天有效
            $userCoupon->start_at = time();
            // 使用 86400秒 计算，比 strtotime更精确可控
            $userCoupon->end_at = $coupon->day > 0 ? (time() + $coupon->day * 86400) : 0;
        }

        // 6. 保存
        if (!$userCoupon->save()) {
            $errorMsg = current($userCoupon->getFirstErrors()) ?? '未知错误';
            throw new \Exception("发放优惠券失败: " . $errorMsg);
        }

        return $userCoupon;
    }

    /**
     * 优惠券转赠核心逻辑
     * @param int $userCouponId
     * @param int $fromUserId
     * @param int $toUserId
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
            // 1. 查找并锁定该优惠券
            $userCoupon = UserCoupon::find()
                ->where(['id' => $userCouponId, 'user_id' => $fromUserId, 'is_delete' => 0])
                ->forUpdate()
                ->one();

            if (!$userCoupon) {
                throw new \Exception(\app\helpers\NounHelper::parse('coupon') . '不存在或不属于您');
            }

            // 校验转赠权限
            if ($userCoupon->is_transferable != 1) {
                throw new \Exception('该' . \app\helpers\NounHelper::parse('coupon') . '不支持转赠');
            }

            // 2. 状态检查
            if ($userCoupon->status != UserCoupon::STATUS_UNUSE) {
                throw new \Exception('该' . \app\helpers\NounHelper::parse('coupon') . '已使用或已失效，无法转赠');
            }
            if ($userCoupon->end_at > 0 && $userCoupon->end_at < time()) {
                throw new \Exception('该' . \app\helpers\NounHelper::parse('coupon') . '已过期，无法转赠');
            }

            // 3. 检查接收方
            $targetUser = User::findOne(['id' => $toUserId, 'mall_id' => Yii::$app->mall->id, 'is_delete' => 0]);
            if (!$targetUser) {
                throw new \Exception('接收方用户不存在');
            }

            // 4. 执行转移 (直接修改归属人)
            $userCoupon->user_id = $toUserId;

            if (!$userCoupon->save()) {
                throw new \Exception('转赠失败: 数据保存异常');
            }

            // 5. 记录转赠日志
            $log = new UserCouponTransferLog();
            $log->mall_id = Yii::$app->mall->id;
            $log->user_coupon_id = $userCoupon->id;
            $log->from_user_id = $fromUserId;
            $log->to_user_id = $toUserId;
            $log->created_at = time();
            $log->remark = "用户主动转赠";

            if (!$log->save()) {
                throw new \Exception('转赠失败: 日志记录异常');
            }

            $transaction->commit();
            return true;

        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * 辅助方法：生成优惠券描述文本
     * @param array $couponData
     * @return string
     */
    public static function getCouponDescription(array $couponData): string
    {
        // 兼容处理 type 0 和 1
        $type = intval($couponData['type'] ?? 0);
        $minPrice = floatval($couponData['min_price'] ?? 0);

        $desc = $minPrice > 0 ? "满{$minPrice}元" : "无门槛";

        if ($type == 0) {
            // 满减
            $subPrice = floatval($couponData['sub_price'] ?? 0);
            return "{$desc}减{$subPrice}元";
        } else {
            // 折扣
            $discount = floatval($couponData['discount'] ?? 10);
            return "{$desc}享{$discount}折";
        }
    }
}