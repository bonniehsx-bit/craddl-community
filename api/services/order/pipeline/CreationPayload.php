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
use app\models\CommonPayOrder;
use app\handlers\order\BaseOrderHandler;

class CreationPayload
{
    public $form;
    public $user;
    public $mall_id;
    public $data;          // 解析后的业务数据
    public $previewData;   // 预览接口返回的数据

    // 事务资源锁
    public $goodsMap = [];
    public $attrMap = [];
    public $selectedCoupon = null;

    /** @var BaseOrderHandler */
    public $mainHandler;   // 主订单处理器

    /** @var Order */
    public $order;         // 订单模型

    /** @var CommonPayOrder */
    public $payOrder;      // 支付单模型

    public $response = []; // 最终返回结果

    public function __construct($form, $user)
    {
        $this->form = $form;
        $this->user = $user;
        $this->mall_id = $user->mall_id;
    }
}
