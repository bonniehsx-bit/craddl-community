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

use app\models\OrderRefund;
use yii\base\Event;

/**
 * Class RefundEvent
 * @package app\events
 *
 * 与售后单 (OrderRefund) 相关的通用事件。
 * 它的职责是作为数据载体，在事件触发时携带完整的 OrderRefund 模型实例。
 *
 * 真正会广播的只有三个:applied(顾客申请)、agreed(商家同意,顾客还能撤销)、completed(退款成功)。
 * rejected / user.delivered / merchant.received 只有定义,目前没有任何地方发,别去监听。
 * 这件作废、分润回滚不看这里,看 order.commission.revert(商家确认打款时发,见 CommissionRevertEvent)。
 */
class RefundEvent extends Event
{
    // --- 售后流程中的核心事件名称常量 ---

    /**
     * @var string 当用户成功提交一个新的售后申请时触发
     */
    const EVENT_APPLIED = 'refund.applied';

    /**
     * @var string 当商家同意了用户的售后申请时触发
     */
    const EVENT_AGREED = 'refund.agreed';

    /**
     * @var string 当商家拒绝了用户的售后申请时触发
     */
    const EVENT_REJECTED = 'refund.rejected';

    /**
     * @var string 当用户在“退货退款”流程中，填写了退货物流信息后触发
     */
    const EVENT_USER_DELIVERED = 'refund.user.delivered';

    /**
     * @var string 当商家确认收到用户的退货后触发
     */
    const EVENT_MERCHANT_RECEIVED = 'refund.merchant.received';

    /**
     * @var string 退款成功时触发(RefundSuccessDispatcher::handleAfterSales 收尾最后一步):支付渠道受理了退款 /
     *             余额已退回 / 后台登记已退款。只挂和钱、通知、订单状态有关的处理;这件在商家确认打款时已经作废,
     *             分润回滚别挂这里
     */
    const EVENT_COMPLETED = 'refund.completed';


    /**
     * @var OrderRefund 触发当前事件的、完整的售后单模型对象
     */
    public $refund;

    /**
     * @var int 为了兼容可能存在的旧代码，保留id属性
     * @deprecated 请优先使用 $refund->id
     */
    public $id;


    /**
     * @inheritdoc
     * 重写 init 方法，在事件创建时自动从模型填充id
     */
    public function init()
    {
        parent::init();
        if ($this->refund instanceof OrderRefund) {
            $this->id = $this->refund->id;
        }
    }
}