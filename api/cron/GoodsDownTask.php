<?php

/**
 * Copyright (c) 2022-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm.
 * User: ganxi
 * Date: 2022/7/12
 * Time: 16:48
 * Note:
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

namespace app\cron;


use app\core\Task;
use app\models\Goods;
use app\services\order\OrderBehaviorRegistry;
use Yii;

/**
 * 售罄自动下架。
 *
 * ⚠ 这不是防超卖的防线,只是**展示层优化** —— 让卖光的商品从货架上消失。
 * 真正防超卖的是下单管线:NormalOrderHandler::checkStockAndPermission 先拦,
 * BaseOrderHandler::deductStock 再用 `stock >= num` 乐观锁原子扣减。
 *
 * 认清这一点,两类错误的代价就不对等了:
 *   · 误下架(不该下的下了)→ 功能直接瘫痪,而且无声无息,排查极难
 *   · 漏下架(该下的没下)  → 商品还挂着,用户点进去下单时被拦「库存不足」
 * 所以本任务一切默认都往**漏下架**那边偏。
 */
class GoodsDownTask extends Task
{

    /**
     * 运行定时任务
     * @param mixed $params
     * @return mixed
     */
    public function run($params = null)
    {
        // 商城级任务：由 Manager 在 foreach(mall) 中注入当前商城上下文。
        // 必须按 mall_id 过滤，否则每个商城迭代都会全表更新（跨租户越界 + N 倍重复执行）。
        $mall = $params['mall'] ?? Yii::$app->mall ?? null;
        if (!$mall) {
            return;
        }
        $mallId = (int)$mall->id;

        // 顺序有讲究:先恢复、再豁免、最后下架。
        // 反过来的话，运营刚上架的商品会在同一轮里先被打下去。
        $restored = $this->restoreAutoManaged($mallId);
        $exempted = $this->exemptManualOnShelf($mallId);
        $downed   = $this->takeOffSoldOut($mallId);

        if ($restored || $exempted || $downed) {
            Yii::warning(
                "[GoodsDownTask] mall={$mallId} 售罄下架 {$downed} 件, "
                . "运营手动上架豁免 {$exempted} 件, 库存恢复交回自动管理 {$restored} 件",
                __METHOD__
            );
        }
    }

    /**
     * 本任务的管辖范围 —— **白名单**。
     *
     * 只管 `normal`(核心自己的商品,库存语义确定),外加插件在
     * Plugin::getOrderBehaviors() 里显式声明了 `auto_off_shelf` 的类型。
     *
     * 早先是反过来的黑名单：默认管所有商品，插件得声明 `virtual_goods` 才能豁免。
     * 那个默认方向是错的 —— 插件的占位商品(账单缴费、账户充值、积分兑换……)库存恒为 0，
     * 作者一旦没想到要来豁免，商品就被静默下架，整个功能瘫痪且完全查不出是谁干的。
     * 真出过事：plaza_bill 6 个、exchange 1 个、withdraw 充值商品，全栽在这。
     * 也和「核心不认识插件」的原则相悖 —— 核心凭什么默认对插件商品行使管辖权。
     *
     * @return string[]
     */
    private function managedTypes(): array
    {
        return array_values(array_unique(array_merge(
            [Goods::TYPE_NORMAL],
            OrderBehaviorRegistry::keysWith('auto_off_shelf')
        )));
    }

    /**
     * ① 库存已恢复的，标记清零，交回系统自动管理。
     *    补货之后就该重新纳管，否则豁免会一直挂着，下次真卖光了不下架。
     */
    private function restoreAutoManaged(int $mallId): int
    {
        return (int)Goods::updateAll(
            ['auto_off_at' => 0],
            ['and',
                ['mall_id' => $mallId, 'is_delete' => 0],
                ['>', 'stock', 0],
                ['<>', 'auto_off_at', 0],
            ]
        );
    }

    /**
     * ② 「我下架过它，可它现在又在架上」= 运营手动上架了 → 豁免，别再抢方向盘。
     *
     * 打成 -1 而不是清零：清零下一轮就又被下架了，等于没豁免。
     * 豁免一直有效，直到库存恢复(见 ①)。
     */
    private function exemptManualOnShelf(int $mallId): int
    {
        return (int)Goods::updateAll(
            ['auto_off_at' => Goods::AUTO_OFF_EXEMPT],
            ['and',
                ['mall_id' => $mallId, 'is_delete' => 0, 'status' => 1],
                ['>', 'auto_off_at', 0],
            ]
        );
    }

    /**
     * ③ 售罄下架。
     *
     * `status = 1` 不能省：没有它，已经下架的行每 2 分钟被重复 UPDATE 一遍，
     * 而且 affected rows 恒为 0，日志里的数字也就没意义了。
     */
    private function takeOffSoldOut(int $mallId): int
    {
        return (int)Goods::updateAll(
            ['status' => 0, 'auto_off_at' => time()],
            ['and',
                ['mall_id' => $mallId, 'is_delete' => 0, 'status' => 1, 'auto_off_at' => 0],
                ['<=', 'stock', 0],
                ['type' => $this->managedTypes()],
            ]
        );
    }

    /**
     * 定时任务规则
     *
     * 单条规则格式
     * ```
     * return 1 1 * * *
     * ```
     * 多条规则格式
     * ```
     * return [
     *      'method1'=>'* * * * *',
     *      'method2'=>'* * * * *',
     * ];
     *
     * return [
     *     'method1'=>[
     *          'spec'=>'* * * * *',
     *          'single'=>true          //这个方法同一时间只允许一个进程存在
     *      ]
     * ]
     * ```
     * @return mixed
     */
    public function rule()
    {
        // TODO: Implement rule() method.
        return "*/2 * * * *";
    }

    /**
     * 任务描述
     * @return mixed
     */
    public function description()
    {
        // TODO: Implement description() method.
        return '商品下架任务';
    }
}
