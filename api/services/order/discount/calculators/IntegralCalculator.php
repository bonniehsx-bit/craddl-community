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

namespace app\services\order\discount\calculators;

use app\models\User;

/**
 * 积分计算器 - 复用原有逻辑
 * 
 * 积分抵扣的特殊之处：
 * 1. 不同商品有不同的积分抵扣规则
 * 2. 需要考虑用户积分余额
 * 3. 需要考虑允许抵扣的最大金额
 * 4. 分摊逻辑在计算过程中完成(不走统一分摊器)
 */
class IntegralCalculator
{
    /**
     * 业务规则：积分与人民币 1:1 兑换（1 积分 = 1 元）。
     * 老代码硬编码 100 是错误的，导致用户实际抵扣金额是真实价值的 1/100。
     *
     * 如果未来需要支持其他汇率，把这里改成从商城配置（OptionHelper）读取即可。
     */
    private const INTEGRAL_PER_YUAN = 1;

    /**
     * 计算积分抵扣并分摊到商品
     *
     * 算法核心：先确定"实际消耗积分数"，再由积分**反推**金额（用 item 自身的兑换率），
     * 避免老算法 bcmul(price, ratio, 2) 截断带来的精度丢失。
     *
     * 例：用户 12 积分 / 599 元商品(use_integral=100, type=1)
     * - 老算法：硬编码 100 积分=1元 + ratio 截断 → 输出 '0.11' 元（少了两个数量级 + 0.01 截断）
     * - 新算法：1:1 汇率，min(12, 599)=12 积分 → bcdiv('12', '1', 2) = '12.00' 元
     *
     * @param User $user
     * @param array $items 商品列表(引用传递，会被修改)
     * @param string $maxAllowDeduction 最大允许抵扣金额(订单剩余可抵金额，由 DiscountEngine 传入)
     * @return array [抵扣金额, 使用积分数]
     */
    public function calculate(User $user, array &$items, string $maxAllowDeduction): array
    {
        $userIntegral = (int) $user->integral;

        // ===== 第一步：算每个商品的"最大积分需求"和"对应金额上限" =====
        $totalMaxIntegral = 0;
        $totalMaxMoney = '0.00';

        foreach ($items as &$item) {
            $goods = $item['goods'];

            if (!$goods->is_integral) {
                $item['_int_max_money'] = '0.00';
                $item['_int_max_integral'] = 0;
                continue;
            }

            if ($goods->use_integral_type == 1) {
                // 按比例抵扣: use_integral 是允许抵扣的百分比 (0-100)
                // 商品最多抵扣金额 = 总价 × 百分比
                // 商品最多需要积分 = 抵扣金额 × 兑换率 (积分/元)
                $itemMaxMoney = bcmul(
                    $item['total_price'],
                    bcdiv((string)$goods->use_integral, '100', 4),
                    2
                );
                $itemMaxIntegral = (int) bcmul($itemMaxMoney, (string)self::INTEGRAL_PER_YUAN, 0);
            } else {
                // 固定金额抵扣: 每件用 use_integral 积分换 integral_price 元
                $itemMaxMoney = bcmul((string)$goods->integral_price, (string)$item['num'], 2);
                $itemMaxIntegral = (int)$goods->use_integral * (int)$item['num'];
            }

            $item['_int_max_money'] = $itemMaxMoney;
            $item['_int_max_integral'] = $itemMaxIntegral;
            $totalMaxIntegral += $itemMaxIntegral;
            $totalMaxMoney = bcadd($totalMaxMoney, $itemMaxMoney, 2);
        }
        unset($item);

        // 没有可抵扣商品 → 直接返回
        if ($totalMaxIntegral <= 0) {
            foreach ($items as &$item) {
                $item['use_integral'] = 0;
                $item['integral_cut_price'] = '0.00';
                unset($item['_int_max_money'], $item['_int_max_integral']);
            }
            unset($item);
            return ['0.00', 0];
        }

        // ===== 第二步：受限于订单剩余可抵金额 (maxAllowDeduction) =====
        // 如果商品总上限超过订单剩余，按比例缩 totalMaxIntegral
        if (bccomp($totalMaxMoney, $maxAllowDeduction, 2) === 1) {
            $shrinkRatio = bcdiv($maxAllowDeduction, $totalMaxMoney, 8);
            $totalMaxIntegral = (int) floor($totalMaxIntegral * (float)$shrinkRatio);
            $totalMaxMoney = $maxAllowDeduction;
        }

        // ===== 第三步：受限于用户积分余额 =====
        $realUseIntegral = min($userIntegral, $totalMaxIntegral);

        // ===== 第四步：把消耗积分按"item 上限"权重分摊，由积分反推金额 =====
        $eligibleCount = 0;
        foreach ($items as $item) {
            if ((int)($item['_int_max_integral'] ?? 0) > 0) {
                $eligibleCount++;
            }
        }

        $remainingIntegral = $realUseIntegral;
        $realTotalDeduct = '0.00';
        $realTotalIntegral = 0;
        $processed = 0;

        foreach ($items as &$item) {
            $itemMaxIntegral = (int) ($item['_int_max_integral'] ?? 0);

            if ($itemMaxIntegral <= 0) {
                $item['use_integral'] = 0;
                $item['integral_cut_price'] = '0.00';
                unset($item['_int_max_money'], $item['_int_max_integral']);
                continue;
            }

            $processed++;

            if ($processed === $eligibleCount) {
                // 最后一个: 吃下剩余积分（不超过自身上限）
                $itemIntegralUsed = min($remainingIntegral, $itemMaxIntegral);
            } else {
                // 按"item 上限 / 全局上限"权重分摊（注意: totalMaxIntegral 可能已被 step2 缩过）
                $weight = $itemMaxIntegral / max(1, $totalMaxIntegral);
                $itemIntegralUsed = (int) round($realUseIntegral * $weight);
                $itemIntegralUsed = min($itemIntegralUsed, $itemMaxIntegral, $remainingIntegral);
            }

            // 由消耗积分**反推**金额 (用 item 自身兑换率，避免 ratio*price 的浮点损失)
            $goods = $item['goods'];
            if ($goods->use_integral_type == 1) {
                // 按汇率换算: 金额 = 积分 / 兑换率
                $itemMoney = bcdiv((string)$itemIntegralUsed, (string)self::INTEGRAL_PER_YUAN, 2);
            } else {
                // 单积分价值 = integral_price / use_integral 元
                if ((int)$goods->use_integral > 0) {
                    $itemMoney = bcmul(
                        (string)$itemIntegralUsed,
                        bcdiv((string)$goods->integral_price, (string)$goods->use_integral, 6),
                        2
                    );
                } else {
                    $itemMoney = '0.00';
                }
            }

            // 防御：不超过 item 自身金额上限
            if (bccomp($itemMoney, $item['_int_max_money'], 2) === 1) {
                $itemMoney = $item['_int_max_money'];
            }

            $item['use_integral'] = $itemIntegralUsed;
            $item['integral_cut_price'] = $itemMoney;
            $realTotalDeduct = bcadd($realTotalDeduct, $itemMoney, 2);
            $realTotalIntegral += $itemIntegralUsed;
            $remainingIntegral -= $itemIntegralUsed;

            unset($item['_int_max_money'], $item['_int_max_integral']);
        }
        unset($item);

        return [$realTotalDeduct, $realTotalIntegral];
    }
}
