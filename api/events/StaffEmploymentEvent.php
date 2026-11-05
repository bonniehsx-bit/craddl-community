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
 * 员工在职状态变更(通知型事件:「发生了什么」,由各插件自己决定副作用)
 *
 * 人事插件(hrm)办理离职 / 恢复在职时触发。按「商城 + 用户」标识人,不带任何插件自己的主键 ——
 * 人事插件不用知道谁在用这个人,别的插件也不用 import 人事插件的类。
 *
 * 用途:各插件自己的员工表(商管内部员工、店员……)跟着收回 / 恢复权限。
 * 真实问题:hrm 里办了离职,商管「内部员工」还是启用,还挂着业主岗位,照样能审批合同、报销。
 *
 * 触发方:
 *   Yii::$app->trigger(StaffEmploymentEvent::EVENT_RESIGNED, new StaffEmploymentEvent([
 *       'mallId' => $mallId, 'userId' => $staff->user_id, 'staffName' => $staff->name, 'source' => 'hrm',
 *   ]));
 *
 * 监听方(在插件 listener 的 register() 里):
 *   Yii::$app->on(StaffEmploymentEvent::EVENT_RESIGNED, [$this, 'onResigned']);
 *   监听器内自己 try/catch,失败不要冒泡 —— 别让人事保存因为别的插件出错而失败。
 */
class StaffEmploymentEvent extends Event
{
    /** 办理离职 */
    const EVENT_RESIGNED = 'staff.employment.resigned';
    /** 从离职恢复为在职 / 试用 */
    const EVENT_REJOINED = 'staff.employment.rejoined';

    /** @var int 商城 ID */
    public $mallId;

    /** @var int 员工绑定的用户 ID(user 表);0 表示没绑用户,触发方不应发 */
    public $userId;

    /** @var string 员工姓名(仅用于日志 / 提示) */
    public $staffName = '';

    /** @var string 来源插件标识,如 'hrm' */
    public $source = '';

    /** @var int 操作人(后台管理员 / 用户 ID),0 = 系统 */
    public $operatorId = 0;
}
