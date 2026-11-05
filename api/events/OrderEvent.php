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

use app\models\Order;
use yii\base\Event;

/**
 * Class OrderEvent (重构增强版)
 * @package app\events
 */
class OrderEvent extends Event
{
    /**
     * @var Order 触发当前事件的、完整的订单模型对象
     */
    public $order;

    /**
     * @var int|null [新增] 本次发货的物流记录ID (ExpressLog ID)。
     * 如果是无需物流的发货，此值为 null。
     */
    public $express_log_id;

    /**
     * @var array|null [新增] 本次发货的订单详情ID列表 (OrderDetail IDs)。
     * 用于精确知道是哪些商品被发出了。
     */
    public $order_detail_ids;

    /**
     * @var mixed|true
     */
    public $isHandled;
}