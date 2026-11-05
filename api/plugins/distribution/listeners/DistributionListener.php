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

namespace app\plugins\distribution\listeners;

use app\events\CommissionRevertEvent;
use app\events\OrderEvent;
use app\helpers\OptionHelper;
use app\listeners\BaseListener;
use app\models\CommissionLog;
use app\models\Level;
use app\plugins\distribution\models\DistributionCommissionType;
use app\models\Order;
use app\models\OrderDetail;
use app\models\User;
use app\plugins\distribution\models\DistributionGoods;
use app\plugins\distribution\models\DistributionSettlePlan;
use app\plugins\distribution\models\DistributionUser;
use app\services\CommissionService;
use app\services\UserService;
use yii\helpers\Json;
use Yii;

/**
 * 分销佣金引擎。
 *
 * 分销商等级 = 平台会员等级（User.level_id → Level）。插件不再自建等级，
 * 仅负责「按会员等级配分润比例」：
 *   distribution_setting.levels       —— 全局默认多级比例（一级/二级…）
 *   distribution_setting.level_rules  —— 按会员等级覆盖：{ [会员level_id]: [{level,reward_rate},...] }
 *
 * 【严格按等级】比例由「每个收款人各自所属的会员等级」决定（不是分享者）：
 *   收款人处在第 N 级 → 取其自身等级在第 N 档的比例；其等级未配置/该档留空 → 回退全局默认。
 *   故未配置进 level_rules 的等级（如 VIP）在全局默认为 0 时不参与分销。
 *   仅在「按会员等级分润」开关(is_dist_level_enabled=1)时按等级覆盖；关闭时一律用全局 levels。
 *
 * 单档位比例优先级（高→低，逐档留空回退）：
 *   a) 商品-按会员等级独立配置（by_level[收款人会员等级]）
 *   b) 商品-统一自定义（custom）
 *   c) 会员等级分润配置（level_rules[收款人会员level_id]）
 *   d) 全局默认（levels）
 *
 * 【资格是三层，别看混】
 *   ① Level.is_inviter        —— 会员等级有没有「推广权限」(等级配置)
 *   ② User.is_inviter         —— 全局「推广员身份」,由①驱动(CheckInviterJob /
 *                                UserLevelChangeForm)或后台手动授予。
 *                                它是**绑定关系链的准入条件**,属主商城概念、多个插件共用。
 *   ③ DistributionUser.is_disabled —— 已在链上的人还给不给计佣,本插件自己的运营开关。
 *
 * ⛔ 计佣时**不判**也不该判 User.is_inviter,这不是漏判:
 *   · 进链侧已经把住了 —— UserService::tryBindParent 要求候选上级 is_inviter=1;
 *   · 出链侧也把住了 —— UserForm 撤销推广员身份时,已有直推用户的一律拒绝
 *     ("请先处理他的直推用户")。
 *   两道合起来的不变式:**能出现在关系链上的人,必然仍是推广员**。
 *
 *   若在这里补一道 is_inviter 判断,反而会出错:上级一旦被降到无推广权限的等级,
 *   他名下**历史已成立的**关系链会突然全部断掉分润 —— 而按现行设计,降级只影响
 *   「以后还能不能被新人绑为上级」,不影响已经绑好的关系。
 *
 *   (库里若见到 is_inviter=0 却挂着下级的用户,那是测试脚本直插的假数据 ——
 *    如 aicare 的 Chain20_u* 20 层链 —— 不是业务路径产生的,别据此改逻辑。)
 */
class DistributionListener extends BaseListener
{
    public function register()
    {
        Yii::$app->on(Order::EVENT_ORDER_PAID, [$this, 'onOrderPaid']);
        Yii::$app->on(Order::EVENT_ORDER_SETTLED, [$this, 'onOrderSettled']);
        // 大一统:把原来的「取消(canceled)」+「售后(refund.agreed)」两条回滚路径,
        // 合并成统一信号 commission.revert(平台在取消和售后同意后各触发一次,按 order_detail_id 分流)。
        Yii::$app->on(CommissionRevertEvent::NAME, [$this, 'onCommissionRevert']);
    }

