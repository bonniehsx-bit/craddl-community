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

namespace app\plugins\marketing\listeners;

use app\events\UserEvent;
use app\listeners\BaseListener;
use app\models\User;
use app\plugins\marketing\services\TriggerService;
use Yii;

/**
 * 用户注册监听:新人礼包【即时到账】。
 * 监听核心 UserEvent::REGISTER(用户首次落库即触发),秒级发券。
 */
class MarketingUserListener extends BaseListener
{
    public function register()
    {
        Yii::$app->on(UserEvent::REGISTER, [$this, 'onRegister']);
    }

    public function onRegister(UserEvent $event)
    {
        try {
            $user = User::findOne((int)$event->user_id);
            if (!$user) {
                return;
            }
            TriggerService::onRegister((int)$user->mall_id, (int)$user->id);
        } catch (\Throwable $e) {
            Yii::error('[营销活动] onRegister 失败 uid=' . ($event->user_id ?? 0) . ' - ' . $e->getMessage());
        }
    }
}
