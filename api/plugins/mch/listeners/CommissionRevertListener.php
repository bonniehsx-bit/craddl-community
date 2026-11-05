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

namespace app\plugins\mch\listeners;

use app\events\CommissionRevertEvent;
use app\listeners\BaseListener;
use app\models\CommissionLog;
use app\plugins\mch\models\MchCommissionType;
use app\services\CommissionService;
use Yii;

/**
 * 商家货款/运费的分润统一回滚(大一统信号 order.commission.revert)。
 *
 * 【为什么必须有】mch 此前**从未订阅这个信号**,而结算后退款是常态路径:
 *   · 自提核销即时结算(OrderShipmentService::verifyPickup → orderSettle($order, true),不等售后期);
 *   · order_settle_day=0 的商城,订单完成当天就打款。
 * 之后客服强制取消/整单退款 → 核心发出本信号 → 没人接 → 商家白拿全额货款+运费(资损)。
 *
 * 【核心那段兜底循环为什么不够】RefundSuccessDispatcher::handleAfterSales 里的手工扣回:
 *   · 只按 order_detail_id 找日志 → 运费流水(order_detail_id=0)永远捞不到;
 *   · 只处理 price / balance,没有 else → mch_settle_target 配成扩展钱包(如 mch.funds)时钱不退,
 *     偏偏末尾还统一把日志刷成 INVALID,于是"钱没退、账面显示已作废",事后对账都查不出来;
 *   · 只覆盖售后单路径,整单取消(OrderSettlementService::cancel)那条完全没人管。
 * 走 CommissionService::revert() 则按 payout_type 反向冲账,price/balance/积分/任意扩展钱包全覆盖。
 *
 * 【口径】与其它分润插件一致(见 docs/订单事件与分润标准规范.md):
 *   · 只回滚 type=reward 的流水(商家货款+运费),不碰 mch_recruit 及别的插件;
 *   · order_detail_id=0 → 整单回滚,会一并捞到运费流水(它的 order_detail_id 本就是 0,语义正确:
 *     运费属于整单而非某条明细);>0 → 只回滚该明细的货款,**不动运费**(部分退款不该退运费);
 *   · 幂等由 CommissionService::revert() 内部的原子状态切换保证 —— 与核心那段兜底循环无论谁先跑,
 *     后跑的一方都会因为 status 已不在 [PENDING, SETTLED] 而空转,不会重复扣钱。
 */
class CommissionRevertListener extends BaseListener
{
    public function register()
    {
        Yii::$app->on(CommissionRevertEvent::NAME, [$this, 'onCommissionRevert']);
    }

    public function onCommissionRevert(CommissionRevertEvent $event)
    {
        $query = CommissionLog::find()
            ->where([
                'mall_id'   => $event->mall_id,
                'order_id'  => $event->order_id,
                'type'      => MchCommissionType::REWARD, // 只回滚商家货款/运费
                'is_delete' => 0,
            ])
            ->andWhere(['in', 'status', [CommissionLog::STATUS_PENDING, CommissionLog::STATUS_SETTLED]]);

        if ($event->order_detail_id > 0) {
            $query->andWhere(['order_detail_id' => $event->order_detail_id]);
        }

        $logs = $query->all();
        if (!$logs) {
            return;
        }

        $commissionService = new CommissionService();
        foreach ($logs as $log) {
            try {
                $commissionService->revert($log);
            } catch (\Throwable $e) {
                // 单条失败不阻断其余回滚,也不冒泡打断退款主流程
                Yii::error("[商家结算] 分润回滚单条失败 LogID:{$log->id} OrderID:{$event->order_id} " . $e->getMessage());
            }
        }
    }
}
