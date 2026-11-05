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

namespace app\jobs\user;

use app\helpers\OptionHelper;
use app\core\Job;
use app\models\OrderDetail;
use app\models\Level;
use app\models\Order;
use app\models\User;
use yii\queue\Queue;
use Yii;

class CheckInviterJob extends Job
{
    public $user_id;

    public function execute($params=null)
    {
        Yii::warning("[CheckInviterJob] 开始为用户 #{$this->user_id} 检查推广者资格...");

        $user = User::findOne(['id' => $this->user_id, 'is_delete' => 0, 'is_inviter' => 0]);
        if (!$user) {
            Yii::warning("[CheckInviterJob] 用户 #{$this->user_id} 不存在或已是推广者，任务结束。");
            return;
        }

        // 1. 根据会员等级判断
        $level = Level::findOne($user->level_id);
        if ($level && $level->is_inviter) {
            $this->grantInviter($user, "会员等级 [{$level->name}] 自动成为推广者");
            return;
        }

        $setting = OptionHelper::get('relation_setting', $user->mall_id);
        if (!$setting || !isset($setting['invite_type'])) {
            Yii::warning("[CheckInviterJob] 未配置推广者条件，任务结束。");
            return;
        }

        // 2. 无条件成为推广者
        if ($setting['invite_type'] == 0) {
            $this->grantInviter($user, "设置为无条件");
            return;
        }

        // 3. 消费满
        if ($setting['invite_type'] == 3) {
            $price = floatval($setting['price'] ?? 0);
            if ($price <= 0) return;

            $buy_type = $setting['buy_type'] ?? 0; // 0=付款后, 1=完成后
            $query = Order::find()->where(['is_delete' => 0, 'user_id' => $this->user_id]);

            if ($buy_type == 0) { // 付款后就算
                // [已修正] 查询所有已付款的订单 (状态 > 待付款)
                $query->andWhere(['pay_status' => Order::PAY_STATUS_PAID]);
                $sum_price = $query->sum('pay_price') ?? '0.00';
                if ($sum_price >= $price) {
                    $this->grantInviter($user, "付款订单金额满 {$price} 元");
                    return;
                }
            } else { // 订单结算后才算
                // [已修正] 精确查询所有“已结算”的订单
                $query->andWhere([
                    'status' => Order::STATUS_COMPLETED,
                    'settlement_status' => Order::SETTLEMENT_STATUS_SETTLED
                ]);
                $sum_price = $query->sum('pay_price') ?? '0.00';
                if ($sum_price >= $price) {
                    $this->grantInviter($user, "结算订单金额满 {$price} 元");
                    return;
                }
            }
        }

        // 4. 购买指定商品
        if ($setting['invite_type'] == 1) {
            $goods_ids = $setting['goods_ids'] ?? [];
            if (empty($goods_ids)) return;

            $buy_type = $setting['buy_type'] ?? 0; // 0=付款后, 1=完成后

            // [已修正] 使用 Order 和 OrderDetail 进行关联查询
            $query = Order::find()->alias('o')
                ->innerJoin(['od' => OrderDetail::tableName()], 'od.order_id = o.id')
                ->where([
                    'o.is_delete' => 0,
                    'o.user_id' => $this->user_id,
                    'od.goods_id' => $goods_ids
                ]);

            if ($buy_type == 0) { // 付款后就算
                $query->andWhere(['o.pay_status' => Order::PAY_STATUS_PAID]);
            } else { // 订单结算后才算
                $query->andWhere([
                    'o.status' => Order::STATUS_COMPLETED,
                    'o.settlement_status' => Order::SETTLEMENT_STATUS_SETTLED
                ]);
            }

            if ($query->exists()) {
                $this->grantInviter($user, "购买了指定商品");
                return;
            }
        }

        Yii::warning("[CheckInviterJob] 用户 #{$this->user_id} 未满足任何成为推广者的条件。");
    }

    /**
     * 授予用户推广者资格并保存
     * @param User $user
     * @param string $reason
     */
    private function grantInviter(User $user, string $reason)
    {
        $user->is_inviter = 1;
        $user->inviter_at = time();
        if ($user->save()) {
            Yii::warning("[CheckInviterJob] 用户 #{$this->user_id} 已成功成为推广者。原因: {$reason}");
        } else {
            Yii::error("[CheckInviterJob] 尝试为用户 #{$this->user_id} 设置推广者时保存失败。");
        }
    }
}