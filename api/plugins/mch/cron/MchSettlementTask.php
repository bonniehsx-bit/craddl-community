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

namespace app\plugins\mch\cron;


use app\core\Task;
use app\models\Order;
use app\models\OrderDetail;
use app\plugins\mch\services\MchSettlementService;
use app\services\order\OrderBehaviorRegistry;
use Yii;

class MchSettlementTask extends Task
{
    /**
     * 禁止多实例并发：虽然 MchSettlementService 内部有 LockHelper 幂等锁（见 returnStock），
     * 但兜底扫描的并发会浪费资源并可能触发锁争用警告。
     */
    public $allowMultiInstance = false;

    /**
     * 定时任务规则
     * 每天凌晨4点执行，作为兜底检查
     * @return string
     */
    public function rule()
    {
        return '0 4 * * *';
    }

    /**
     * 任务描述
     * @return string
     */
    public function description()
    {
        return "商家货款与运费结算【兜底】任务";
    }

    /**
     * 运行定时任务
     * @param mixed|null $params
     * @return void
     */
    public function run($params = null)
    {
        Yii::warning("--- [兜底任务] 开始执行商家货款与运费的结算检查 ---");

        // 核心查询：
        // 找出所有主订单已结算(SETTLED)，但其下的“商品详情货款”或“运费”尚未结算的订单。
        $query = Order::find()->alias('o')
            ->leftJoin(['od' => OrderDetail::tableName()], 'od.order_id = o.id')
            ->where([
                // 逐商场作用域:原漏 mall_id → 在某商场轮次里跨商场结算其它商场订单,商户货款/运费打款串场
                'o.mall_id' => Yii::$app->mall->id,
                'o.settlement_status' => Order::SETTLEMENT_STATUS_SETTLED,
                'o.is_delete' => 0
            ])
            ->andWhere(['>', 'o.mch_id', 0]);

        // 排除「商家货款另有归属」的业态。名单由各插件 Plugin::getOrderBehaviors() 的
        // mch_settlement_exempt 声明（yuda / massage / checkout），不再硬编码插件名。
        //
        // ⚠️ 此前只排除了 'yuda'：massage 订单会被本兜底任务捞到并调 MchSettlementService，
        //    今天仅因 massage 的 mch_income_price=0 走了「金额<=0 直接标记完成」的提前返回才没发钱。
        //    checkout 同理未排除，靠 is_mch_settled 标志侥幸空转。现一并按声明收口。
        //
        // 双查口径与 NULL 安全性统一由 excludeCondition() 兜住（o.plugin 是 nullable 列）
        if ($cond = OrderBehaviorRegistry::excludeCondition('mch_settlement_exempt', 'o.type', 'o.plugin')) {
            $query->andWhere($cond);
        }

        $query
            ->andWhere([
                'OR',
                // 条件一：存在未结算的有效商品详情
                ['AND', ['od.status' => OrderDetail::STATUS_VALID, 'od.is_mch_settled' => 0]],
                // 条件二：运费需打款给商家但尚未打款(用 is_express_paid,与财务汇总标志 is_express_settled 解耦)
                ['AND', ['>', 'o.express_price', 0], ['o.is_express_paid' => 0]]
            ])
            ->groupBy('o.id'); // 按订单ID去重

        $totalCount = $query->count();
        if ($totalCount == 0) {
            Yii::warning("[兜底任务] 未发现被遗漏的待结算订单。");
            Yii::warning("--- [兜底任务] 结算检查执行完毕 ---");
            return;
        }

        Yii::warning("[兜底任务] 发现 {$totalCount} 个被遗漏的待结算订单。");

        $successCount = 0;
        $failCount = 0;
        $service = new MchSettlementService();

        foreach ($query->batch(50) as $orders) {
            /** @var Order[] $orders */
            foreach ($orders as $order) {
                try {
                    // 直接调用统一的结算服务，它内部会处理所有该订单下的结算项
                    $service->settleByOrder($order);
                    $successCount++;
                } catch (\Exception $e) {
                    $failCount++;
                    Yii::error("[兜底任务] 处理订单 #{$order->id} 失败: " . $e->getMessage());
                }
            }
        }

        Yii::warning("[兜底任务] 处理完毕。成功处理订单: {$successCount} 个, 失败: {$failCount} 个。");
        Yii::warning("--- [兜底任务] 结算检查执行完毕 ---");
    }
}
