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
 * 积分变动事件
 *
 * 用途:凡是 user.integral 发生增减(经唯一账本入口 IntegralLogHelper::add/sub),
 *       都会派发此事件。各业务/插件 listener 监听它做副作用(会员等级、消息通知、
 *       风控统计等),无需在各自发/扣积分处重复埋点。
 *
 * 设计原则(高内聚低耦合):此事件在**账本 chokepoint** 派发,天然覆盖全部积分变动
 *       (含未迁移到 IntegralService 网关的历史调用),listener 不会漏事件。
 *
 * listener 写法示例:
 *   Yii::$app->on(IntegralEvent::CHANGED, function (IntegralEvent $e) {
 *       if ($e->direction === 'add') { ... 触发升级检测 ... }
 *   });
 */
class IntegralEvent extends Event
{
    /** 积分变动(增或减) */
    const CHANGED = 'INTEGRAL_CHANGED';

    /** @var int 商城 ID */
    public $mallId;

    /** @var int 用户 ID */
    public $userId;

    /** @var float 本次变动数额(恒正) */
    public $amount;

    /** @var string 方向:add=增 / sub=减 */
    public $direction;

    /** @var string|null 业务类型(order_base/sign/exchange/parking_deduct...);历史调用可能为空 */
    public $bizType;

    /** @var int|null 关联的 integral_log 记录 ID */
    public $logId;

    /** @var float|null 变动后的最新余额 */
    public $balanceAfter;

    /** @var array 附加参数(order_no/mch_id/source_ref 等,由网关透传) */
    public $params = [];
}
