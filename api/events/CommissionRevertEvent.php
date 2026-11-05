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
 * 分润统一回滚事件 —— 大一统:把「订单取消」与「售后退款」两条路径归一成一个回滚信号。
 *
 * 它广播的是一个事实:这一件(order_detail_id>0)或这一整单(=0)作废了、不再算数。
 * 分润/业绩/奖励类插件只监听本事件,回滚自己记在上面的东西(分润、业绩、赠送积分、码、奖池……),
 * 不再分别监听 order.canceled / refund.*,也不用关心是取消还是售后(见 docs/订单事件与分润标准规范.md)。
 * 不看钱到没到:和钱有关的是 refund.completed(退款成功),分润回滚别挂那里。
 *
 * 触发点(都在各自事务提交之后,规避「事件已发、事务回滚」;正常只发一次,收尾重跑可能再来,监听者必须幂等):
 *   · 订单取消 / 后台关单  OrderSettlementService::cancel → order_detail_id=0,关单那一刻
 *   · 售后退款            RefundService::completeRefund → voidDetail → order_detail_id=该售后单明细,
 *                         商家确认打款那一刻(之后顾客不能撤销、退款撤不回)。
 *                         不在「同意」时发:同意了顾客还能撤销,回滚的东西补不回来;换货不退钱,不发
 *   · 插件自己发起的全额退款(拼团失败 lucky_fail、遇搭流局 yuda_cancel 等) RefundSuccessDispatcher::handleGeneric
 *                         → order_detail_id=0,退款成功且整单退完时
 * 插件收到时分润还是待结算 / 已结算;平台随后统一追回(售后是紧接着,关单是退款成功时)。
 *
 * ⛔ 同一单会先后收到两次:先退一件(按明细)、之后再关单(整单)。整单信号的意思是「这单剩下的全部作废」——
 *    先前按明细作废的那几件已经收到过信号、处理过了,整单处理必须跳过它们,否则同一件扣两次
 *    (9/28 查出品牌代理奖池、艾载仁间推荐人业绩就是这么扣两次的)。
 *    判断「哪几件已经处理过」用本插件按明细记下的东西(按明细的冲减流水、按明细的回滚标记……),
 *    别看明细现在的状态:排队任务跑的时候,关单那几件可能也已经被标成作废了。
 *    只用 CommissionService::revert() 的不用管 —— 作废过的流水它会跳过。
 */
class CommissionRevertEvent extends Event
{
    const NAME = 'order.commission.revert';

    /** @var int 商城ID */
    public $mall_id;

    /** @var int 订单ID */
    public $order_id;

    /** @var int 订单明细ID;0=整单回滚,>0=仅回滚该明细 */
    public $order_detail_id = 0;

    /** @var string 回滚来源:'cancel'(主动取消) | 'refund'(售后退款) */
    public $source;
}
