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
namespace app\services\order\pipeline;

use app\services\order\context\OrderContext;
use app\services\order\context\DiscountConfig;
use app\services\order\context\BasePricing;
use app\services\order\context\DiscountResult;

class PreviewPayload
{
    /** @var array 原始请求参数 */
    public $requestData;
    /** @var array 订单详情临时挂载(兼容旧版插件) */
    public $orderDetailList = [];
    /** @var \app\models\User|null 当前用户 */
    public $user;

    /** @var OrderContext 订单上下文 */
    public $context;
    /** @var DiscountConfig 优惠配置 */
    public $discountConfig;
    /** @var BasePricing 基础定价结果 */
    public $basePricing;
    /** @var DiscountResult 最终优惠结果 */
    public $discountResult;

    /** @var array 插件扩展数据挂载点，避免 PHP 8.2 动态属性警告 */
    public $extensionData = [];

    /** @var array 最终输出给前端的响应数据 */
    public $responseData = [];

    public function __construct(array $requestData, $user)
    {
        $this->requestData = $requestData;
        $this->user = $user;
    }
}