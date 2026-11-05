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

namespace app\services\order\discount\strategies;

use app\helpers\OptionHelper;
use app\services\order\context\DiscountConfig;
use app\services\order\context\OrderContext;
use app\services\order\discount\DiscountDistributor;

/**
 * 平台随机立减策略
 */
class RandomMinusStrategy implements DiscountStrategy
{
    public function getName(): string
    {
        return 'random_minus';
    }
    
    public function isApplicable(OrderContext $context, DiscountConfig $config): bool
    {
        $setting = OptionHelper::get('random_minus_rules', $context->mallId, []);
        return !empty($setting['is_enabled']);
    }
    
    public function calculate(
        string $remainingAmount,
        array &$items,
        OrderContext $context,
        DiscountConfig $config
    ): array {
        // 已锁定(新版前端回传了有效正数)→ 直接用,保证"看到多少付多少"
        if ($config->lockedRandomMinus !== null) {
            $randomAmount = $config->lockedRandomMinus;
        } else {
            // 否则按"每单确定性"算:同一用户 + 同一笔可减基数,preview 与 submit 必得同值。
            // 不依赖前端回传锁定值,旧客户端(传 0/不回填)也能稳定生效且预览金额==实际支付。
            $userId = $context->user ? (int)$context->user->id : 0;
            $randomAmount = $this->calculateRandomAmount($remainingAmount, $context->mallId, $userId);
        }

        // 不能超过剩余金额
        if (bccomp($randomAmount, $remainingAmount, 2) === 1) {
            $randomAmount = $remainingAmount;
        }

        // 分摊到商品（写入独立的随机减字段，与平台券、首单立减彻底解耦）
        DiscountDistributor::distribute(
            $items,
            $randomAmount,
            'platform_subsidy_random_minus',
            []
        );

        return [
            'amount' => $randomAmount,
        ];
    }
    
    private function calculateRandomAmount(string $orderAmount, int $mallId, int $userId = 0): string
    {
        $setting = OptionHelper::get('random_minus_rules', $mallId, []);

        if (empty($setting['tiers']) || !is_array($setting['tiers'])) {
            return '0.00';
        }

        $minusAmountMax = '0.00';
        foreach ($setting['tiers'] as $tier) {
            if (bccomp($orderAmount, $tier['order_amount_max'], 2) <= 0) {
                $minusAmountMax = $tier['minus_amount_max'];
                break;
            }
        }

        if ($minusAmountMax <= 0) {
            return '0.00';
        }

        $maxInt = (int)bcmul($minusAmountMax, '100');
        if ($maxInt <= 0) {
            return '0.00';
        }

        // 【每单确定性】种子 = mallId + 用户ID + 本单可减基数。
        // 同一单 preview 与 submit 入参一致 → crc32 得同一整数 → 随机减金额必然相同,
        // 无需依赖前端回传锁定值即可保证"预览所见 == 实际支付";
        // 不同订单(金额不同)/不同用户自然得到不同值,反风控的"金额加扰"仍成立。
        // crc32 在 64 位 PHP 返回非负整数,32 位下可能为负,统一 abs 兜底。
        $seed = $mallId . '|' . $userId . '|' . $orderAmount;
        $randomInt = abs(crc32($seed)) % ($maxInt + 1);

        return bcdiv((string)$randomInt, '100', 2);
    }
}
