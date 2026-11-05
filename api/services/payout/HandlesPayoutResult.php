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

namespace app\services\payout;

/**
 * 打款结果处理器契约。
 *
 * 任何"提现/打款源单"模型实现本接口，即可被 PayoutResultDispatcher 按 source_type 多态路由，
 * 让网关/回调/轮询统一回流，而无需知道具体业务（cash / 提现插件 / mch ...）。
 *
 * 约定：PayoutLog.source_type 存源单模型的完整类名(FQCN)，dispatcher 据此找到实现类。
 */
interface HandlesPayoutResult
{
    /**
     * 处理网关回传的（已规范化的）打款结果。
     *
     * @param int   $sourceId 源单ID
     * @param array $result   规范化结果：['status' => 'success|failed|doing', 'payout_no' => ?, 'message' => ?]
     */
    public static function onPayoutResult(int $sourceId, array $result): void;
}
