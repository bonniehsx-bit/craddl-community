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
/**
 * Created by PhpStorm.
 * User: dujxmall
 * Date: 2023/1/11
 * Time: 15:39
 */

namespace app\events;


use yii\base\Event;

class MessageEvent extends Event
{
    // ------------------- 订单相关消息类型 (Order Related) -------------------

    /**
     * @var string 新订单提醒 (通常在支付成功后，发送给商家/后台)
     */
    const TYPE_ORDER_NEW_CREATED = 'order_new_created';
    /**
     * @var string 订单支付成功通知 (发送给用户)
     */
    const TYPE_ORDER_PAY_SUCCESS = 'order_pay_success';

    /**
     * @var string 订单已发货通知 (发送给用户)
     */
    const TYPE_ORDER_SHIPPED = 'order_shipped';

    /**
     * @var string 订单已确认收货通知 (发送给商家/后台)
     */
    const TYPE_ORDER_CONFIRMED = 'order_confirmed';

    /**
     * @var string 订单已完成通知 (发送给商家/后台)
     */
    const TYPE_ORDER_COMPLETED = 'order_completed';

    /**
     * @var string 订单已取消通知 (发送给用户)
     */
    const TYPE_ORDER_CANCELED = 'order_canceled';


    // ------------------- 售后相关消息类型 (Refund Related) -------------------

    /**
     * @var string 用户提交了新的售后申请 (发送给商家/后台)
     */
    const TYPE_REFUND_APPLIED = 'refund_applied';

    /**
     * @var string 售后申请被同意/或状态更新 (发送给用户)
     */
    const TYPE_REFUND_STATUS_CHANGED = 'refund_status_changed';


    // ------------------- 其他通用消息类型 (General) -------------------

    /**
     * @var string 其他通用/未分类事件
     */
    const TYPE_OTHER = 'other';


    // ------------------- 旧常量兼容 (可以逐步废弃) -------------------

    // 为了兼容旧代码，可以暂时保留，并指向新常量
    const TYPE_ORDER_SEND = self::TYPE_ORDER_SHIPPED;
    const TYPE_ORDER_PAY = self::TYPE_ORDER_PAY_SUCCESS;
    const TYPE_ORDER_CANCEL = self::TYPE_ORDER_CANCELED;
    const TYPE_ORDER_CONFIRM = self::TYPE_ORDER_CONFIRMED;
    const TYPE_ORDER_COMPLETE = self::TYPE_ORDER_COMPLETED;
    const TYPE_NEW_ORDER = self::TYPE_ORDER_NEW_CREATED;
    const TYPE_OTHER_EVENT = self::TYPE_OTHER;
    const MSG_CREATING = 'msg_creating';

    /**
     * @var array 消息体, 例如 ['title' => '...', 'content' => '...']
     */
    public $body;

    /**
     * @var int 关联的模型ID (可选)
     */
    public $id;
}