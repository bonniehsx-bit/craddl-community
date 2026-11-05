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

namespace app\listeners;

use app\events\UserLevelCheckNeededEvent;
use app\helpers\JobHelper;
use app\jobs\user\UserLevelUpgradeJob;
use Yii;

/**
 * 会员等级升级检测监听器
 *
 * 监听 UserLevelCheckNeededEvent，推送 UserLevelUpgradeJob 到队列。
 * 主商城是会员等级的"归属方"，本 listener 常驻。
 */
class UserLevelCheckListener extends BaseListener
{
    public function register()
    {
        Yii::$app->on(UserLevelCheckNeededEvent::NAME, [$this, 'onCheckNeeded']);
    }

    public function onCheckNeeded(UserLevelCheckNeededEvent $event)
    {
        if (!$event->user_id) {
            return;
        }
        // 队列级去重:同一用户在途升级检测只保留一份(unique_key 在任务终态由 Go 调度器释放)。
        // 覆盖 dispatchUplineCheck 的 60s 事件锁挡不住的路径(经销/登录/dirty兜底/下单人自身),
        // 把同一用户同窗口的多份 UserLevelUpgradeJob 折叠为一份,从源头收敛并发与重复执行。
        JobHelper::push(
            new UserLevelUpgradeJob(['user_id' => $event->user_id]),
            5,
            ['unique_key' => 'lvlchk_' . $event->user_id]
        );
    }
}
