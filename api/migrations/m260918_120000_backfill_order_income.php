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
use app\services\order\actions\OrderSettlementService;
use app\services\order\pipeline\stages\creation\FinalizeIncomeStage;

/**
 * 回填「已付款、还没结算」订单的平台收入 / 商家收入。
 *
 * ============================================================
 * 为什么会有这批错数据
 * ============================================================
 * 后台订单列表顶上的「平台收入 / 商家收入」读的就是这两个字段。结算前它们是下单时写的预估值,
 * 下单和退款重算各有一个 bug(同一提交已修,只管新单),存量要这里补:
 *
 *   ① 自营单下单时整笔货款记成了商家收入,平台收入 0(f1bb266afa 修了新单,老单等结算)
 *   ② 商家单的运费归商家,下单时却记进了平台收入
 *   ③ 售后期内部分退款,退款重算只认「有效」明细 —— 结算前明细还是「正常」,
 *      一条都数不到,整单两个收入被算成 0
 *
 * 结算时 calculateFinalIncome 会整单重算,所以已结算的单不受 ①②③ 影响,不碰。
 * 已关闭、待付款的单统计里不再计入收入(同一提交),也不碰。
 *
 * ============================================================
 * 怎么回填:只改「确实是那两段 bug 写出来的」行,照修好的代码重放
 * ============================================================
 * · 一件没退过:库里的值 == 旧下单公式(商家 = Σ明细货款,平台 = 实付 − 它)才改,
 *   改成新下单口径 FinalizeIncomeStage::creationIncome(),运费归属也走下单用的同一个
 *   creationExpressOwner() —— 和新单完全一样(含插件声明的 express_income_to_mch,如点单的附加费归门店)。
 * · 退过:库里是旧退款重算算出来的 0 / 0 才改,改成 OrderSettlementService::computeIncome()
 *
 * ⛔ 不能对「已付款未结算」一刀切重放:有的插件单压根没走建单管线的收入阶段
 *   (实测艾灸自营单一直是 0 / 0,结算后也是 0 / 0),套下单公式会凭空变成「平台 = 实付」,
 *   结算时又跳回 0。指纹对不上的一律不动。
 * ⛔ 运费归属不读库里的 express_income_mch_id,和下单走同一个 creationExpressOwner():
 *   库里的值可能是建单后被插件改过的(旧版点单就是这么改的),以下单口径为准,老单新单才一致。
 *
 * ============================================================
 * 上线时和线上流量并发
 * ============================================================
 * · 不包一个大事务:逐单提交,不会把几千张单锁到整个回填结束(发货 / 收货 / 结算会等锁超时)
 * · 每条都是「比较后再写」:仍未结算、两个值还是读到的那两个才写。回填算完到写入之间,
 *   这单要是刚退了款(退款重算写了新值)或刚结算,就跳过,不拿旧快照盖掉
 *
 * ============================================================
 * 为什么改这两个字段是安全的 / 怎么回滚
 * ============================================================
 * 全库只有三处报表 SUM() 读它们:后台订单列表、商户端订单列表、商户后台看板。
 * 给商家打款读的是明细的 mch_income_price,结算时两个字段还会整单重算。不动钱。
 *
 * 每批写之前先把 原值 + 新值 追加到 @runtime/migration_backup/ 下的备份文件。
 * down 只还原「现在的值还是本迁移写进去的那两个、且仍未结算」的单 ——
 * 之后又退过款、结算过、被关单的,都已经有了更新的值,不能拿旧值盖回去。
 */
class m260918_120000_backfill_order_income extends Migration
{
    private function backupFile(): string
    {
        return Yii::getAlias('@runtime/migration_backup/m260918_120000_order_income.jsonl');
    }

