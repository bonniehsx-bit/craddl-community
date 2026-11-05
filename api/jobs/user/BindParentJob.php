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

use app\helpers\LockHelper;
use app\helpers\OptionHelper;
use app\core\Job;
use app\models\Order;

use app\models\User;
use Yii;

class BindParentJob extends Job
{
    public $user_id;
    public $parent_id;

    public function execute($params = null)
    {
        Yii::warning("[BindParentJob] 开始执行。user_id={$this->user_id} job.parent_id={$this->parent_id}");

        if (!LockHelper::lock('BIND_PARENT_JOB_' . $this->user_id, 5)) {
            Yii::warning("[BindParentJob] 获取用户 #{$this->user_id} 的绑定锁失败，任务终止。");
            return;
        }

        $user = User::findOne(['id' => $this->user_id, 'parent_id' => 0]);
        if (!$user) {
            $existing = User::findOne($this->user_id);
            $currentParentId = $existing ? $existing->parent_id : 'N/A（用户不存在）';
            Yii::warning("[BindParentJob] 用户 #{$this->user_id} 不存在或已有推荐人（当前 parent_id={$currentParentId}），任务终止。");
            return;
        }

        Yii::warning("[BindParentJob] 用户 #{$user->id} 状态：parent_id={$user->parent_id}" .
            " maybe_parent_id={$user->maybe_parent_id}" .
            " is_inviter={$user->is_inviter}" .
            " mall_id={$user->mall_id}");

        // 已是推广员且拥有下级的用户不可被随意绑定上级，防止整棵团队树被"嫁接"
        if ($user->is_inviter == 1) {
            $hasTeam = User::find()->where(['parent_id' => $user->id, 'is_delete' => 0])->exists();
            if ($hasTeam) {
                Yii::warning("[BindParentJob] 用户 #{$this->user_id} 已是推广员且拥有下级团队，拒绝绑定上级。");
                return;
            }
            Yii::warning("[BindParentJob] 用户 #{$this->user_id} 是推广员但无下级，允许绑定继续。");
        }

        $final_parent_id = $this->determineParentId($user);
        Yii::warning("[BindParentJob] 确定推荐人：job.parent_id={$this->parent_id} db.maybe_parent_id={$user->maybe_parent_id} → final_parent_id={$final_parent_id}");
        if (!$final_parent_id) {
            Yii::warning("[BindParentJob] 未能确定用户 #{$this->user_id} 的有效推荐人，任务终止。");
            return;
        }

        if ($user->id == $final_parent_id) {
            Yii::warning("[BindParentJob] 用户 #{$this->user_id} 不能将自己设为推荐人，任务终止。");
            return;
        }

        $parent = User::findOne(['id' => $final_parent_id, 'mall_id' => $user->mall_id, 'is_inviter' => 1, 'is_delete' => 0]);
        if (!$parent) {
            $rawParent = User::findOne($final_parent_id);
            $detail = $rawParent
                ? "is_inviter={$rawParent->is_inviter} is_delete={$rawParent->is_delete} mall_id={$rawParent->mall_id}"
                : '用户不存在';
            Yii::warning("[BindParentJob] 推荐人 #{$final_parent_id} 不具备推广资格（{$detail}），任务终止。");
            return;
        }

        $setting = OptionHelper::get('relation_setting', $user->mall_id);
        $child_type = $setting['child_type'] ?? 0;
        Yii::warning("[BindParentJob] 绑定条件类型 child_type={$child_type}（0=无条件 1=首次下单 2=首次付款）。");

        if ($child_type != 0) {
            if ($user->maybe_parent_id != $final_parent_id) {
                Yii::warning("[BindParentJob] 用户 #{$user->id} 暂存推荐人意向 maybe_parent_id={$final_parent_id}。");
                $user->maybe_parent_id = $final_parent_id;
                $user->save(false);
            }
            if (!$this->checkBindingCondition($user, $child_type)) {
                Yii::warning("[BindParentJob] 用户 #{$this->user_id} 尚未满足 child_type={$child_type} 的绑定条件，等待下次触发。");
                return;
            }
            Yii::warning("[BindParentJob] 用户 #{$this->user_id} 已满足 child_type={$child_type} 的绑定条件，继续执行绑定。");
        }

        Yii::warning("[BindParentJob] 所有条件满足，准备为用户 #{$this->user_id} 绑定推荐人 #{$final_parent_id}。");

        try {
            $user->bindParent($final_parent_id);
            Yii::warning("[BindParentJob] 用户 #{$this->user_id} 绑定推荐人 #{$final_parent_id} 成功。");
        } catch (\Exception $e) {
            Yii::warning("[BindParentJob] 用户 #{$this->user_id} 绑定推荐人 #{$final_parent_id} 失败：{$e->getMessage()}");
            User::updateAll(['maybe_parent_id' => 0], ['id' => $user->id]);
        }
    }

    private function determineParentId(User $user)
    {
        if ($this->parent_id && $this->parent_id != -1) {
            return $this->parent_id;
        }
        if ($user->maybe_parent_id) {
            return $user->maybe_parent_id;
        }
        return null;
    }

    /**
     * [已重构] 检查绑定条件是否满足
     * @param User $user
     * @param int $child_type
     * @return bool
     */
    private function checkBindingCondition(User $user, $child_type)
    {
        if ($child_type == 1) {
            $orderExists = Order::find()->where(['user_id' => $user->id, 'is_delete' => 0])->exists();
            if (!$orderExists) {
                Yii::warning("[BindParentJob] 用户 #{$user->id} 未满足 [首次下单] 条件（尚无任何订单）。");
                return false;
            }
            Yii::warning("[BindParentJob] 用户 #{$user->id} 满足 [首次下单] 条件。");
        } elseif ($child_type == 2) {
            $orderExists = Order::find()->where([
                'user_id' => $user->id,
                'is_delete' => 0,
                'pay_status' => Order::PAY_STATUS_PAID
            ])->exists();
            if (!$orderExists) {
                Yii::warning("[BindParentJob] 用户 #{$user->id} 未满足 [首次付款] 条件（尚无已支付订单）。");
                return false;
            }
            Yii::warning("[BindParentJob] 用户 #{$user->id} 满足 [首次付款] 条件。");
        } else {
            Yii::warning("[BindParentJob] 用户 #{$user->id} 未知 child_type={$child_type}，视为条件不满足。");
            return false;
        }
        return true;
    }
}
