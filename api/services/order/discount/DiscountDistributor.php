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

namespace app\services\order\discount;

/**
 * 优惠分摊器 - 将优惠金额精确分配到商品明细
 * * 核心算法改进（动态剩余基数算法）：
 * 1. 【净价基数】基于上一次优惠扣减后的“净价”进行分摊，杜绝已被抵扣至0元的商品继续参与分摊。
 * 2. 【动态分摊】采用“剩余优惠 × (当前净价/剩余总净价)”计算，自然平滑精度差异。
 * 3. 【尾差兜底】确保强截断场景下，漏掉的分摊金额会被重新分配，保证财务对账1分钱不丢。
 */
class DiscountDistributor
{
    /**
     * 将优惠分摊到指定商品
     * * @param array $items 商品列表(引用传递，会修改)
     * @param string $totalDiscount 总优惠金额
     * @param string $fieldKey 要填充的字段名(如 'coupon_cut_price')
     * @param array $applicableGoodsIds 适用的商品ID列表(空数组表示全部适用)
     * @return void
     */
    public static function distribute(
        array &$items,
        string $totalDiscount,
        string $fieldKey,
        array $applicableGoodsIds = []
    ): void {
        // 如果优惠金额小于等于0，直接跳过
        if (bccomp($totalDiscount, '0.00', 2) <= 0) {
            return;
        }

        $candidates = [];
        $remainingTotalNetPrice = '0.00';

        // 1. 筛选商品并使用【当前净价】作为计算基数
        foreach ($items as $index => $item) {
            // 检查是否在适用范围内
            if (!empty($applicableGoodsIds) && !in_array($item['goods_id'], $applicableGoodsIds)) {
                continue;
            }

            // 获取商品当前（经过之前各层策略扣减后）的净价
            $netPrice = self::getNetPrice($item);

            // 只有净价还有余额的商品才参与分摊
            if (bccomp($netPrice, '0.00', 2) > 0) {
                $candidates[] = [
                    'index' => $index,
                    'net_price' => $netPrice
                ];
                $remainingTotalNetPrice = bcadd($remainingTotalNetPrice, $netPrice, 2);
            }
        }

        // 如果没有可用商品，或可用净价为0，直接退出
        if (empty($candidates) || bccomp($remainingTotalNetPrice, '0.00', 2) <= 0) {
            return;
        }

        // 防御性拦截：分摊的优惠金额绝对不能超过参与商品的总剩余净价
        if (bccomp($totalDiscount, $remainingTotalNetPrice, 2) === 1) {
            $totalDiscount = $remainingTotalNetPrice;
        }

        $remainingDiscount = $totalDiscount;
        $count = count($candidates);

        // 2. 动态剩余基数分摊逻辑
        foreach ($candidates as $idx => $candidate) {
            $itemIndex = $candidate['index'];
            $netPrice = $candidate['net_price'];

            if ($idx == $count - 1) {
                // 最后一个商品吃下所有的剩余优惠（正常情况下的尾差归集）
                $currentDiscount = $remainingDiscount;
            } else {
                // 动态比例算法 (当前净价 / 剩余总净价) * 剩余待分摊优惠
                $ratio = bcdiv($netPrice, $remainingTotalNetPrice, 8);
                $currentDiscount = bcmul($remainingDiscount, $ratio, 2);
            }

            // 溢出拦截：当前分得的优惠不能超过自身的剩余净价
            if (bccomp($currentDiscount, $netPrice, 2) === 1) {
                $currentDiscount = $netPrice;
            }

            // 写入/累加优惠字段
            if (!isset($items[$itemIndex][$fieldKey])) {
                $items[$itemIndex][$fieldKey] = '0.00';
            }
            $items[$itemIndex][$fieldKey] = bcadd($items[$itemIndex][$fieldKey], $currentDiscount, 2);

            // 游标递减：扣除刚刚分摊掉的金额和基数
            $remainingDiscount = bcsub($remainingDiscount, $currentDiscount, 2);
            $remainingTotalNetPrice = bcsub($remainingTotalNetPrice, $netPrice, 2);
        }

        // 3. 尾差兜底补偿
        // 极其罕见的情况：如果因为 bccomp 精度强制截断，导致最终 remainingDiscount 依然没分完
        // 把多出来的尾差重新塞给还有余额的其他商品，确保分摊总额绝对等于优惠总额
        if (bccomp($remainingDiscount, '0.00', 2) === 1) {
            self::redistributeLeftover($items, $candidates, $remainingDiscount, $fieldKey);
        }
    }

    /**
     * 将强制截断后多出来的尾差，重新分配给还能吃得下余额的商品 (兜底安全网)
     * * @param array $items
     * @param array $candidates
     * @param string $leftover
     * @param string $fieldKey
     */
    private static function redistributeLeftover(array &$items, array $candidates, string $leftover, string $fieldKey): void
    {
        foreach ($candidates as $candidate) {
            if (bccomp($leftover, '0.00', 2) <= 0) {
                break; // 尾差全部分配完毕
            }

            $itemIndex = $candidate['index'];
            $currentNetPrice = self::getNetPrice($items[$itemIndex]); // 重新获取最新净价

            if (bccomp($currentNetPrice, '0.00', 2) === 1) {
                // 这个商品还能吃下多少尾差 (取 剩余尾差 和 剩余净价 中的最小值)
                $canTake = (bccomp($leftover, $currentNetPrice, 2) === 1) ? $currentNetPrice : $leftover;

                $items[$itemIndex][$fieldKey] = bcadd($items[$itemIndex][$fieldKey], $canTake, 2);
                $leftover = bcsub($leftover, $canTake, 2);
            }
        }
    }

    /**
     * 获取商品已累计的各项优惠总额
     * * @param array $item 商品项
     * @return string
     */
    public static function getTotalDiscount(array $item): string
    {
        $total = '0.00';

        $discountFields = [
            'coupon_cut_price',              // 优惠券（平台券+商家券）
            'first_order_discount_price',    // 首单立减（商家承担）
            'integral_cut_price',            // 积分抵扣
            'pickup_discount_price',         // 自提优惠
            'platform_subsidy_random_minus', // 支付随机减（平台补贴，反风控）
        ];

        foreach ($discountFields as $field) {
            if (isset($item[$field])) {
                $total = bcadd($total, $item[$field], 2);
            }
        }

        return $total;
    }

    /**
     * 获取商品的净价(原价 - 所有已被分配的优惠)
     * * @param array $item 商品项
     * @return string
     */
    public static function getNetPrice(array $item): string
    {
        $totalDiscount = self::getTotalDiscount($item);

        // 净价 = 商品总价 - 已分配总优惠
        $netPrice = bcsub($item['total_price'], $totalDiscount, 2);

        // 兜底：净价不能为负数
        return bccomp($netPrice, '0.00', 2) === -1 ? '0.00' : $netPrice;
    }
}
