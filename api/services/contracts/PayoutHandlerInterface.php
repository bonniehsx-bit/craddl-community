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

namespace app\services\contracts;

use app\models\CommissionLog;

/**
 * 结算目标处理器契约(payout handler)。
 *
 * 背景:核心 CommissionService::settle() 内置了 balance/integral/price/扩展钱包 等结算目标,
 * 但少数目标(如「第三方外部分账」)的落库/调用逻辑属于某个插件的私有实现,核心不应认识。
 * 插件通过 Plugin::getPayoutHandlers() 声明 `payoutType => 实现类`,由 PayoutHandlerRegistry 聚合;
 * settle() 命中已注册的 payoutType 时委托给对应 handler,核心对插件保持零引用。
 *
 * 语义与内置分支一致:handle() 在 settle() 的事务内被调用,负责把这笔已结算佣金按插件方式发放/落库。
 * handle() 应自行吞掉可降级的外部异常(如第三方网关失败只记失败态),仅在数据库级故障时抛出以触发回滚。
 */
interface PayoutHandlerInterface
{
    /**
     * @param CommissionLog $log       已被条件 UPDATE 置为 SETTLED 的佣金记录
     * @param array         $extraData 佣金记录 extra_data 解出的数组(含插件所需的协议字段)
     */
    public function handle(CommissionLog $log, array $extraData): void;
}
