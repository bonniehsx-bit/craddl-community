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

use app\events\UserLevelCheckNeededEvent;
use app\core\Job;
use app\helpers\OptionHelper;
use app\models\OrderDetail;
use app\models\Level;
use app\models\Order;
use app\models\User;
use app\services\UserService;

use Yii;

class UserLoginJob extends Job
{
    public $user_id;
    public $parent_id;

    public function execute($params=null)
    {
        $user = User::findOne($this->user_id);
        if (!$user || $user->is_delete) {
            Yii::warning("[UserLoginJob] 用户 #{$this->user_id} 未找到或已删除，任务中止。");
            return;
        }

        // 绑定上级（bindParent 内部自带事务，不再外层包裹，避免嵌套事务冲突）
        try {
            $this->checkAndBindParent($user);
        } catch (\Throwable $e) {
            Yii::error("[UserLoginJob] 绑定上级失败 UserID:{$this->user_id}: " . $e->getMessage());
        }

        // 推广者资格检查
        try {
            $user->refresh(); // 绑定可能更新了字段，刷新
            $this->checkAndGrantInviter($user);
        } catch (\Throwable $e) {
            Yii::error("[UserLoginJob] 推广者检查失败 UserID:{$this->user_id}: " . $e->getMessage());
        }

        // 统一等级检测事件：会员/分销/经销 Listener 各自推 Job
        Yii::$app->trigger(UserLevelCheckNeededEvent::NAME, new UserLevelCheckNeededEvent([
            'user_id' => $this->user_id,
            'trigger' => 'user_login',
        ]));
    }

    private function checkAndGrantInviter(User $user)
    {
        if ($user->is_inviter) {
            return;
        }
        Yii::warning("[UserLoginJob] 检查用户 #{$user->id} 的推广者资格");

        $level = Level::findOne($user->level_id);
        if ($level && $level->is_inviter) {
            $this->grantInviter($user, "会员等级升级");
            return;
        }

        $setting = OptionHelper::get('relation_setting', $user->mall_id, []);
        if (!$setting || !isset($setting['invite_type'])) {
            return;
        }

        $isQualified = false;
        $reason = '';
        switch ($setting['invite_type']) {
            case 0:
                $isQualified = true;
                $reason = "设置为无条件";
                break;
            case 1:
                $isQualified = $this->hasPurchasedGoods($user, $setting);
                $reason = "购买了指定商品";
                break;
            case 3:
                $isQualified = $this->hasReachedSpendThreshold($user, $setting);
                $reason = "消费金额达标";
                break;
        }

        if ($isQualified) {
            $this->grantInviter($user, $reason);
        }
    }

    /**
     * 绑定上级 —— 校验逻辑已下沉到 UserService::tryBindParent。
     * 注册(同步,立刻建立关系)与本 Job(异步兜底)共用同一份,避免两处口径漂移。
     *
     * $this->parent_id 可能是前端哨兵 -1(本次请求无邀请人,见 uniapp/utils/request.js:getPid),
     * 与 user.parent_id 的 -1(平台直属)不是一回事;tryBindParent 只把 >0 当候选。
     */
    private function checkAndBindParent(User $user)
    {
        UserService::tryBindParent($user, (int)$this->parent_id, ':login');
    }

    private function grantInviter(User $user, $reason = '')
    {
        $user->is_inviter = 1;
        $user->inviter_at = time();
        if (!$user->save()) {
            throw new \Exception($this->getErrorMsg($user));
        }
        Yii::warning("[UserLoginJob] 用户 #{$user->id} 已成为推广者。原因: {$reason}");
    }

    /**
     * [已重构] 辅助方法：检查用户是否已购买指定商品
     * @param User $user
     * @param array $setting
     * @return bool
     */
    private function hasPurchasedGoods(User $user, array $setting): bool
    {
        if (empty($setting['goods_ids'])) {
            return false;
        }

        $buy_type = $setting['buy_type'] ?? 0;

        $query = Order::find()->alias('o')
            ->innerJoin(['od' => OrderDetail::tableName()], 'od.order_id = o.id') // 使用 order_id 关联
            ->where([
                'o.user_id' => $user->id,
                'o.is_delete' => 0,
                'od.goods_id' => $setting['goods_ids']
            ]);

        if ($buy_type == 1) { // 订单结算后
            $query->andWhere([
                'o.status' => Order::STATUS_COMPLETED,
                'o.settlement_status' => Order::SETTLEMENT_STATUS_SETTLED
            ]);
        } else { // 付款后
            $query->andWhere(['o.pay_status' => Order::PAY_STATUS_PAID]);
        }
        return $query->exists();
    }

    /**
     * [已重构] 辅助方法：检查用户是否达到消费门槛
     * @param User $user
     * @param array $setting
     * @return bool
     */
    private function hasReachedSpendThreshold(User $user, array $setting): bool
    {
        $price = floatval($setting['price'] ?? 0);
        if ($price <= 0) {
            return false;
        }

        $buy_type = $setting['buy_type'] ?? 0;
        $query = Order::find()->where(['is_delete' => 0, 'user_id' => $user->id]);

        if ($buy_type == 1) { // 订单结算后
            $query->andWhere([
                'status' => Order::STATUS_COMPLETED,
                'settlement_status' => Order::SETTLEMENT_STATUS_SETTLED
            ]);
        } else { // 付款后
            $query->andWhere(['pay_status' => Order::PAY_STATUS_PAID]);
        }
        $sum_price = $query->sum('pay_price') ?? '0.00';

        return $sum_price >= $price;
    }

    /**
     * [已重构] 辅助方法：检查用户是否满足订单绑定条件
     * @param User $user
     * @param int $conditionType 1:下单, 2:付款
     * @return bool
     */
}