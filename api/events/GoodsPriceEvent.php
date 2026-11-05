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

namespace app\events;

use app\models\Goods;
use yii\base\Event;

/**
 * 商品动态定价事件
 * 插件通过监听 Goods::EVENT_CALC_PRICE 来注入自定义价格
 * 设置 $finalPrice 即表示接管定价
 */
class GoodsPriceEvent extends Event
{
    /** @var Goods 当前商品 */
    public $goods;

    /** @var float 原始基础价格 */
    public $basePrice;

    /** @var int 当前用户ID */
    public $userId;

    /** @var float|null 插件计算后的最终价格，null=不干预 */
    public $finalPrice = null;
}
