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

namespace app\forms\api\bankcard;

use app\payments\contracts\QuickPaymentInterface;
use Yii;

/**
 * 把 gateway 字符串(支付类 FQN)解析成实现了 QuickPaymentInterface 的支付实例。
 *
 * 用途:bankcard Form 层把"银行卡快捷支付"这件事做成网关无关的,
 * 不再对具体公司(快付/汇付/杉德/...)做硬拦截。任何新增网关只需
 * 新建一个 implements QuickPaymentInterface 的支付类并在后台启用对应配置即可。
 *
 * 异常语义:
 *  - 空 gateway / 无法实例化 / 未实现 QuickPaymentInterface → 抛 InvalidArgumentException
 *  - 调用方捕获并通过 ResponseHelper 返回友好错误
 */
class BankCardGatewayResolver
{
    /**
     * @param string|null $gateway 支付类完整类名
     * @param int         $mallId
     * @return QuickPaymentInterface
     * @throws \InvalidArgumentException
     */
    public static function resolve($gateway, $mallId): QuickPaymentInterface
    {
        if (empty($gateway)) {
            throw new \InvalidArgumentException('缺少支付网关参数');
        }
        if (!class_exists($gateway)) {
            throw new \InvalidArgumentException('支付网关不存在: ' . $gateway);
        }

        try {
            $payment = Yii::createObject($gateway, [$mallId]);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException($e->getMessage());
        }

        if (!$payment instanceof QuickPaymentInterface) {
            throw new \InvalidArgumentException('该支付网关不支持银行卡快捷支付');
        }

        return $payment;
    }
}
