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
 * 「可切换收款主体」的支付网关契约
 *
 * 有些聚合支付网关支持在下单时把收款主体从「平台应用」切换成「商户进件应用」
 * （商户在支付公司侧完成进件后拿到 applicationId），钱直接进商户账户，平台再走分账。
 * 收银台的 flow_to=split 模式强依赖这个能力。
 *
 * 判定方式与既有的 QuickPaymentInterface 一致：业务层 instanceof 判定，
 * 不感知具体支付公司名（快付 / 企业微信 / ...），新增网关只要实现本接口即可被选中。
 *
 * ⚠️ 这三件事必须同时成立，网关才算真正支持：
 *   1. implements 本接口；
 *   2. placeOrder() 接受第二形参 $mchConfig（否则切换出来的配置递不进去）；
 *   3. 网关侧真的支持代收（applicationId 语义）。
 * 只有 1 而没有 2 的类会在下单时静默用回平台配置 —— 故实现方必须同时具备 2。
 */
interface MerchantApplicationPaymentInterface
{
    /**
     * 基于平台配置派生出「以该商户为收款主体」的配置，供 placeOrder($orderData, $config) 使用。
     *
     * @param array  $config        平台配置，通常来自 $this->getConfig()
     * @param string $applicationId 商户在支付公司侧的进件应用 ID（Mch::other_application_id）
     * @return array 可直接作为 placeOrder 第二形参的配置
     * @throws \InvalidArgumentException 商户未进件 / applicationId 为空
     */
    public function withMerchantApplication(array $config, string $applicationId): array;
}
