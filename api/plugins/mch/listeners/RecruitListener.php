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
use app\events\GoodsEvent;
use app\events\OrderEvent;
use app\helpers\OptionHelper;
use app\listeners\BaseListener;
use app\models\CommissionLog;
use app\models\Order;
use app\models\OrderDetail;
use app\models\User;
use app\plugins\mch\forms\mall\GoodsRecruitForm;
use app\plugins\mch\models\Mch;
use app\plugins\mch\models\MchCommissionType;
use app\plugins\mch\models\MchRecruitGoods;
use app\services\CommissionService;
use Yii;

/**
 * 商家推荐奖监听器
 *
 *  - GOODS_EDIT_FORM_LOAD / GOODS_AFTER_SAVE：商品编辑面板「商家推荐奖」Tab 的读写
 *  - EVENT_ORDER_PAID    ：订单支付 → 创建待结算分红记录（CommissionLog，pending）
 *  - EVENT_ORDER_SETTLED ：订单完成 → 把待结算记录真正发放（settle）
 *  - commission.revert   ：取消/售后统一回滚 → 中台 revert（未结算作废 / 已结算追回，按 order_detail_id 分流）
 *
 * 规则（初版，简单）：
 *  - 仅商品开启了商家推荐奖开关（is_enable=1）才参与
 *  - 卖家必须是商户（goods.mch_id > 0）
 *  - 推荐商户 = 卖家商户店长 User.parent_id 对应的用户，且该用户本身也拥有商户
 *  - 分红 = 订单明细 total_price × rate%，进推荐商户店长 User.price
 */
class RecruitListener extends BaseListener
{
    public function register()
    {
        Yii::$app->on(GoodsEvent::GOODS_EDIT_FORM_LOAD, [$this, 'editLoad']);
        Yii::$app->on(GoodsEvent::GOODS_AFTER_SAVE, [$this, 'goodsSave']);
        Yii::$app->on(Order::EVENT_ORDER_PAID, [$this, 'onOrderPaid']);
        Yii::$app->on(Order::EVENT_ORDER_SETTLED, [$this, 'onOrderSettled']);
        // 大一统:回滚只认统一信号 commission.revert(覆盖取消+售后,按 order_detail_id 分流),不再单独监听 order.canceled。
        Yii::$app->on(CommissionRevertEvent::NAME, [$this, 'onCommissionRevert']);
    }

    /**
     * 商家推荐奖总开关是否开启（mch_setting.recruit_enable）。
     * 关闭时：不挂「商家推荐奖」Tab、不保存其配置、订单也不产生商家推荐奖。
     * 兼容存值为 int(1) / 字符串("1") / 缺省，统一用 !empty 判定。
     */
    private function recruitEnabled(int $mallId): bool
    {
        if ($mallId <= 0) {
            return false;
        }
        $setting = OptionHelper::get('mch_setting', $mallId, []);
        return !empty($setting['recruit_enable']);
    }

    public function editLoad(GoodsEvent $event)
    {
        // 商品所属商城（编辑/新建均在某 mall 上下文）；未开启商家推荐奖则不挂该 Tab
        $mallId = $event->goods ? (int) $event->goods->mall_id : (int) (Yii::$app->mall->id ?? 0);
        if (!$this->recruitEnabled($mallId)) {
            return;
        }
        $goodsId = $event->goods ? (int) $event->goods->id : 0;
        try {
            $form = new GoodsRecruitForm();
            $event->pluginData[] = [
                'key'            => 'mch_recruit',
                'label'          => '商家推荐奖',
                'component_name' => 'plugin-mch-recruit-goods-setting',
                'data'           => $form->getSetting($goodsId),
                'sort'           => 25,
            ];
        } catch (\Throwable $e) {
            Yii::error('商家推荐奖加载配置失败: ' . $e->getMessage());
        }
    }

