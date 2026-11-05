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

namespace app\events;

use yii\base\Event;

/**
 * 用户等级升级检测事件（统一入口）
 *
 * 任何业务只要觉得某用户"可能需要重新评估等级"，就 trigger 一次本事件：
 *
 *     \Yii::$app->trigger(UserLevelCheckNeededEvent::NAME, new UserLevelCheckNeededEvent([
 *         'user_id' => 123,
 *         'trigger' => 'order_paid',   // 触发源，便于日志追踪，可选
 *     ]));
 *
 * 三套等级体系（系统会员 / 分销 / 经销）各自有一个 Listener 监听本事件，
 * 收到后 push 自己的升级 Job。这样：
 *   · 业务方只发一次事件，不关心谁在听
 *   · 插件可自由装卸，新插件接入只需加一个 listener
 *   · 主商城代码里不会出现对任何插件 Job 的硬编码引用
 *
 * 常见触发源：
 *   · order_paid        订单支付成功
 *   · order_settled     订单结算（完成）
 *   · parent_bind       用户绑定上级（可能触发上级团队/直推条件）
 *   · level_cascaded    某用户升级成功 → 上级等级重算
 *   · admin_manual      管理员手动触发
 *   · cron              定时任务兜底扫描
 *   · team_changed      团队结构变化（新增有效下级等）
 */
class UserLevelCheckNeededEvent extends Event
{
    const NAME = 'user.level.check_needed';

    /** @var int 目标用户 ID */
    public $user_id;

    /** @var string 触发源标识（便于日志/排查，没有业务用途） */
    public $trigger = 'unknown';
}
