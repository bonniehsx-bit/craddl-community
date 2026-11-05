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

namespace app\plugins\withdraw\services;

/**
 * 提现到账计算器（纯函数，bc 定点，精度2，可单测）
 *
 *   手续费 = 申请额 × 费率
 *   税费   = 申请额 × 税率
 *   实际到账 = 申请额 - 手续费 - 税费
 *
 * 用户扣减的是「申请额」全额，手续费+税费是平台留存，实际到账打款给用户。
 * 禁止浮点/round —— 算钱一律 bc 定点。
 */
class AmountCalculator
{
    /**
     * @param string|float $amount  申请金额
     * @param string|float $feeRate 手续费率（小数，如 0.006）
     * @param string|float $taxRate 税率（小数）
     * @return array{amount:string,fee:string,tax:string,actual:string}
     */
    public static function calc($amount, $feeRate = 0, $taxRate = 0): array
    {
        $amount = is_numeric($amount) ? (string)$amount : '0';
        if (bccomp($amount, '0', 2) < 0) {
            $amount = '0';
        }
        $feeRate = is_numeric($feeRate) && $feeRate > 0 ? (string)$feeRate : '0';
        $taxRate = is_numeric($taxRate) && $taxRate > 0 ? (string)$taxRate : '0';

        $amount = bcadd($amount, '0', 2);
        $fee    = bcmul($amount, $feeRate, 2);
        $tax    = bcmul($amount, $taxRate, 2);
        $actual = bcsub(bcsub($amount, $fee, 2), $tax, 2);
        if (bccomp($actual, '0', 2) < 0) {
            $actual = '0.00';
        }

        return ['amount' => $amount, 'fee' => $fee, 'tax' => $tax, 'actual' => $actual];
    }
}
