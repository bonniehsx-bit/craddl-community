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

namespace app\plugins\withdraw\pipeline\stages;

use app\models\Order;
use app\plugins\withdraw\models\RechargeGoods;
use app\plugins\withdraw\models\RechargePlan;
use app\services\order\pipeline\StageInterface;

/**
 * 充值订单的预览格式化。
 *
 * ── 为什么自带一个 stage，而不是复用核心 FormatPreviewResponseStage ──────────
 * ① 核心那个 stage 强依赖 `$payload->discountResult`，而它只由 ApplyDiscountStage 赋值。
 *    不挂 ApplyDiscountStage 就是读 null 属性 → 下单预览直接 500。
 * ② 但充值**不能**挂 ApplyDiscountStage —— 优惠券打到充值单上就是套利：
 *    ¥50 的券充 ¥100、到账仍按档位给 100，平台净亏 50。
 *    (即便 fillOrderData 最终按档位覆盖了价格不会真亏，预览显示 ¥50、实付 ¥100
 *     也是"预览与实付对不上"，核心那个文件的注释里就记着这类历史 bug。)
 * 所以走 membership / emotion 的路子：自己拍平一份不含任何优惠字段的响应。
 *
 * 各优惠字段仍然按 0 下发 —— 前端结算页是通用组件，字段缺失会渲染成 undefined。
 */
class FormatRechargePreviewStage implements StageInterface
{
    public function handle($payload, \Closure $next)
    {
        $context     = $payload->context;
        $basePricing = $payload->basePricing;
        $data        = $payload->requestData;

        // 金额一律以**档位**为准，不信 basePricing（占位商品价格恒为 0），
        // 也不信前端传值。与 fillOrderData 同源，保证预览 = 实付。
        $plan = RechargePlan::findOne([
            'id'        => (int)($data['plan_id'] ?? 0),
            'mall_id'   => (int)(\Yii::$app->mall->id ?? 0),
            'is_delete' => 0,
        ]);
        $total = $plan ? number_format((float)$plan->pay_amount, 2, '.', '') : '0.00';
        $cfg   = $plan ? RechargeGoods::ofGoods((int)$plan->goods_id, (int)(\Yii::$app->mall->id ?? 0)) : null;

        $payload->responseData = [
            'plugin'        => 'withdraw',
            'type'          => 'withdraw_recharge',
            'mch_id'        => $context->mchId,
            'mch'           => $context->mch,
            'goods_list'    => $basePricing->items,
            'delivery_type' => Order::DELIVER_TYPE_NONE,
            'address'       => null,

            'total_price'       => $total,
            'goods_total_price' => $total,
            'express_price'     => '0.00',

            // 充值单不参与任何优惠，全部按 0 下发（前端结算页是通用组件，缺字段会渲染成 undefined）
            'coupon_list'                 => [],
            'user_coupon_id'              => 0,
            'coupon_cut_price'            => '0.00',
            'integral_cut_price'          => '0.00',
            'first_order_discount_price'  => '0.00',
            'pickup_discount_price'       => '0.00',
            'random_minus_price'          => '0.00',
            'expected_rewards'            => ['integral' => '0.00'],

            // 充值特有：让前端能直接展示"到账多少"
            //   到账资产看**商品**配置(一个商品一个钱包),不看档位那列已作废的 asset_type
            'recharge' => $plan ? [
                'plan_id'     => (int)$plan->id,
                'plan_title'  => (string)$plan->title,
                'goods_id'    => (int)$plan->goods_id,
                'asset_type'  => $cfg ? (string)$cfg->asset_type : '',
                'asset_label' => $cfg ? $cfg->assetLabel() : '',
                'total_gain'  => $plan->totalGain(),
            ] : null,
        ];

        return $next($payload);
    }
}
