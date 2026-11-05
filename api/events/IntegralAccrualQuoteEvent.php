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

use yii\base\Event;

/**
 * 积分试算事件(按消费金额算应得积分)
 *
 * 解决的问题:小票拍照、到店核销这类「有金额、但不是标准订单」的场景,也想按统一比例发分,
 * 但它们分散在各业务插件里(plaza 等),不该让业务插件去 use 积分插件的 Resolver ——
 * 那样积分插件一卸载,业务插件就白屏。
 *
 * 所以反过来:**业务方派发本事件把金额抛出来,谁能算谁接**。
 *   - 积分插件装了且计提开关开 → 监听器按「商户比例 × 金额」算好写回 $points;
 *   - 积分插件没装 / 开关关 / 没配比例 → $points 保持 null,业务方按自己的兜底逻辑走(如人工填)。
 *
 * 与 Goods::EVENT_CALC_PRICE 同一范式:核心定义钩子,插件写值,业务方读值,双方互不认识。
 *
 * 用法(业务方):
 *   $e = new IntegralAccrualQuoteEvent();
 *   $e->mallId = $mallId; $e->mchId = $mchId; $e->amount = 128.00; $e->scene = 'plaza_receipt';
 *   Yii::$app->trigger(IntegralAccrualQuoteEvent::NAME, $e);
 *   $points = $e->points;   // null = 没人接/算不出,自己兜底
 */
class IntegralAccrualQuoteEvent extends Event
{
    const NAME = 'integral.accrual.quote';

    /** @var int 商城ID */
    public $mallId;

    /** @var int 商户ID(比例按商户>分类解析;0=无商户,通常算不出) */
    public $mchId = 0;

    /** @var float 消费金额(试算基数) */
    public $amount = 0.0;

    /** @var int 用户ID(多倍积分要判生日月等,可不传) */
    public $userId = 0;

    /** @var string 场景 key,参与「多倍适用范围」匹配(如 plaza_receipt) */
    public $scene = '';

    /** @var float|null 试算结果;null=无人应答/算不出,业务方须自行兜底 */
    public $points = null;

    /** @var float 实际生效的倍数(供业务方展示"生日月2倍"之类) */
    public $multiplier = 1.0;
}