    public function up()
    {
        $svc = new OrderSettlementService();
        $file = $this->backupFile();
        \yii\helpers\FileHelper::createDirectory(dirname($file));

        $scanned = 0;
        $changed = 0;
        $skippedRace = 0;
        $diffPlatform = '0.00';
        $diffMch = '0.00';

        $query = Order::find()->where([
            'is_delete' => 0,
            'status' => [Order::STATUS_TO_BE_SHIPPED, Order::STATUS_SHIPPED, Order::STATUS_COMPLETED],
            'settlement_status' => Order::SETTLEMENT_STATUS_PENDING,
        ])->orderBy(['id' => SORT_ASC]);

        foreach ($query->batch(200) as $orders) {
            $ids = array_map(function ($o) { return $o->id; }, $orders);
            $details = OrderDetail::find()->select(['order_id', 'status', 'mch_income_price'])
                ->where(['order_id' => $ids, 'is_delete' => 0])->asArray()->all();
            $sumIncome = [];
            $refunded = [];
            $active = [];
            foreach ($details as $d) {
                $sumIncome[$d['order_id']] = bcadd($sumIncome[$d['order_id']] ?? '0.00', is_numeric($d['mch_income_price']) ? (string)$d['mch_income_price'] : '0', 2);
                if ((int)$d['status'] === OrderDetail::STATUS_INVALID) {
                    $refunded[$d['order_id']] = true;
                } else {
                    $active[$d['order_id']] = true;
                }
            }

            $plans = [];
            foreach ($orders as $order) {
                $scanned++;
                $oldMch = is_numeric($order->mch_income) ? (string)$order->mch_income : '0';
                $oldPlatform = is_numeric($order->platform_income) ? (string)$order->platform_income : '0';
                $sum = $sumIncome[$order->id] ?? '0.00';
                // 插件自己分钱的单(卡券按结算价打给门店、艾灸分给技师……,声明 mch_settlement_exempt):
                // 收入字段按插件口径,这里不动 —— 按「自营整笔归平台」改会把门店 / 技师的钱记成平台的
                if (\app\services\order\OrderBehaviorRegistry::isMchSettlementExempt($order)) {
                    continue;
                }

                if (isset($refunded[$order->id])) {
                    // 旧退款重算的指纹:一条「有效」都数不到 → 两个都是 0。还有没退的明细才需要补
                    if (!isset($active[$order->id]) || bccomp($oldMch, '0', 2) !== 0 || bccomp($oldPlatform, '0', 2) !== 0) {
                        continue;
                    }
                    [$mch, $platform] = $svc->computeIncome($order);
                } else {
                    // 旧下单公式的指纹:商家 = Σ明细货款,平台 = 实付 − Σ明细货款(不分自营、不分运费)
                    $oldFormulaPlatform = bcsub(is_numeric($order->pay_price) ? (string)$order->pay_price : '0', $sum, 2);
                    if (bccomp($oldMch, $sum, 2) !== 0 || bccomp($oldPlatform, $oldFormulaPlatform, 2) !== 0) {
                        continue;
                    }
                    $replay = clone $order;
                    $replay->express_income_mch_id = FinalizeIncomeStage::creationExpressOwner($order);
                    [$mch, $platform] = FinalizeIncomeStage::creationIncome($replay, $sum);
                }

                if (bccomp($oldMch, $mch, 2) === 0 && bccomp($oldPlatform, $platform, 2) === 0) {
                    continue;
                }
                $plans[] = [
                    'id' => (int)$order->id,
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
                    [
                        'id' => $plan['id'],
                        'settlement_status' => Order::SETTLEMENT_STATUS_PENDING,
                        'mch_income' => $plan['old_mch'],
                        'platform_income' => $plan['old_platform'],
                    ]
                );
                if ($n === 0) {
                    $skippedRace++; // 这期间被退款重算 / 结算改过了,以它为准
                    continue;
                }
                $changed++;
                $diffMch = bcadd($diffMch, bcsub($plan['new_mch'], $plan['old_mch'], 2), 2);
                $diffPlatform = bcadd($diffPlatform, bcsub($plan['new_platform'], $plan['old_platform'], 2), 2);
            }
        }

        echo "    > 已付款未结算订单 {$scanned} 单,改了 {$changed} 单"
            . "(平台收入 " . (bccomp($diffPlatform, '0', 2) >= 0 ? '+' : '') . "{$diffPlatform},"
            . "商家收入 " . (bccomp($diffMch, '0', 2) >= 0 ? '+' : '') . "{$diffMch})"
            . ($skippedRace ? ",{$skippedRace} 单期间已被改动、跳过" : '') . "\n";
        if ($changed) {
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
            // 只还原「现在还是本迁移写进去的值、且仍未结算」的:之后退过款 / 结算过 / 关过单的,
            // 已经有了更新的值,拿旧值盖回去反而错
            $restored += Order::updateAll(
                ['mch_income' => $plan['old_mch'], 'platform_income' => $plan['old_platform']],
                [
                    'id' => (int)$plan['id'],
                    'settlement_status' => Order::SETTLEMENT_STATUS_PENDING,
                    'status' => [Order::STATUS_TO_BE_SHIPPED, Order::STATUS_SHIPPED, Order::STATUS_COMPLETED],
                    'mch_income' => $plan['new_mch'],
                    'platform_income' => $plan['new_platform'],
                ]
            );
        }
        @unlink($file);
        echo "    > 备份 {$total} 条,按原值还原 {$restored} 单(其余之后已被改动,保留现值)\n";
        return true;
    }
}