    /**
     * 订单支付：计算分销佣金 → CommissionService 记录（待结算）
     */
    public function onOrderPaid(OrderEvent $event)
    {
        $order  = $event->order;
        $mallId = $order->mall_id;

        $globalSetting = OptionHelper::get('distribution_setting', $mallId, []);
        if (empty($globalSetting['is_enable'])) {
            return;
        }

        $globalLevels = $globalSetting['levels'] ?? [];
        if (empty($globalLevels)) {
            return;
        }

        $settlementTiming = $globalSetting['settlement_timing'] ?? 'completed';
        // 延迟结算天数：>0 时压过 settlement_timing，统一由到期任务发放
        $settleDelayDays  = (int) ($globalSetting['settle_delay_days'] ?? 0);
        // 佣金落账账户：可配 收入/余额/积分,也可以是任意已授权的扩展钱包 key。
        // 该值会被 CommissionService::log 快照进流水的 extra_data.payout_type,
        // 结算和退款追回都按快照走 —— 所以后台改了账户,不会影响已经计提的老流水。
        $payoutTarget     = (string) ($globalSetting['payout_target'] ?? CommissionService::PAYOUT_TO_PRICE);
        // 按会员等级的分润覆盖：{ "会员level_id": [{level,reward_rate},...] }
        $levelRules = (array) ($globalSetting['level_rules'] ?? []);

        // 佣金计算基数：0=订单实付 1=利润 2=成本 3=商品现价
        $commissionBase = (int) ($globalSetting['commission_base'] ?? 0);

        // 收款人链（按全局层级数取深度）
        $maxLevel    = count($globalLevels);
        $parentChain = $this->getParentChain($order->user_id, $maxLevel);

        // 自购返佣：买家自己占一级，上级依次顺延（不是额外加一份，总发放比例不变）。
        // 例:开启后 买家=一级、原直推上级=二级……超出层级数的自然被截断。
        // 关闭时行为与改动前完全一致。
        if (!empty($globalSetting['self_buy'])) {
            array_unshift($parentChain, (int) $order->user_id);
            $parentChain = array_slice($parentChain, 0, $maxLevel);
        }

        if (empty($parentChain)) {
            return;
        }

        // 预加载收款人的会员等级（一次查询），及会员等级名称快照
        $chainUsers = User::find()
            ->select(['id', 'level_id'])
            ->where(['id' => $parentChain])
            ->indexBy('id')
            ->asArray()
            ->all();
        $levelIds = array_values(array_unique(array_filter(array_map(
            fn($u) => (int) ($u['level_id'] ?? 0), $chainUsers
        ))));
        $memberLevels = $levelIds
            ? Level::find()->where(['id' => $levelIds])->indexBy('id')->asArray()->all()
            : [];

        // 被取消分销资格的上级（is_disabled=1）—— 一次查询
        $disabledSet = array_flip(
            DistributionUser::find()
                ->select('user_id')
                ->where(['mall_id' => $mallId, 'user_id' => $parentChain, 'is_disabled' => 1])
                ->column()
        );

        $commissionService = new CommissionService();

        // 幂等：预加载已有分销记录（防事件重复触发）
        $existingLogs = CommissionLog::find()->where([
            'order_id'  => $order->id,
            'type'      => DistributionCommissionType::DISTRIBUTION,
            'is_delete' => 0,
        ])->select(['order_detail_id', 'level'])->asArray()->all();
        $existingMap = [];
        foreach ($existingLogs as $el) {
            $existingMap[$el['order_detail_id'] . '_' . $el['level']] = true;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            /** @var OrderDetail[] $orderDetails */
            $orderDetails = $order->orderDetails;

            // 批量预加载商品分销独立配置 + 商品名称（剿灭 N+1）
            $goodsIds = array_column($orderDetails, 'goods_id');
            $distGoodsList = DistributionGoods::find()
                ->where(['goods_id' => $goodsIds, 'is_delete' => 0])
                ->indexBy('goods_id')
                ->all();
            $goodsList = \app\models\Goods::find()
                ->where(['id' => $goodsIds])
                ->select(['id', 'name', 'price', 'cost_price'])
                ->indexBy('id')
                ->asArray()
                ->all();
            $goodsPriceList = $goodsList; // 计算基数要用到 price/cost_price

            foreach ($orderDetails as $detail) {
                $distGoods = $distGoodsList[$detail->goods_id] ?? null;

                // 商品级分销开关
                if ($distGoods && !$distGoods->is_commission_open) {
                    continue;
                }

                // 是否按会员等级分润（后台「按会员等级分润」开关）
                $byLevel = !empty($globalSetting['is_dist_level_enabled']);

                // 商品级覆盖表（预解析）：custom=统一档位表；by_level={会员level_id: 档位表}
                $goodsCustom  = null;
                $goodsByLevel = null;
                if ($distGoods && $distGoods->commission_rule_type === 'custom' && !empty($distGoods->commission_rules)) {
                    try { $goodsCustom = Json::decode($distGoods->commission_rules); } catch (\Exception $e) {}
                } elseif ($distGoods && $distGoods->commission_rule_type === 'by_level' && !empty($distGoods->level_commission_rules)) {
                    try { $goodsByLevel = Json::decode($distGoods->level_commission_rules); } catch (\Exception $e) {}
                }

                // —— 逐级计算佣金：每个收款人「按自己的会员等级」取该档位比例 ——
                // 【严格按等级】比例由收款人自身会员等级决定（不是分享者）：
                //   按等级模式 → level_rules[收款人等级][档位]；该等级未配置/该档位留空 → 回退全局默认 levels[档位]
                //   非按等级模式(is_dist_level_enabled=0) → 一律用全局 levels[档位]
                //   故 VIP 等若未配置进 level_rules，且全局默认为 0，则不发（修复 VIP 误发分销）
                // 优先级(低→高)：全局 levels → level_rules[收款人等级] → 商品 custom → 商品 by_level[收款人等级]
                foreach ($globalLevels as $levelIndex => $globalRule) {
                    if (!isset($parentChain[$levelIndex])) {
                        continue;
                    }
                    $receiverId = $parentChain[$levelIndex];

                    // 被取消分销资格的上级不参与分佣
                    if (isset($disabledSet[$receiverId])) {
                        continue;
                    }

                    $rLevelId = (int) ($chainUsers[$receiverId]['level_id'] ?? 0);

                    // 基准：全局该档位
                    $rewardRate = (isset($globalRule['reward_rate']) && is_numeric($globalRule['reward_rate']))
                        ? (float) $globalRule['reward_rate'] : 0.0;

                    // 会员等级覆盖（仅按等级模式，按收款人自己的等级；该档位留空则保持全局）
                    if ($byLevel && $rLevelId > 0) {
                        $r = $levelRules[(string) $rLevelId][$levelIndex]['reward_rate'] ?? null;
                        if ($r !== null && $r !== '' && is_numeric($r)) {
                            $rewardRate = (float) $r;
                        }
                    }

                    // 商品统一自定义覆盖（与等级无关，按档位）
                    if (is_array($goodsCustom)) {
                        $r = $goodsCustom[$levelIndex]['reward_rate'] ?? null;
                        if ($r !== null && $r !== '' && is_numeric($r)) {
                            $rewardRate = (float) $r;
                        }
                    }

                    // 商品按会员等级覆盖（按收款人自己的等级，最高优先级）
                    if (is_array($goodsByLevel) && $rLevelId > 0) {
                        $r = $goodsByLevel[(string) $rLevelId][$levelIndex]['reward_rate'] ?? null;
                        if ($r !== null && $r !== '' && is_numeric($r)) {
                            $rewardRate = (float) $r;
                        }
                    }

                    if ($rewardRate <= 0) {
                        continue;
                    }

                    $calculationPrice = $this->calcBase($detail, $goodsPriceList, $commissionBase);
                    if (bccomp((string) $calculationPrice, '0', 2) <= 0) {
                        continue; // 基数为 0（如按利润算但成本高于售价）→ 该商品行不分佣
                    }
                    $commission = bcmul((string) $calculationPrice, bcdiv((string) $rewardRate, '100', 4), 2);

                    if ($commission > 0.01) {
                        $idempotentKey = $detail->id . '_' . ($levelIndex + 1);
                        if (isset($existingMap[$idempotentKey])) {
                            continue;
                        }

                        // 自购返佣的那一笔单独标注,否则台账里会写成「1级分销」,对账时看不出是自己买自己
                        $isSelfBuy = ($receiverId === (int) $order->user_id);
                        $levelName = $isSelfBuy ? '自购' : (($levelIndex + 1) . '级');
                        $goodsName = $goodsList[$detail->goods_id]['name'] ?? '商品';

                        // 收款人自身会员等级快照
                        $rLevelName = $memberLevels[$rLevelId]['name'] ?? '';

                        $log = $commissionService->log(
                            $receiverId,
                            $commission,
                            DistributionCommissionType::DISTRIBUTION,
                            $payoutTarget,
                            [
                                'order_id'          => $order->id,
                                'order_detail_id'   => $detail->id,
                                'buy_user_id'       => $order->user_id,
                                'calculation_price' => $calculationPrice,
                                'commission_rate'   => $rewardRate,
                                'remark'            => ($isSelfBuy ? "分销佣金(自购返佣)" : "分销佣金({$levelName})") . " 单号:{$order->order_no}",
                                'level'             => $levelIndex + 1,
                                'extra_data'        => [
                                    'goods_name'        => $goodsName,
                                    'goods_price'       => $detail->total_price,
                                    'level_name'        => $isSelfBuy ? '自购返佣' : "{$levelName}分销",
                                    'is_self_buy'       => $isSelfBuy ? 1 : 0,
                                    'member_level_id'   => $rLevelId,    // 收款人会员等级ID快照
                                    'member_level_name' => $rLevelName,  // 收款人会员等级名称快照
                                    'rate_label'        => '分销比例',
                                    'rate_desc'         => '佣金 ¥' . number_format($commission, 2, '.', ''),
                                ],
                            ]
                        );

                        // T+N 优先于 settlement_timing：配了延迟天数就只登记到期计划，
                        // 由 SettleDueTask 到期发放；两条路同时走会把同一笔佣金发两次。
                        if ($settleDelayDays > 0) {
                            DistributionSettlePlan::plan($log, $settleDelayDays);
                        } elseif ($settlementTiming === 'payment') {
                            $commissionService->settle($log);
                        }
                        // settlement_timing === 'manual' 时两条路都不发，等后台手动结算
                    }
                }
            }
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::error("[分销] 支付计算失败 OrderID:{$order->id} Error:" . $e->getMessage());
        }
    }

