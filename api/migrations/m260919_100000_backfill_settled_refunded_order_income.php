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

use app\core\Migration;
use app\models\Order;
use app\models\OrderDetail;
use app\models\UserCoupon;
use app\services\order\actions\OrderSettlementService;
use app\services\order\pipeline\stages\creation\FinalizeIncomeStage;

/**
 * 回填「已结算」「已全额退款关单」订单的平台收入 / 商家收入(接 m260918_120000,那边只管已付款未结算)。
 *
 * ============================================================
 * 为什么还有错
 * ============================================================
 * bonnie 上 m260918_120000 跑完,后台订单列表顶上的「商家收入」还剩 1122,其中 878 是错的。
 * 顺着查下去,已结算 / 退款关单的历史数据有这几类:
 *
 *   S. 已结算的自营单,平台收入 ≠ 实收(应当整笔归平台)
 *      · 结算时整单重算收入的 calculateFinalIncome 2026-03-24 才有,之前结算的单一直留着旧下单公式的值
 *        (商家 = Σ明细货款、平台 = 实付 − 它)—— bonnie 8 张自营单(7 张卡券 + #115)商家收入 878
 *      · 之后结算的,又是按明细货款汇总的,而自营单明细货款的含义换过几代(最早记 0、积分商城单记 0、
 *        2026-04-14 前平台券先从货款里扣),平台收入会被算小
 *   A. 已结算的商家单,值是旧下单公式(结算早于整单重算)
 *   B. 已结算的商家单,值是旧结算公式(整单补贴全扣:退掉那几件分到的平台券 / 随机减又扣一遍;
 *      2026-04-14 前的单平台券下单时已从货款里扣过,又扣一遍)
 *   C. 已全额退款并关单(pay_status = 已退款),两个收入却不是 0 —— 旧取消订单退款那条路不重算收入
 *      (2026-08 才补上)。后台订单列表不统计关单,但商户后台看板按「已支付 / 部分退款 / 已退款」
 *      统计收入,会算进去。bonnie 14 张(商家单 5 张:平台 76 / 商家 384)
 *
 * ============================================================
 * 怎么回填
 * ============================================================
 *   S → OrderSettlementService::computeIncome():自营单 = 实收,口径没有歧义,和它不一样的都改
 *   A → FinalizeIncomeStage::creationIncome()(实付 − 商家那份;运费按下单时刻的归属)
 *       ⛔ 不用 computeIncome:这批单早于 2026-04-14,明细货款含义和现在不一样,
 *       按明细重算会把平台券再扣一遍(bonnie 商家单 #111 平台 16 会算成 -4)
 *   B → OrderSettlementService::computeIncome()(明细上早已扣掉的补贴它会自己跳过)
 *   C → 0 / 0(现在的退款落地全额退完就是 0 / 0)
 *   商家单只改指纹对得上的(A / B),对不上的不动 —— 插件单(如艾灸商家单明细货款刻意记 0)有自己的口径。
 *
 * 不动:待付款后关单的(统计已不计入)、已关单但仍是已支付的(状态异常,要人工看)、没有有效明细的单、
 *       运费当时记给第三方(配送服务商)的 A 类单、
 *       插件自己分钱的单(声明 mch_settlement_exempt:卡券按结算价打给门店、艾灸分给技师、悦达、收银台)。
 *       卡券单当时由卡券插件的迁移单独回填;后来统计不再分平台/商家,那条迁移已删(见 2026-09-20)。
 *
 * ============================================================
 * 并发与回滚(同 m260918_120000)
 * ============================================================
 * 不包大事务,逐单「比较后再写」;每批写库前把 原值 + 新值 追加进 @runtime/migration_backup/ 下的备份;
 * down 只还原「现在还是本迁移写进去的值」的单。
 *
 * 这两个字段只被三处报表 SUM() 读,给商家打款读的是明细 mch_income_price,不动钱。
 */
class m260919_100000_backfill_settled_refunded_order_income extends Migration
{
    private function backupFile(): string
    {
        return Yii::getAlias('@runtime/migration_backup/m260919_100000_order_income.jsonl');
    }

    public function up()
    {
        $svc = new OrderSettlementService();
        $file = $this->backupFile();
        \yii\helpers\FileHelper::createDirectory(dirname($file));

        $scanned = 0;
        $changed = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0];
        $skippedRace = 0;
        $diffPlatform = '0.00';
        $diffMch = '0.00';

        $query = Order::find()->where(['is_delete' => 0])->andWhere(['or',
            ['settlement_status' => Order::SETTLEMENT_STATUS_SETTLED],
            ['pay_status' => Order::PAY_STATUS_REFUNDED, 'status' => Order::STATUS_CLOSED],
        ])->orderBy(['id' => SORT_ASC]);

