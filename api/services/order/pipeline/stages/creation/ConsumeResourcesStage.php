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

namespace app\services\order\pipeline\stages\creation;

use app\services\IntegralService;
use app\services\order\pipeline\CreationPayload;
use app\services\order\pipeline\StageInterface;

class ConsumeResourcesStage implements StageInterface
{
    public function handle($payload, \Closure $next)
    {
        /** @var CreationPayload $payload */

        // 1. 核销优惠券:以订单上最终落下的券和抵扣额为准(订单记录已创建,各插件也都在这之前写好了券字段)
        //    - 减了钱的那张券才核销:以前「锁了就核销」,门槛不够 / 插件不支持用券时券被白白用掉;
        //    - 预览自动选券时订单减了钱,这里一定核销到那张券;
        //    - 条件更新防并发:两单同时用一张券,后一单更新不到行、整单回滚。
        $order = $payload->order;
        $usedCouponId = (int)($order->user_coupon_id ?? 0);
        if ($usedCouponId > 0 && bccomp((string)($order->coupon_cut_price ?? '0'), '0', 2) > 0) {
            $n = \app\models\UserCoupon::updateAll(
                ['status' => 1, 'used_at' => time()],
                ['id' => $usedCouponId, 'user_id' => (int)$payload->user->id, 'status' => 0]
            );
            if ($n !== 1) {
                throw new \Exception(\app\helpers\NounHelper::parse('coupon') . '已被使用或失效');
            }
        }

        // 2. 扣除积分
        // 注意：本 stage 在 CreateOrderRecordStage 之后执行，订单已创建，
        // 必须从 $payload->order 读取真实订单号，而非 previewData（预览阶段无订单号）
        $isUseIntegral = !empty($payload->data['is_use_integral']) ? 1 : 0;
        if ($isUseIntegral == 1 && ($payload->previewData['total_use_integral'] ?? 0) > 0) {
            $orderNo = $payload->order->order_no ?? '';
            if ($orderNo === '') {
                // 幂等键依赖订单号,拿不到就不能静默用常量键(会跨订单串幂等)
                throw new \Exception('扣除积分失败:订单号缺失');
            }
            // 走核心积分网关:业务类型 order_deduct;补幂等键(原先无键,管线重试可重复扣分)
            (new IntegralService())->consume(
                $payload->user->id,
                $payload->previewData['total_use_integral'],
                'order_deduct',
                [
                    'idempotencyKey' => 'order_deduct_' . $orderNo,
                    'title'          => '订单下单抵扣',
                    'content'        => '抵扣订单:' . $orderNo,
                    'order_no'       => $orderNo,
                ]
            );
        }

        // 3. 商户首单立减:名额和「每人一次」的资格在这里占住(原来付款后才扣,中间什么都不占)。
        //    占不到就抛异常,整单回滚 —— 见 FirstOrderQuotaService
        \app\plugins\mch\services\FirstOrderQuotaService::reserve($payload->order);

        return $next($payload);
    }
}
