<?php

/**
 * Copyright (c) 2026 佛山今日鼎盛信息科技有限公司
 * @author Refactored Version
 * @date 2026-02-15
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

namespace app\services\order\pipeline;

/**
 * 管道节点接口
 * * 无论是订单预览 (Preview) 还是订单创建 (Submit)，
 * 所有插入流水线的业务节点都必须实现此接口。
 */
interface StageInterface
{
    /**
     * 执行管道节点逻辑
     * * @param mixed $payload 贯穿管道的载荷对象 (OrderPayload | PreviewPayload)
     * @param \Closure $next 将控制权移交给下一个节点的闭包
     * @return mixed
     */
    public function handle($payload, \Closure $next);
}