        foreach ($query->batch(200) as $orders) {
            $ids = array_map(function ($o) { return $o->id; }, $orders);
            $detailsByOrder = [];
            foreach (OrderDetail::find()->where(['order_id' => $ids, 'is_delete' => 0])->asArray()->all() as $d) {
                $detailsByOrder[$d['order_id']][] = $d;
            }

            $plans = [];
            foreach ($orders as $order) {
                $scanned++;
                $oldMch = $this->amount($order->mch_income);
                $oldPlatform = $this->amount($order->platform_income);
                $details = $detailsByOrder[$order->id] ?? [];

                if ((int)$order->pay_status === Order::PAY_STATUS_REFUNDED && (int)$order->status === Order::STATUS_CLOSED) {
                    // C:全额退款关单
                    if (bccomp($oldMch, '0', 2) === 0 && bccomp($oldPlatform, '0', 2) === 0) {
                        continue;
                    }
                    $rule = 'C';
                    [$mch, $platform] = ['0.00', '0.00'];
                } else {
                    // 插件自己分钱的单(卡券按结算价打给门店、艾灸分给技师……,声明 mch_settlement_exempt):
                    // 收入字段按插件口径,这里不动 —— 按「自营整笔归平台」改会把门店 / 技师的钱记成平台的。
                    // (全额退款关单不受影响:钱都退回买家了,谁的单都该是 0 / 0)
                    if (\app\services\order\OrderBehaviorRegistry::isMchSettlementExempt($order)) {
                        continue;
                    }
                    $active = array_filter($details, function ($d) { return (int)$d['status'] !== OrderDetail::STATUS_INVALID; });
                    if (!$active) {
                        continue; // 没有有效明细:computeIncome 会算成 0,不是它们该有的值
                    }
                    $hasRefund = count($active) < count($details);
                    [$cMch, $cPlatform] = $this->oldCreationIncome($order, $details);
                    [$sMch, $sPlatform] = $this->oldSettlementIncome($order, $details);
                    if ((int)$order->mch_id === 0) {
                        // S:自营单 = 实收,口径没有歧义
                        $rule = 'S';
                        [$mch, $platform] = $svc->computeIncome($order);
                    } elseif (!$hasRefund && bccomp($oldMch, $cMch, 2) === 0 && bccomp($oldPlatform, $cPlatform, 2) === 0) {
                        // A:结算早于整单重算,留着旧下单公式的值 → 按新下单口径(按实付分)
                        // 运费当时记给了第三方的不动:2026-07-28 前同城单走配送服务商时,express_income_mch_id
                        // 存的是服务商 —— 这笔运费既不是平台的也不是本商家的,按下单口径重放会记成商家收入
                        if (bccomp($this->amount($order->express_price), '0', 2) > 0
                            && !in_array((int)$order->express_income_mch_id, [0, (int)$order->mch_id], true)) {
                            continue;
                        }
                        $rule = 'A';
                        $replay = clone $order;
                        $replay->express_income_mch_id = FinalizeIncomeStage::creationExpressOwner($order);
                        [$mch, $platform] = FinalizeIncomeStage::creationIncome($replay, $cMch);
                    } elseif (bccomp($oldMch, $sMch, 2) === 0 && bccomp($oldPlatform, $sPlatform, 2) === 0) {
                        // B:旧结算公式算的 → 按现在的结算口径重算
                        $rule = 'B';
                        [$mch, $platform] = $svc->computeIncome($order);
                    } else {
                        continue; // 指纹对不上:不是那两段旧代码写的,不动
                    }
                }

                if (bccomp($oldMch, $mch, 2) === 0 && bccomp($oldPlatform, $platform, 2) === 0) {
                    continue;
                }
                $plans[] = [
                    'id' => (int)$order->id, 'rule' => $rule,
                    'old_mch' => $oldMch, 'old_platform' => $oldPlatform,
                    'new_mch' => $mch, 'new_platform' => $platform,
                ];
            }
            if (!$plans) {
                continue;
            }

            // 先落备份再写库:写到一半进程挂了,已经写掉的也能从备份还原
            $lines = '';
            foreach ($plans as $plan) {
                $lines .= json_encode($plan, JSON_UNESCAPED_UNICODE) . "\n";
            }
            file_put_contents($file, $lines, FILE_APPEND | LOCK_EX);

            foreach ($plans as $plan) {
                $n = Order::updateAll(
                    ['mch_income' => $plan['new_mch'], 'platform_income' => $plan['new_platform']],
                    ['id' => $plan['id'], 'mch_income' => $plan['old_mch'], 'platform_income' => $plan['old_platform']]
                        + $this->stateGuard($plan['rule'])
                );
                if ($n === 0) {
                    $skippedRace++; // 这期间被别的流程改过了,以它为准
                    continue;
                }
                $changed[$plan['rule']]++;
                $diffMch = bcadd($diffMch, bcsub($plan['new_mch'], $plan['old_mch'], 2), 2);
                $diffPlatform = bcadd($diffPlatform, bcsub($plan['new_platform'], $plan['old_platform'], 2), 2);
            }
        }

