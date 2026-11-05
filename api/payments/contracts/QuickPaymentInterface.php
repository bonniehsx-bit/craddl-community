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

namespace app\payments\contracts;

/**
 * 快捷支付(银行卡)网关契约
 *
 * 所有"用户绑卡后输入短信验证码完成支付"的网关都要 implements 本接口。
 * PayForm / BankCardController / bankcard Form 通过 instanceof 判定是否支持快捷支付,
 * 再反射到具体实现,避免前端和业务层感知具体支付公司名(快付 / 汇付 / 杉德 / ...).
 *
 * 下单/退款/回调/查询仍然走 BasePayment 的抽象方法,本接口只约定快捷支付特有的"卡管理+短信确认"5 个动作。
 */
interface QuickPaymentInterface
{
    /**
     * 绑卡申请 —— 对应第三方的 CARD_APPLY 类接口
     * 实现方通常会对 bankCardNo/identityNo 等敏感字段做 RSA 公钥加密后再上送
     *
     * @param array $params 通用字段: user_id, userName, identityNo, identityType, bankCardNo, mobile,
     *                       cDFlag(D 储蓄/C 信用), validDate, cvv2, idDocPeriodBegin, idDocPeriodEnd
     * @return array 至少包含: status(SUCCESS/DOING/FAIL) / bindCardId(DOING/SUCCESS 时返回) / desc
     */
    public function applyBindCard(array $params);

    /**
     * 绑卡确认 —— 用户收到短信后提交验证码
     *
     * @param string $bindCardId 网关侧的绑卡凭证 (applyBindCard 返回)
     * @param string $smsCode    短信验证码
     * @return array 至少包含: status / desc / bankName / bankCode
     */
    public function verifyBindCard($bindCardId, $smsCode);

    /**
     * 解绑 —— 通知网关侧失效该绑卡凭证
     * 即使远端失败,业务层也会允许本地软删,以避免"死卡"卡位
     *
     * @param string $bindCardId
     * @return array
     */
    public function unbindCard($bindCardId);

    /**
     * 支付确认 —— 对应第三方的 TRANS_VERIFY 类接口
     * 在 placeOrder 同步返回 DOING/PROCESS 之后,用户输入短信验证码再调本方法完成扣款
     *
     * @param string $orderNo 商户订单号 (与 placeOrder 使用的 order_no 一致)
     * @param string $smsCode 短信验证码
     * @return array 至少包含: status(SUCCESS/DOING/FAIL) / atuOrderNo / channelFlowNo
     */
    public function verifyPayment($orderNo, $smsCode);
}
