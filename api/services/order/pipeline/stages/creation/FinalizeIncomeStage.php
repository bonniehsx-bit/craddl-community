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

namespace app\services\order\pipeline\stages\creation;

use app\models\Order;
use app\plugins\mch\models\Mch;
use app\services\order\OrderBehaviorRegistry;
use app\services\order\pipeline\CreationPayload;
use app\services\order\pipeline\StageInterface;

class FinalizeIncomeStage implements StageInterface
{
    public function handle($payload, \Closure $next)
    {
        /** @var CreationPayload $payload */

        $order = $payload->order;
        $totalMchIncome = $payload->data['_total_mch_income'] ?? '0.00';

        // 1. 确定运费归属(收入要按它分运费,必须先定)
        $this->determineExpressIncome($order);

        // 2. 更新主订单收入归属
        $this->determineIncome($order, $totalMchIncome);

        if (!$order->save()) {
            throw new \Exception('更新订单收入失败');
        }

        return $next($payload);
    }

    /**
     * 下单时的收入归属(结算时会由 OrderSettlementService::calculateFinalIncome 按净额覆盖)。
     *
     * ⛔ 必须按订单是不是商家单分开算,口径要和结算那边一致:
     *   明细上的 mch_income_price 是个复用字段 ——
     *     · 商家单(mch_id > 0):存的是商家货款(已扣平台抽佣)
     *     · 自营单(mch_id = 0):存的是整笔货款,标签叫「平台直营结转」,是【平台】的钱
     *   原来这里不分,自营单整笔记进 mch_income、platform_income = 实付 − 实付 = 0,
     *   后台订单列表顶上的「平台收入 0 / 商家收入 = 全部实付」就是这么来的。
     *   结算时那边是分了的,所以结算完会自己改对,但结算前(已付款 → 售后期结束)这段一直是反的。
     *
     * ⛔ 运费也要按 express_income_mch_id 分:商家单的快递/同城运费归商家,
     *   原来实付里的运费一律落进 platform_income,结算前商家少、平台多,结算时才挪回商家。
     */
    private function determineIncome(Order $order, string $totalMchIncome)
    {
        [$order->mch_income, $order->platform_income] = self::creationIncome($order, $totalMchIncome);
    }

    /**
     * 下单时的两个收入(存量回填 m260918_120000 也用它,保证老单和新单同一口径)
     *
     * @param string $totalMchIncome 全部明细 mch_income_price 之和
     * @return string[] [商家收入, 平台收入]
     */
    public static function creationIncome(Order $order, string $totalMchIncome): array
    {
        if ($order->mch_id > 0) {
            $mchIncome = $totalMchIncome;
            if ((int)$order->express_income_mch_id > 0 && bccomp((string)$order->express_price, '0', 2) > 0) {
                $mchIncome = bcadd($mchIncome, (string)$order->express_price, 2);
            }
            return [$mchIncome, bcsub((string)$order->pay_price, $mchIncome, 2)];
        }
        // 自营:没有商家(只有 mch_id > 0 的订单才算商家收入),实收整笔归平台。
        // ⛔ 插件后面要把多少钱结给门店 / 技师(卡券按结算底价、艾灸给技师),这里不管 ——
        //   这两列只给订单列表顶部的统计用,不承担分账口径;钱怎么分以结算、分润的流水为准。
        //   原来核心会问插件「你要结出去多少」再扣掉,结果是同一批单在插件装没装的环境算出不同的数。
        return ['0.00', bcadd((string)$order->pay_price, '0', 2)];
    }

    private function determineExpressIncome(Order $order)
    {
        $order->express_income_mch_id = self::creationExpressOwner($order);
    }

    /**
     * 下单这一刻的运费归属(0 = 平台)。下单和存量回填(m260918_120000)共用,口径只有这一处。
     *
     * 插件可声明 express_income_to_mch:它的 express_price 装的是门店实收的附加费(如点单的餐位费/打包费),
     * 订单又都是自提,按配送方式判会误记给平台。原来点餐插件在建单事件里事后改归属,
     * 但收入已经按平台算好了,结算前一直记错。
     */
    public static function creationExpressOwner(Order $order): int
    {
        if ($order->express_price <= 0) {
            return 0;
        }
        if ($order->mch_id > 0 && OrderBehaviorRegistry::expressIncomeToMch($order)) {
            return (int)$order->mch_id;
        }
        if ($order->deliver_type == Order::DELIVER_TYPE_EXPRESS) {
            return (int)$order->mch_id;
        }
        if ($order->deliver_type == Order::DELIVER_TYPE_CITY) {
            // 同城配送:商家自配送,配送费归商家自己(服务商/骑手派单层已剔除)
            return (int)$order->mch_id;
        }
        return 0;
    }
}
