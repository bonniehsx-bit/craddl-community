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

namespace app\services\finance;

/**
 * 提现到账计算 —— 纯计算、无副作用、可单测。
 *
 * 公式：
 *   手续费 = 提现额 × feeRate
 *   税     = 提现额 × taxRate
 *   实际到账 = 提现额 - 手续费 - 税（不为负，费率之和>1时兜底 0）
 *
 * 全程 bc 定点运算（精度 2），避免浮点误差；费率为小数（0.006 表示 0.6%）。
 *
 * 设计意图：各处「提现/打款到账」金额计算应统一走这里，便于口径一致 + 单元测试覆盖
 * （算钱逻辑算错直接资损，是最该有测试网的地方）。
 */
class WithdrawCalculator
{
    /**
     * @param string|float|int $amount  提现申请金额（元）
     * @param float            $feeRate 手续费率，小数（0.006 = 0.6%）
     * @param float            $taxRate 税率，小数
     * @return array{amount:string,fee:string,tax:string,actual:string} 均为 2 位精度字符串
     */
    public static function calc($amount, float $feeRate = 0.0, float $taxRate = 0.0): array
    {
        $amount = is_numeric($amount) ? (string)$amount : '0';
        if (bccomp($amount, '0', 2) < 0) {
            $amount = '0'; // 负额防御
        }
        $feeRate = $feeRate > 0 ? $feeRate : 0.0;
        $taxRate = $taxRate > 0 ? $taxRate : 0.0;

        $amount = bcadd($amount, '0', 2);               // 规整到 2 位
        $fee    = bcmul($amount, (string)$feeRate, 2);
        $tax    = bcmul($amount, (string)$taxRate, 2);
        $actual = bcsub(bcsub($amount, $fee, 2), $tax, 2);
        if (bccomp($actual, '0', 2) < 0) {
            $actual = '0.00';                           // 费率之和 > 1 时到账兜底为 0
        }

        return [
            'amount' => $amount,
            'fee'    => $fee,
            'tax'    => $tax,
            'actual' => $actual,
        ];
    }
}
