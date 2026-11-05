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

namespace app\services\order;

/** 下单数量与计价结果的公共边界，预览、异步建单和库存操作共用。 */
class OrderInputGuard
{
    // 订单明细、购物车和库存使用 MySQL 有符号 INT。
    const MAX_QUANTITY = 2147483647;

    public static function assertQuantity($quantity): void
    {
        // 不先 intval：避免小数、布尔值、科学计数法等被静默转成合法数量。
        if ((!is_int($quantity) && !is_string($quantity))
            || !preg_match('/^[0-9]+$/D', (string)$quantity)) {
            throw new \InvalidArgumentException('商品数量必须为大于 0 的整数');
        }
        $digits = ltrim((string)$quantity, '0');
        $max = (string)self::MAX_QUANTITY;
        if ($digits === '' || strlen($digits) > strlen($max)
            || (strlen($digits) === strlen($max) && strcmp($digits, $max) > 0)) {
            throw new \InvalidArgumentException('商品数量必须为大于 0 且不超过 ' . $max . ' 的整数');
        }
    }

    public static function assertItems($items): void
    {
        if (!is_array($items) || !$items || array_keys($items) !== range(0, count($items) - 1)) {
            throw new \InvalidArgumentException('商品列表格式错误或为空');
        }
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('商品明细格式错误');
            }
            self::assertQuantity($item['num'] ?? null);
        }
    }

    public static function assertAmount($amount): void
    {
        if ((!is_int($amount) && !is_float($amount) && !is_string($amount))
            || !is_numeric($amount) || !is_finite((float)$amount) || (float)$amount < 0) {
            throw new \InvalidArgumentException('订单金额异常，请重新选择商品');
        }
    }

    public static function assertOrderAmounts($order): void
    {
        foreach (['goods_total_price', 'express_price', 'total_price', 'pay_price'] as $field) {
            self::assertAmount($order->$field);
        }
    }
}
