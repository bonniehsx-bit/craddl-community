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

use app\events\UserEvent;
use app\helpers\JobHelper;
use app\helpers\MessageHelper;
use app\models\User;
use Yii;

/**
 * Class UserParentChangeListener
 * @package app\listeners
 *
 * 监听用户绑定上级事件，并向上级发送新成员加入的通知。
 */
class UserParentChangeListener extends BaseListener
{
    /**
     * 将事件绑定到此监听器
     */
    public function register()
    {
        // 监听与模板完全相同的事件
        Yii::$app->on(UserEvent::PARENT_CHANGE, [$this, 'handle']);
    }

    /**
     * 处理用户绑定推荐人事件，发送通知。
     * @param UserEvent $event
     */
    public function handle(UserEvent $event)
    {
        // $event->user_id 是刚刚绑定了上级的新用户ID
        $newUser = User::findOne($event->user_id);

        // $parentUser 是新用户的上级（即推荐人）
        $parentUser = $newUser ? User::findOne($newUser->parent_id) : null;

        if (!$newUser || !$parentUser) {
            // 如果新用户或其上级不存在，则直接返回
            return;
        }

        $newUserId = $newUser->id;
        $parentId = $parentUser->id;

        Yii::warning("[新成员通知] 用户 {$newUserId} 成功绑定上级 {$parentId}，准备发送通知...");

        try {
            // 准备通知文案
            $title = '恭喜您，团队新添一名成员';
            $content = "用户“{$newUser->nickname}”已通过您的分享成功加入，您的团队正在不断壮大！";

            // 根据您的要求，自定义一个有意义的 msg_type
            $msg_type = 'new_subordinate_join';

            // 使用我们优化后的 MessageHelper 发送通知
            MessageHelper::sendToUser(
                $parentId,
                $title,
                $content,
                $msg_type
            );

            Yii::warning("[新成员通知] 成功向上级 {$parentId} 发送了新成员加入的通知。");

        } catch (\Exception $e) {
            Yii::error("[新成员通知] 向上级 {$parentId} 发送通知时发生异常: " . $e->getMessage());
        }
    }
}