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

/**
 * 分账网关异步回调处理器契约。
 *
 * 分账 webhook 的物理入口(web/split/*.php)属于核心通知基建(与其它网关回调同处 controllers/notify,
 * 免登录、免 CSRF、路由稳定),但「按分账单号找记录、更新状态、触发放款」的业务实现属于插件。
 * 核心 notify 控制器只做解析,再委托给 PayoutHandlerRegistry 命中的 handler(若其实现本契约),
 * 从而核心对插件的分账模型/结算逻辑保持零引用。
 */
interface SplitGatewayNotifyHandlerInterface
{
    /**
     * @param string $merOrderNo 分账单号(网关回传的 merOrderNo,对应插件分账记录的 split_order_no)
     * @param array  $data       已解析、状态已归一化(BaseSplit::transStatus)的回调数据
     * @return bool 处理成功返回 true(核心据此回 SUCCESS/FAIL 给网关)
     */
    public function onGatewayNotify(string $merOrderNo, array $data): bool;
}
