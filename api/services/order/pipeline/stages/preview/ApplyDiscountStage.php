<?php

/**
 * Copyright (c) 2026 佛山今日鼎盛信息科技有限公司
 * @author Refactored Version (Pipeline Pattern)
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

namespace app\services\order\pipeline\stages\preview;

use app\services\order\discount\DiscountEngine;
use app\services\order\pipeline\PreviewPayload;
use app\services\order\pipeline\StageInterface;

/**
 * 订单预览阶段 3：应用优惠策略
 * * 职责：调用优惠引擎 (DiscountEngine)，基于前置阶段计算的基础价格 (BasePricing)，
 * 叠加计算优惠券、积分抵扣、首单立减、随机立减、自提折扣等营销优惠。
 */
class ApplyDiscountStage implements StageInterface
{
    /**
     * 执行过滤节点
     * * @param PreviewPayload $payload 贯穿预览管道的载荷对象
     * @param \Closure $next 下一个执行节点
     * @return mixed
     */
    public function handle($payload, \Closure $next)
    {
        // 1. 实例化核心优惠引擎
        $discountEngine = new DiscountEngine();

        // 2. 将基础价格、订单上下文、用户选择的优惠配置传入引擎进行计算
        $discountResult = $discountEngine->apply(
            $payload->basePricing,
            $payload->context,
            $payload->discountConfig
        );

        // 3. 将计算完成的最终优惠结果 (DiscountResult) 挂载到载荷上
        $payload->discountResult = $discountResult;

        // 4. 将控制权流转给下一个管道节点 (如: FormatPreviewResponseStage)
        return $next($payload);
    }
}