    public function goodsSave(GoodsEvent $event)
    {
        // 未开启商家推荐奖则不保存其配置
        $mallId = $event->goods ? (int) $event->goods->mall_id : (int) (Yii::$app->mall->id ?? 0);
        if (!$this->recruitEnabled($mallId)) {
            return;
        }
        $pluginData = $event->pluginData;
        $data = null;
        if (isset($pluginData['mch_recruit'])) {
            $data = $pluginData['mch_recruit'];
        } elseif (is_array($pluginData)) {
            foreach ($pluginData as $item) {
                if (isset($item['key']) && $item['key'] === 'mch_recruit') {
                    $data = $item['data'] ?? $item;
                    break;
                }
            }
        }
        if ($data === null) {
            return;
        }
        try {
            (new GoodsRecruitForm())->save((int) $event->goods->id, $data);
        } catch (\Throwable $e) {
            Yii::error('商家推荐奖保存配置失败: ' . $e->getMessage());
        }
    }

    /**
     * 订单支付 → 创建待结算的商家推荐奖记录（pending，钱还没发）
     */
    public function onOrderPaid(OrderEvent $event)
    {
        $order = $event->order;
        if (!$order) {
            return;
        }
        // 未开启商家推荐奖则不产生分红记录（settle/cancel 不 gate：已生成的待结算记录仍按原流程结算/作废，避免漏发已赚的钱）
        if (!$this->recruitEnabled((int) $order->mall_id)) {
            return;
        }
        try {
            /** @var OrderDetail[] $details */
            $details = $order->orderDetails;
            if (empty($details)) {
                return;
            }

            // 幂等：本订单已生成商家推荐奖记录则跳过
            $exists = CommissionLog::find()
                ->where(['order_id' => $order->id, 'type' => MchCommissionType::RECRUIT, 'is_delete' => 0])
                ->exists();
            if ($exists) {
                return;
            }

            $commissionService = new CommissionService();

            foreach ($details as $detail) {
                $resolved = $this->resolveDividend($order, $detail);
                if ($resolved === null) {
                    continue;
                }
                // 只记录（pending），不结算 —— 等订单完成再发放
                $commissionService->log(
                    $resolved['recruiter_user_id'],
                    $resolved['money'],
                    MchCommissionType::RECRUIT,
                    CommissionService::PAYOUT_TO_PRICE,
                    [
                        'order_id'        => (int) $order->id,
                        'order_detail_id' => (int) $detail->id,
                        'buy_user_id'     => (int) $order->user_id,
                        'commission_rate' => $resolved['rate'],
                        'remark'          => '商家推荐奖：商户「' . $resolved['buyer_name'] . '」下单',
                    ]
                );
            }
        } catch (\Throwable $e) {
            Yii::error('[商家推荐奖] 支付处理失败 OrderID:' . $order->id . ' ' . $e->getMessage());
        }
    }

    /**
     * 订单完成 → 把待结算记录真正发放
     */
    public function onOrderSettled(OrderEvent $event)
    {
        $order = $event->order;
        if (!$order) {
            return;
        }
        $pendingLogs = CommissionLog::find()
            ->where([
                'order_id'  => $order->id,
                'status'    => CommissionLog::STATUS_PENDING,
                'type'      => MchCommissionType::RECRUIT,
                'is_delete' => 0,
            ])
            ->all();
        if (empty($pendingLogs)) {
            return;
        }

        $commissionService = new CommissionService();
        foreach ($pendingLogs as $log) {
            try {
                // 退款防护(按件):这件退了款 / 订单已关闭 → 作废;部分退款只作废退掉的那件,其余照常发(口径见 payoutRefundState)
                if (CommissionService::payoutRefundState(Order::findOne($order->id), (int) $log->order_detail_id) !== CommissionService::PAYOUT_OK) {
                    $commissionService->invalid($log);
                    continue;
                }
                $commissionService->settle($log);
            } catch (\Throwable $e) {
                Yii::error('[商家推荐奖] 结算单条失败 LogID:' . $log->id . ' ' . $e->getMessage());
            }
        }
    }

