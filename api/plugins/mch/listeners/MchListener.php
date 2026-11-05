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

namespace app\plugins\mch\listeners;

use app\helpers\MessageHelper; // [核心新增] 1. 引入消息助手
use app\listeners\BaseListener;
use app\plugins\mch\events\MchEvent;
use app\plugins\mch\helpers\MchAdminNotifyHelper;

use Yii;

class MchListener extends BaseListener
{
    public function register()
    {
        Yii::$app->on(MchEvent::MCH_APPROVED, [$this, 'onMchApproved']);
        Yii::$app->on(MchEvent::MCH_SUBMITTED, [$this, 'onMchSubmitted']);
    }

    /**
     * 商家提交入驻申请 → 通知平台管理员（IM/微信/短信，后台可配置）。
     * @param MchEvent $event
     */
    public function onMchSubmitted(MchEvent $event)
    {
        try {
            if ($event->mch) {
                MchAdminNotifyHelper::notifyApplySubmitted($event->mch);
            }
        } catch (\Throwable $e) {
            Yii::error('[MchSubmitted] 通知管理员失败 mch#' . ($event->mch->id ?? 0) . ': ' . $e->getMessage());
        }
    }

    /**
     * 处理商户审核通过事件
     * @param MchEvent $event
     */
    public function onMchApproved(MchEvent $event)
    {
        $mch = $event->mch;

        // 使用事务确保所有操作的原子性
        // --- [核心新增] 在核心业务成功后，发送通知 ---
        try {
            if ($mch->user_id) {
                $title = '恭喜！您的店铺已审核通过';
                // 使用 mch_name 动态填充店铺名称
                $content = "您的店铺「{$mch->mch_name}」已成功通过平台审核，现在可以开始营业了。祝您生意兴隆！";
                $msg_type = 'mch_approved_notification';

                MessageHelper::sendToUser(
                    $mch->user_id,
                    $title,
                    $content,
                    $msg_type
                );
                Yii::warning("[MchApproved] 已向商户用户 #{$mch->user_id} 发送审核通过通知。");
            }
        } catch (\Exception $e) {
            // 即使通知失败，也只记录日志，因为核心业务已经成功
            Yii::error("[MchApproved] 商户 {$mch->id} 发送审核通过通知失败: " . $e->getMessage());
        }
    }
}