    /**
     * 订单结算：把待结算分销佣金结算到账（settlement_timing=completed 时）
     */
    public function onOrderSettled(OrderEvent $event)
    {
        $order = $event->order;

        $globalSetting    = OptionHelper::get('distribution_setting', $order->mall_id, []);
        $settlementTiming = $globalSetting['settlement_timing'] ?? 'completed';
        // 手动档：订单完成也不自动发钱，等运营在后台逐条点
        if ($settlementTiming !== 'completed') {
            return;
        }

        $pendingLogs = CommissionLog::find()
            ->where([
                'order_id'  => $order->id,
                'status'    => CommissionLog::STATUS_PENDING,
                'type'      => DistributionCommissionType::DISTRIBUTION,
                'is_delete' => 0,
            ])
            ->all();

        // 挂了 T+N 到期计划的交给 cron 发，这里跳过，避免同一笔佣金两条路各发一次
        $plannedSet = DistributionSettlePlan::pendingLogIdSet(
            array_map(fn($l) => (int) $l->id, $pendingLogs)
        );
        if ($plannedSet) {
            $pendingLogs = array_values(array_filter(
                $pendingLogs,
                fn($l) => !isset($plannedSet[(int) $l->id])
            ));
        }
        if (empty($pendingLogs)) {
            return;
        }

        $commissionService = new CommissionService();

        // 发钱前确认禁用标志：下单时未禁用、结算时已被取消分销资格的，佣金作废不发放
        $disabledSet = DistributionUser::disabledSet((int)$order->mall_id, array_map(fn($l) => (int)$l->user_id, $pendingLogs));

        foreach ($pendingLogs as $log) {
            try {
                // 退款防护(按件):这件退了款 / 订单已关闭 → 作废;部分退款只作废退掉的那件,其余照常发(口径见 payoutRefundState)
                if (CommissionService::payoutRefundState(Order::findOne($order->id), (int)$log->order_detail_id) !== CommissionService::PAYOUT_OK) {
                    $commissionService->invalid($log);
                    continue;
                }

                // 已取消分销资格：作废佣金，不发放
                if (isset($disabledSet[(int)$log->user_id])) {
                    $commissionService->invalid($log);
                    continue;
                }

                $commissionService->settle($log);
            } catch (\Exception $e) {
                Yii::error("[分销] 结算单条失败 LogID:{$log->id} Msg:{$e->getMessage()}");
            }
        }
    }