    /**
     * 分润统一回滚(大一统):取消(order_detail_id=0 整单)或售后(>0 该明细)都走这里。
     * 查出本插件该范围的商家推荐奖记录,逐条交给中台 revert(未结算作废 / 已结算按口径追回,内部幂等)。
     */
    public function onCommissionRevert(CommissionRevertEvent $event)
    {
        $query = CommissionLog::find()
            ->where([
                'mall_id'   => $event->mall_id,
                'order_id'  => $event->order_id,
                'type'      => MchCommissionType::RECRUIT, // 只回滚商家推荐奖,不碰其它插件记录
                'is_delete' => 0,
            ])
            ->andWhere(['in', 'status', [CommissionLog::STATUS_PENDING, CommissionLog::STATUS_SETTLED]]);
        if ($event->order_detail_id > 0) {
            $query->andWhere(['order_detail_id' => $event->order_detail_id]); // 明细级:只回滚该明细
        }
        $logs = $query->all();
        if (empty($logs)) return;

        $commissionService = new CommissionService();
        foreach ($logs as $log) {
            try {
                $commissionService->revert($log);
            } catch (\Throwable $e) {
                Yii::error('[商家推荐奖] 回滚单条失败 LogID:' . $log->id . ' ' . $e->getMessage());
            }
        }
    }

    /**
     * 解析某条订单明细的商家推荐奖（买家侧）：返回 [recruiter_user_id, money, rate, buyer_name] 或 null
     *
     * 逻辑：站在「买家」端 —— 不管商品属于谁，只要
     *   ① 该商品开了商家推荐奖（mch_recruit_goods.is_enable=1，提供分红比例）
     *   ② 下单的买家本身是商户（商家去买的）
     *   ③ 买家的上级（User.parent_id）也是商户（推荐人是商家）
     * 三者同时满足 → 上级商户按比例拿商家推荐奖。
     */
    private function resolveDividend(Order $order, OrderDetail $detail): ?array
    {
        // ① 商品的商家推荐奖比例配置
        $cfg = MchRecruitGoods::findByGoodsId((int) $detail->goods_id, (int) $order->mall_id);
        if (!$cfg || (int) $cfg->is_enable !== 1) {
            return null;
        }
        // ② 买家本身必须是商户
        $buyerUserId = (int) $order->user_id;
        if ($buyerUserId <= 0) {
            return null;
        }
        $buyerMch = Mch::findOne(['user_id' => $buyerUserId, 'is_delete' => 0]);
        if (!$buyerMch) {
            return null;
        }
        // ③ 买家的上级也必须是商户（商家推荐商家）
        $buyerUser = User::findOne($buyerUserId);
        $parentId  = $buyerUser ? (int) $buyerUser->parent_id : 0;
        if ($parentId <= 0) {
            return null;
        }
        // 平台侧状态把关:推荐奖只读 parent_id 一跳,不经过 getParents 的过滤。
        // Mch.is_delete=0 只说明商户没被删,不代表这个 User 还正常(注销/拉黑)。
        // 失去资格则该笔奖烧掉,不穿透给上级。
        if (!\app\services\UserService::isCommissionEligible($parentId)) {
            \Yii::warning("[mch-recruit] 推荐商家 #{$parentId} 状态异常(已注销/被拉黑),本单不发推荐奖", 'mch');
            return null;
        }
        $recruiterMch = Mch::findOne(['user_id' => $parentId, 'is_delete' => 0]);
        if (!$recruiterMch) {
            return null;
        }
        // 比例：rule_type=2 用商品独立比例，否则用全局比例
        if ((int) $cfg->rule_type === MchRecruitGoods::RULE_CUSTOM) {
            $rate = (float) $cfg->rate;
        } else {
            $setting = OptionHelper::get('mch_setting', (int) $order->mall_id, []);
            $rate = (float) ($setting['recruit_rate'] ?? 0);
        }
        if ($rate <= 0) {
            return null;
        }
        $money = round((float) $detail->total_price * $rate / 100, 2);
        if ($money <= 0) {
            return null;
        }
        return [
            'recruiter_user_id' => $parentId,
            'money'             => $money,
            'rate'              => $rate,
            'buyer_name'        => (string) $buyerMch->mch_name,
        ];
    }
}
