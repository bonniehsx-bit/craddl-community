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
 * 提现统计事件(核心问、插件答)
 *
 * 解决的问题:核心多处要展示「提现中 / 已提现 / 待审核单数」——
 * 后台首页卡片、C 端首页、管理中心、佣金中心。但提现单落在 withdraw 插件自己的表里,
 * 核心不该去 `new app\plugins\withdraw\models\WithdrawOrder` 或裸查 `{{%plugin_withdraw_order}}`:
 * 那样插件一卸载核心就 500(老 cash 表删除时 /api/user/index 就是这么炸的),
 * 且核心代码里散落插件类名/表名,与「主框架不依赖插件」的设计原则相悖。
 *
 * 所以反过来:**核心派发本事件把上下文抛出来,谁有提现数据谁接**。
 *   - withdraw 插件装了 → 监听器一条条件聚合 SQL 算出三个值写回;
 *   - 插件没装 / 没数据 → 三个字段保持 null,调用方按 `?? 0` 兜底,不报错。
 *
 * 与 IntegralAccrualQuoteEvent、Goods::EVENT_CALC_PRICE 同一范式:
 * 核心定义钩子,插件写值,调用方读值,双方互不认识。
 *
 * ⚠ 监听器约定:**必须用 addXxx() 累加,不可直接给字段赋值**。
 *   将来若出现第二个提现来源(如商家提现独立插件),两个监听器要能叠加;
 *   直接赋值会让后注册的那个把前一个的结果冲掉。
 *
 * 用法(核心调用方):
 *   $e = new WithdrawStatEvent(['mallId' => $mallId, 'userId' => $userId]);
 *   Yii::$app->trigger(WithdrawStatEvent::NAME, $e);
 *   $cashing = $e->pendingAmount ?? 0;
 *   $cashed  = $e->completedAmount ?? 0;
 */
class WithdrawStatEvent extends Event
{
    const NAME = 'withdraw.stat.collect';

    /** 商城 ID(必填) */
    public $mallId = 0;

    /** 用户 ID;0 = 不限用户(平台级统计,如后台首页待审核单数) */
    public $userId = 0;

    /** 资产类型(balance/price/integral/钱包 key);'' = 全部资产 */
    public $assetType = '';

    // ── 以下为应答字段,null 表示「无人应答」,区别于「有人应答但结果是 0」 ──

    /** 在途金额:待审核 + 待打款 + 打款中 */
    public $pendingAmount = null;

    /** 已到账金额 */
    public $completedAmount = null;

    /** 待审核单数(平台级卡片用) */
    public $pendingCount = null;

    public function addPending(float $v): void
    {
        $this->pendingAmount = (float) ($this->pendingAmount ?? 0) + $v;
    }

    public function addCompleted(float $v): void
    {
        $this->completedAmount = (float) ($this->completedAmount ?? 0) + $v;
    }

    public function addPendingCount(int $v): void
    {
        $this->pendingCount = (int) ($this->pendingCount ?? 0) + $v;
    }
}