    /**
     * 分润统一回滚(大一统):合并原「取消」+「售后」两条路径。
     * order_detail_id=0 整单(取消)、>0 仅该明细(售后部分退款)。逐条交给中台 revert:
     * 未结算作废、已结算按发放口径强制追回(不够扣成负数,与平台统一口径一致),内部幂等。
     */
    public function onCommissionRevert(CommissionRevertEvent $event)
    {
        $query = CommissionLog::find()
            ->where([
                'mall_id'   => $event->mall_id,
                'order_id'  => $event->order_id,
                'type'      => DistributionCommissionType::DISTRIBUTION, // 只回滚分销佣金,不碰其它插件记录
                'is_delete' => 0,
            ])
            ->andWhere(['in', 'status', [CommissionLog::STATUS_PENDING, CommissionLog::STATUS_SETTLED]]);
        if ($event->order_detail_id > 0) {
            $query->andWhere(['order_detail_id' => $event->order_detail_id]); // 明细级:只回滚该明细
        }
        $logs = $query->all();
        if (empty($logs)) {
            return;
        }

        // 同步作废未到期的延迟发放计划，否则 cron 到期还会把这笔已回滚的佣金发出去
        DistributionSettlePlan::invalidByLogIds(array_map(fn($l) => (int) $l->id, $logs));

        $commissionService = new CommissionService();
        foreach ($logs as $log) {
            try {
                $commissionService->revert($log);
            } catch (\Exception $e) {
                Yii::error("[分销] 回滚单条失败 LogID:{$log->id} " . $e->getMessage());
            }
        }
    }

