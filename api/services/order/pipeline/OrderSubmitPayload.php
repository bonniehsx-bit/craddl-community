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

use app\models\Order;

class OrderSubmitPayload
{
    public $form;
    public $user;
    public $mall_id;

    // 过程中产生的数据
    public $data = [];             // 解析后的表单 JSON 数据
    public $previewData = [];      // 算好的预览数据
    public $lockedGoodsMap = [];   // 行锁锁定的商品
    public $lockedAttrMap = [];    // 行锁锁定的规格
    public $selectedCoupon = null; // 锁定的优惠券

    /** @var Order */
    public $order;                 // 最终生成的主订单模型
    public $payId = 0;             // 支付中心ID
    public $payKey = '';           // 支付中心Key

    // 响应结果封装
    public $responseCode = \app\core\ApiCode::CODE_SUCCESS;
    public $responseMsg = 'success';
    public $responseData = [];

    public function __construct($form, $user, $mall_id)
    {
        $this->form = $form;
        $this->user = $user;
        $this->mall_id = $mall_id;
    }
}