        $total = array_sum($changed);
        echo "    > 扫描已结算 / 全额退款关单 {$scanned} 单,改了 {$total} 单"
            . "(S 已结算自营单 {$changed['S']},A 已结算商家单留着下单值 {$changed['A']},B 商家单旧结算重复扣补贴 {$changed['B']},C 全额退款关单 {$changed['C']};"
            . "平台收入 " . (bccomp($diffPlatform, '0', 2) >= 0 ? '+' : '') . "{$diffPlatform},"
            . "商家收入 " . (bccomp($diffMch, '0', 2) >= 0 ? '+' : '') . "{$diffMch})"
            . ($skippedRace ? ",{$skippedRace} 单期间已被改动、跳过" : '') . "\n";
        if ($total) {
            echo "    > 原值备份:{$file}\n";
        }
        return true;
    }

    public function down()
    {
        $file = $this->backupFile();
        if (!is_file($file)) {
            echo "    > 没有备份文件 {$file},无需还原(up 当时没改到任何订单,或备份已被删除)\n";
            return true;
        }
        $total = 0;
        $restored = 0;
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $plan = json_decode($line, true);
            if (!is_array($plan) || empty($plan['id'])) {
                continue;
            }
            $total++;
            // 只还原「现在还是本迁移写进去的值」的:之后被别的流程改过的,保留现值
            $restored += Order::updateAll(
                ['mch_income' => $plan['old_mch'], 'platform_income' => $plan['old_platform']],
                ['id' => (int)$plan['id'], 'mch_income' => $plan['new_mch'], 'platform_income' => $plan['new_platform']]
                    + $this->stateGuard((string)($plan['rule'] ?? ''))
            );
        }
        @unlink($file);
        echo "    > 备份 {$total} 条,按原值还原 {$restored} 单(其余之后已被改动,保留现值)\n";
        return true;
    }

    /** 写库时再确认一遍状态没变(算完到写入之间可能被结算 / 退款 / 关单) */
    private function stateGuard(string $rule): array
    {
        if ($rule === 'C') {
            return ['pay_status' => Order::PAY_STATUS_REFUNDED, 'status' => Order::STATUS_CLOSED];
        }
        return ['settlement_status' => Order::SETTLEMENT_STATUS_SETTLED];
    }

    /** 旧下单公式(修复前 FinalizeIncomeStage):商家 = Σ全部明细货款,平台 = 实付 − 它,不分自营、不分运费 */
    private function oldCreationIncome(Order $order, array $details): array
    {
        $sum = '0.00';
        foreach ($details as $d) {
            $sum = bcadd($sum, $this->amount($d['mch_income_price']), 2);
        }
        return [$sum, bcsub($this->amount($order->pay_price), $sum, 2)];
    }

    /**
     * 旧结算公式(修复前 calculateFinalIncome):只数「有效」明细,运费无条件按归属加,
     * 补贴扣整单金额(支付随机减 + 平台券),不管退掉的那几件分了多少
     */
    private function oldSettlementIncome(Order $order, array $details): array
    {
        $mch = '0.00';
        $gross = '0.00';
        foreach ($details as $d) {
            if ((int)$d['status'] !== OrderDetail::STATUS_VALID) {
                continue;
            }
            if ($order->mch_id > 0) {
                $mch = bcadd($mch, $this->amount($d['mch_income_price']), 2);
                $gross = bcadd($gross, $this->amount($d['concession_amount']), 2);
            } else {
                $gross = bcadd($gross, $this->amount($d['mch_income_price']), 2);
            }
        }
        if ((int)$order->express_income_mch_id === 0) {
            $gross = bcadd($gross, $this->amount($order->express_price), 2);
        } else {
            $mch = bcadd($mch, $this->amount($order->express_price), 2);
        }
        $subsidy = $this->amount($order->platform_subsidy_random_minus);
        if ((int)$order->user_coupon_id > 0 && bccomp($this->amount($order->coupon_cut_price), '0', 2) > 0) {
            $coupon = UserCoupon::findOne((int)$order->user_coupon_id);
            if ($coupon && (int)$coupon->mch_id === 0) {
                $subsidy = bcadd($subsidy, $this->amount($order->coupon_cut_price), 2);
            }
        }
        return [bccomp($mch, '0', 2) < 0 ? '0.00' : $mch, bcsub($gross, $subsidy, 2)];
    }

    private function amount($value): string
    {
        return is_numeric($value) ? bcadd((string)$value, '0', 2) : '0.00';
    }
}