    // ================= 辅助方法 =================

    /**
     * 按层级索引合并规则：override 中非空数字覆盖 base 对应层级，留空回退 base
     */
    private function mergeRates(array $base, array $override): array
    {
        $merged = [];
        foreach ($base as $idx => $rule) {
            $row = is_array($rule) ? $rule : [];
            if (isset($override[$idx]) && is_array($override[$idx])) {
                $v = $override[$idx]['reward_rate'] ?? null;
                if ($v !== null && $v !== '' && is_numeric($v)) {
                    $row['reward_rate'] = $v;
                }
            }
            $merged[] = $row;
        }
        return $merged;
    }

    /**
     * 获取上级关系链
     * @return array [0=>父级ID, 1=>爷爷级ID, ...]
     */
    /**
     * 佣金计算基数 —— 同一套比例，算在不同的钱上结果差很远。
     *
     * 0=订单实付(默认,行为与改动前一致)  1=利润(实付-成本)  2=商品成本  3=商品现价
     * 利润为负(成本高于实付)时返回 0,由调用方跳过该商品行,绝不算出负佣金。
     */
    private function calcBase($detail, array $goodsList, int $base): string
    {
        // ⚠ total_price = 「商品真实总额 = price × num」,是**原价小计、未扣优惠券/积分抵扣**。
        //   真正的实付在 $detail->pay_price。这里刻意保持 total_price ——
        //   改成 pay_price 会直接改变所有商城的分润金额,属业务口径变更、不是修 bug。
        $fullPrice = (string) $detail->total_price;
        $goods = $goodsList[$detail->goods_id] ?? null;
        $num   = (string) (int) $detail->num;

        $cost = $goods ? bcmul((string) ($goods['cost_price'] ?? 0), $num, 2) : '0.00';
        $now  = $goods ? bcmul((string) ($goods['price'] ?? 0), $num, 2) : $fullPrice;

        switch ($base) {
            case 1: // 利润 = 商品金额 - 成本
                $profit = bcsub($fullPrice, $cost, 2);
                return bccomp($profit, '0', 2) > 0 ? $profit : '0.00';
            case 2: // 成本
                return $cost;
            case 3: // 商品现价
                return $now;
            default: // 商品金额(原价小计)
                return $fullPrice;
        }
    }

    private function getParentChain($userId, $maxDepth)
    {
        // 关系链必须走 UserService —— 它保证只返回状态正常的上级:
        // 已注销 / 被拉黑的祖先会被摘出链,后面的人层级依次前移(穿透)。
        // 原来在这里自己 while 走 parent_id,有两个 bug:
        //   · 带 is_delete=0 查当前节点,走到已注销上级时 break,导致「墓碑照拿佣金、其上全断」
        //   · 用 !$user->parent_id 判到顶,而 -1(平台直属)/-2(游客)是 truthy,-1 会被当收款人
        return array_map(
            static fn($p) => (int)$p['id'],
            UserService::getParents($userId, $maxDepth)
        );
    }
}
