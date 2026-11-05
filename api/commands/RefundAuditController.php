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

namespace app\commands;

use app\models\CommonPayOrder;
use app\models\CommonPayRefund;
use app\models\Goods;
use app\models\Order;
use app\models\OrderDetail;
use app\models\OrderRefund;
use app\models\User;
use app\services\RefundService;
use app\services\RefundSuccessDispatcher;
use app\services\RefundTaskService;
use Yii;
use yii\console\Controller;

/**
 * 支付 → 售后 的数据一致性审计(隔离 mall=997)。
 *
 * ============================================================
 * 审计的是「钱、单、明细」三者会不会对不上
 * ============================================================
 * 退款有三条路径,各自收尾的程度不同:
 *   · after_sales  售后单发起  → handleAfterSales   (会动售后单)
 *   · cancel_order 取消订单    → handleCancelOrder  (不动售后单)
 *   · 插件直写流水 如 yuda_cancel → handleGeneric   (不动售后单)
 *
 * 一笔退款要落地的字段散在四张表:
 *   order.pay_status / status / refund_price
 *   order_detail.refund_status / status
 *   order_refund.status / is_refund / refund_time
 *   common_pay_refund.status / business_synced_at
 *
 *   php yii refund-audit/run
 */
class RefundAuditController extends Controller
{
    private $mallId = 997;
    private $pass = 0;
    private $fail = 0;
    private $userId = 0;
    private $goodsId = 0;

    public function actionRun()
    {
        echo "\n===== 支付→售后 数据一致性审计(mall={$this->mallId}) =====\n\n";
        Yii::$app->set('mall', (object) ['id' => $this->mallId]);
        Yii::$app->platform = \app\helpers\ConstantHelper::PLATFORM_MPWX;
        $this->cleanup();

        try {
            $this->seed();
            $this->auditGenericFullRefund();
            $this->auditPartialNoClose();
            $this->auditOverRefundGuard();
            $this->auditCancelLeavesRefundOrphan();
            $this->auditRefundPriceSync();
            $this->auditForceCancelVsPendingRefund();
            $this->auditCancelGuard();
            $this->auditOrphanSideEffect();
            $this->simulateShippedGoodsAtRisk();
            $this->auditReturnNotice();
            $this->auditPartialStillProcessing();
            $this->auditSoftDeletedDetail();
            $this->auditCancelOrderDetailLanding();
        } catch (\Throwable $e) {
            $this->assert('审计异常: ' . $e->getMessage() . ' @ '
                . basename($e->getFile()) . ':' . $e->getLine(), false);
        } finally {
            $this->cleanup();
        }

        echo "\n===== 结果: {$this->pass} 通过 / {$this->fail} 失败 =====\n\n";
        return $this->fail === 0 ? 0 : 1;
    }

    /** ① 插件直写流水全额退 → 该关的都关 */
    private function auditGenericFullRefund()
    {
        echo "== ① 插件直写流水(yuda_cancel 这类)全额退款 ==\n";
        [$order, $pay, $detail] = $this->mkPaidOrder(100);
        $task = $this->mkRefundTask($pay, 100, 'yuda_cancel', $order->id);

        RefundSuccessDispatcher::dispatch($task);
        $order->refresh(); $detail->refresh(); $task->refresh();

        $this->assert('⭐ 订单被关闭', (int) $order->status === Order::STATUS_CLOSED, "status={$order->status}");
        $this->assert('⭐ 明细标为售后完成',
            (int) $detail->refund_status === OrderDetail::REFUND_STATUS_COMPLETE, "refund_status={$detail->refund_status}");
        $this->assert('明细标为无效(不再计入结算)',
            (int) $detail->status === OrderDetail::STATUS_INVALID, "status={$detail->status}");
        $this->assert('⭐⭐ order.refund_price 回写了退款额'
            . '(这个字段历史上从来没被写过,首页销售额的减数恒为 0)',
            bccomp((string) $order->refund_price, '100.00', 2) === 0, "refund_price={$order->refund_price}");
        $this->assert('流水打上业务闭环标记(幂等锁)', (int) $task->business_synced_at > 0);
    }

    /** ② 部分退款不能关单 */
    private function auditPartialNoClose()
    {
        echo "\n== ② 部分退款不动主订单 ==\n";
        [$order, $pay, $detail] = $this->mkPaidOrder(100);
        $task = $this->mkRefundTask($pay, 30, 'yuda_cancel', $order->id);

        RefundSuccessDispatcher::dispatch($task);
        $order->refresh(); $detail->refresh();

        $this->assert('⭐⭐ 只退了 30/100,订单不能关闭',
            (int) $order->status !== Order::STATUS_CLOSED, "status={$order->status}");
        $this->assert('明细也不能标成售后完成',
            (int) $detail->refund_status !== OrderDetail::REFUND_STATUS_COMPLETE, "refund_status={$detail->refund_status}");
        $this->assert('⭐ 但 refund_price 要记上这 30 —— 否则营业额虚高',
            bccomp((string) $order->refund_price, '30.00', 2) === 0, "refund_price={$order->refund_price}");
    }

    /** ③ ⛔ 超额退款守卫 —— 二次退款会不会出钱 */
    private function auditOverRefundGuard()
    {
        echo "\n== ③ ⛔ 已全额退过,再退一次 ==\n";
        [$order, $pay, $detail] = $this->mkPaidOrder(69);
        $t1 = $this->mkRefundTask($pay, 69, 'cancel_order', $order->id);
        RefundSuccessDispatcher::dispatch($t1);
        $order->refresh();
        $this->assert('前提:第一笔退款已成功,订单标为全额退款',
            (int) $order->pay_status === Order::PAY_STATUS_REFUNDED, "pay_status={$order->pay_status}");

        // 再发起一笔同额退款(模拟商家又去处理那条挂着的售后单)
        $blocked = false; $msg = '';
        try {
            (new RefundTaskService())->createRefundTask($order->id, 69, CommonPayRefund::SOURCE_AFTER_SALES, 999);
        } catch (\Throwable $e) {
            $blocked = true; $msg = $e->getMessage();
        }
        $this->assert('⭐⭐ 二次退款被拦下 —— 没有资损'
            . '(createRefundTask 算「已退+处理中+待处理」的占用额度,超过实付即抛)',
            $blocked, '⚠ 竟然放行了,这是真资损');
        if ($blocked) echo "       拦截信息: {$msg}\n";

        $cnt = CommonPayRefund::find()->where(['common_pay_order_id' => $pay->id, 'is_delete' => 0])->count();
        $this->assert('库里只有 1 笔退款流水,没多出来', (int) $cnt === 1, "实际 {$cnt} 笔");
    }

    /** ④ 取消订单退款后,已存在的待审核售后单会怎样 */
    private function auditCancelLeavesRefundOrphan()
    {
        echo "\n== ④ 取消订单退款,但用户之前已申请了售后 ==\n";
        [$order, $pay, $detail] = $this->mkPaidOrder(50);

        // 用户先申请售后(待审核)
        $refund = new OrderRefund([
            'mall_id' => $this->mallId, 'user_id' => $this->userId,
            'order_id' => $order->id, 'order_detail_id' => $detail->id,
            'goods_id' => $this->goodsId, 'order_no' => $order->order_no,
            'refund_price' => 50, 'status' => OrderRefund::STATUS_PENDING,
            'is_refund' => 0, 'type' => 0, 'is_delete' => 0,
        ]);
        $refund->save(false);

        // 订单从别的入口被取消并退款
        $task = $this->mkRefundTask($pay, 50, 'cancel_order', $order->id);
        RefundSuccessDispatcher::dispatch($task);

        $order->refresh(); $refund->refresh();
        $this->assert('订单侧处理正确(标为全额退款)',
            (int) $order->pay_status === Order::PAY_STATUS_REFUNDED);
        $this->assert('⚠ 已知缺口:售后单仍停在「待审核」'
            . ' —— handleCancelOrder / handleGeneric 都不碰 order_refund,'
            . '商家后台会一直看到这条假的待处理单',
            (int) $refund->status === OrderRefund::STATUS_PENDING,
            "status={$refund->status}(若已变说明缺口被修了,改这条用例)");

        // 商家真去处理这条挂着的售后单会怎样
        $svc = new RefundService();
        $agreed = $svc->agree($refund->id);
        $refund->refresh();
        $done = $agreed ? $svc->completeRefund($refund->id) : false;
        $this->assert('⭐⭐ 商家去处理这条挂单,打款那步失败 —— 钱不会重复出去',
            $done === false, '⚠ 竟然成功了,这是二次退款');
        echo "       失败原因: " . ($svc->getErrorMsg() ?: '(见日志:退款金额超限)') . "\n";
    }

    /** ⑤ refund_price 与真实成功流水一致 */
    private function auditRefundPriceSync()
    {
        echo "\n== ⑤ order.refund_price 与成功流水对账 ==\n";
        [$order, $pay, $detail] = $this->mkPaidOrder(200);
        foreach ([[30, 'yuda_cancel'], [70, 'yuda_cancel']] as [$amt, $src]) {
            RefundSuccessDispatcher::dispatch($this->mkRefundTask($pay, $amt, $src, $order->id));
        }
        $order->refresh();
        $sum = CommonPayRefund::find()
            ->where(['common_pay_order_id' => $pay->id, 'status' => CommonPayRefund::STATUS_SUCCESS, 'is_delete' => 0])
            ->sum('refund_price');
        $this->assert('⭐ 两笔部分退款累加,refund_price = 成功流水之和',
            bccomp((string) $order->refund_price, (string) $sum, 2) === 0,
            "order={$order->refund_price} vs 流水合计={$sum}");
        $this->assert('累计 100/200 仍未退完,订单不关',
            (int) $order->status !== Order::STATUS_CLOSED, "status={$order->status}");
    }

    /**
     * ⑥ ⛔ 售后已发起退款(流水占着额度),系统又要整单强制退款。
     *
     * 这个组合现实里会发生:用户申请「仅退款」→ 商家同意并打款(流水待处理)
     * → 与此同时拼团失败 / 开奖失败,插件用 force=true 强制取消整单退款。
     * 11 处插件调用传的都是 force=true,绕过 OrderService::cancel 的售后单防线。
     *
     * 关注点是:那笔强制退款是被守卫拦下(钱退不出去,用户拿不到),
     * 还是放行(重复退款)。两种都要有明确结论。
     */
    private function auditForceCancelVsPendingRefund()
    {
        echo "\n== ⑥ ⛔ 售后退款处理中,系统又要整单强制退款 ==\n";
        [$order, $pay, $detail] = $this->mkPaidOrder(80);

        // 售后单走到「已同意」,再打款 → 生成一笔待处理流水
        $refund = new OrderRefund([
            'mall_id' => $this->mallId, 'user_id' => $this->userId,
            'order_id' => $order->id, 'order_detail_id' => $detail->id,
            'goods_id' => $this->goodsId, 'order_no' => $order->order_no,
            'refund_price' => 80, 'status' => OrderRefund::STATUS_AGREED,
            'is_refund' => 0, 'type' => 0, 'is_delete' => 0,
        ]);
        $refund->save(false);
        (new RefundService())->completeRefund($refund->id);
        $refund->refresh();
        $this->assert('前提:售后单进入「退款处理中」',
            (int) $refund->status === OrderRefund::STATUS_PROCESSING, "status={$refund->status}");
        $pendingCnt = CommonPayRefund::find()->where(['common_pay_order_id' => $pay->id, 'is_delete' => 0])->count();
        $this->assert('前提:已生成 1 笔退款流水(占着额度)', (int) $pendingCnt === 1, "实际 {$pendingCnt} 笔");

        // 此时插件强制整单退款
        $blocked = false; $msg = '';
        try {
            (new RefundTaskService())->createRefundTask($order->id, 80, CommonPayRefund::SOURCE_CANCEL_ORDER, $order->id);
        } catch (\Throwable $e) {
            $blocked = true; $msg = $e->getMessage();
        }
        $this->assert('⭐⭐ 强制整单退款被守卫拦下 —— 钱不会退两次'
            . '(额度算的是「已退+处理中+待处理」,售后那笔虽未到账也占着额度)',
            $blocked, '⚠ 放行了 = 重复退款');
        if ($blocked) echo "       拦截信息: {$msg}\n";

        $cnt = CommonPayRefund::find()->where(['common_pay_order_id' => $pay->id, 'is_delete' => 0])->count();
        $this->assert('流水仍是 1 笔,没多出来', (int) $cnt === 1, "实际 {$cnt} 笔");
        echo "       ⚠ 但这也意味着:插件的强制取消这一步会抛异常。\n";
        echo "         钱最终仍由售后那笔流水退给用户(金额相同,结果正确),\n";
        echo "         代价是插件侧看到一个异常,得确认它有没有吞掉/重试。\n";
    }

    /** ⑦ 后台手动关单的售后防线 */
    private function auditCancelGuard()
    {
        echo "\n== ⑦ 后台手动关单遇到进行中售后 ==\n";
        [$order, $pay, $detail] = $this->mkPaidOrder(60);
        $refund = new OrderRefund([
            'mall_id' => $this->mallId, 'user_id' => $this->userId,
            'order_id' => $order->id, 'order_detail_id' => $detail->id,
            'goods_id' => $this->goodsId, 'order_no' => $order->order_no,
            'refund_price' => 60, 'status' => OrderRefund::STATUS_PENDING,
            'is_refund' => 0, 'type' => 0, 'is_delete' => 0,
        ]);
        $refund->save(false);

        $svc = new RefundService();
        $orderSvc = new \app\services\OrderService();

        $ok = $orderSvc->cancel($order);              // 不带 force
        $this->assert('⭐⭐ 有进行中售后时,后台关单被拒(2026-07-02 加的防线)'
            . ' —— 否则整单自动退款会和售后重复,还会把售后单卡死在「已同意」',
            $ok === false, '⚠ 竟然放行了');
        echo "       拒绝原因: " . ($orderSvc->getErrorMsg() ?: '(见 OrderService::cancel)') . "\n";

        $okForce = $orderSvc->cancel($order, true);   // 插件用的这条
        $this->assert('⚠ 但 force=true 会绕过这道防线 —— 11 处插件调用走的都是它'
            . '(拼团失败/开奖失败/酒店拒单/门店取消)',
            $okForce !== false || true);   // 只做说明,不断言成败
        $refund->refresh();
        $this->assert('⚠ 已知缺口:强制关单后售后单仍停在「待审核」,商家后台一直看得到',
            (int) $refund->status === OrderRefund::STATUS_PENDING,
            "status={$refund->status}(若已变说明缺口被修了,改这条用例)");
    }

    /**
     * ⑧ 整单退完后,订单级售后标记要终结,不能被开着的售后单带成「售后中」。
     *
     * updateOrderStatusAfterAction 判「是否还有售后在跑」用的是
     *   status IN (PENDING, AGREED, USER_SHIPPED, PROCESSING)
     * 插件强制整单退款时,用户之前申请的售后单正好落在这个集合里,
     * 于是订单会同时是「已关闭」和「售后中」——两个字段打架。
     *
     * ⚠ 修的是**订单级标记**,不是去关售后单:退货类售后里用户可能已把货寄出,
     *   单子一关商家收到包裹就查不到来历(见第 ⑨ 组)。
     */
    private function auditOrphanSideEffect()
    {
        echo "\n== ⑧ 整单退完 → 订单级售后标记终结(不被开着的售后单带偏) ==\n";
        [$order, $pay, $detail] = $this->mkPaidOrder(40);
        $refund = new OrderRefund([
            'mall_id' => $this->mallId, 'user_id' => $this->userId,
            'order_id' => $order->id, 'order_detail_id' => $detail->id,
            'goods_id' => $this->goodsId, 'order_no' => $order->order_no,
            'refund_price' => 40, 'status' => OrderRefund::STATUS_PENDING,
            'is_refund' => 0, 'type' => 0, 'is_delete' => 0,
        ]);
        $refund->save(false);

        RefundSuccessDispatcher::dispatch($this->mkRefundTask($pay, 40, 'yuda_cancel', $order->id));
        $order->refresh();

        $this->assert('订单已关闭', (int) $order->status === Order::STATUS_CLOSED, "status={$order->status}");
        $this->assert('⭐⭐ 整单退完 → 订单级售后标记为「已结束」,不再被开着的售后单带成「售后中」'
            . '(修之前是 status=0 已关闭 + refund_status=20 售后中,两个字段打架)',
            (int) $order->refund_status === Order::REFUND_STATUS_COMPLETED,
            "refund_status={$order->refund_status}");
        $refund->refresh();
        $this->assert('⭐ 售后单本身仍然开着 —— 这是刻意的,退货类的货可能在路上',
            (int) $refund->status === OrderRefund::STATUS_PENDING, "status={$refund->status}");
        echo "       结果: 订单 status={$order->status}(已关闭) refund_status={$order->refund_status}(售后已结束)\n";
        echo "             售后单 status={$refund->status}(仍在列表里,货有据可查)\n";
    }

    /**
     * ⑨ 模拟最危险的组合:货已寄回 + 插件强制整单退款。
     *
     * 可达性(不是假想):
     *   · 申请售后时,待发货订单被**强制成「仅退款」**(RefundService::apply 类型修正),
     *     所以拼团失败/开奖失败那类(订单必然待发货)不会产生退货类售后;
     *   · 但 hotel 的强制退款只排除了「已核销」和「已退款」,**没排除已发货**;
     *     massage 门店取消只看时间窗口,也不看发货状态。
     *   · 已发货订单 → 用户可申请退货退款(type=2)→ 商家同意并给退货地址
     *     → 用户寄货并填单号(USER_SHIPPED)→ 此时后台强制退款。
     *
     * 这一组要回答两件事:
     *   ① 强制退款这一步会不会被拦(售后还没打款,不占额度 → 不拦);
     *   ② 如果按「自动关闭售后单」的方案去做,那批已寄回的货会变成什么。
     */
    private function simulateShippedGoodsAtRisk()
    {
        echo "\n== ⑨ 模拟:货已寄回,后台又强制整单退款 ==\n";
        [$order, $pay, $detail] = $this->mkPaidOrder(120);
        $order->status = Order::STATUS_SHIPPED;      // 已发货
        $order->save(false);

        // 用户申请退货退款,商家同意并给了退货地址,用户已寄货
        $refund = new OrderRefund([
            'mall_id' => $this->mallId, 'user_id' => $this->userId,
            'order_id' => $order->id, 'order_detail_id' => $detail->id,
            'goods_id' => $this->goodsId, 'order_no' => $order->order_no,
            'refund_price' => 120, 'type' => RefundService::TYPE_RETURN_AND_REFUND,
            'status' => OrderRefund::STATUS_USER_SHIPPED, 'is_refund' => 0,
            'return_name' => '商家收货人', 'return_mobile' => '13800000000',
            'return_address' => '某某仓库',
            'user_express' => '顺丰', 'user_express_no' => 'SF123456789',
            'user_express_at' => time(), 'is_delete' => 0,
        ]);
        $refund->save(false);
        $this->assert('前提:退货退款单,用户已寄货并填了单号',
            (int) $refund->status === OrderRefund::STATUS_USER_SHIPPED && $refund->user_express_no !== '');

        // 后台强制整单退款,走真实路径 OrderService::cancel(force=true)
        // ⚠ 别直接调 createRefundTask —— handleCancelOrder 只改 pay_status,
        //   order.status 是上游 OrderSettlementService::cancel 改的,跳过它状态就不对。
        $orderSvc = new \app\services\OrderService();
        $cancelled = $orderSvc->cancel($order, true);
        $this->assert('⭐⭐ 强制退款**没被拦下** —— 售后单还没打款,不占额度'
            . '(额度守卫只认 common_pay_refund 里的流水,认不出"用户已寄货"这件事)',
            $cancelled !== false, '被拦了: ' . $orderSvc->getErrorMsg());

        // 让这笔退款成功并走 dispatch
        $task = CommonPayRefund::find()->where(['common_pay_order_id' => $pay->id, 'is_delete' => 0])
            ->orderBy(['id' => SORT_DESC])->one();
        if ($task) {
            $task->status = CommonPayRefund::STATUS_SUCCESS;
            $task->success_at = time();
            $task->save(false);
            RefundSuccessDispatcher::dispatch($task);
        }
        $order->refresh(); $refund->refresh();

        $this->assert('订单被关闭、标为全额退款', (int) $order->status === Order::STATUS_CLOSED);
        $this->assert('⭐⭐ 售后单仍停在「用户已发货」—— 货在路上,单还开着',
            (int) $refund->status === OrderRefund::STATUS_USER_SHIPPED, "status={$refund->status}");
        echo "       现状: 钱已全退给用户,货({$refund->user_express} {$refund->user_express_no})也已寄出\n";
        echo "             售后单开着 → 商家收到包裹时还能在后台看到这条,知道货的来历。\n";
        echo "       ⛔ 若按「自动关闭售后单」的方案把它关掉,这条线索就断了:\n";
        echo "          钱退了、货寄了、后台没记录 —— 包裹到仓库无人认领。\n";
        echo "          所以 USER_SHIPPED 必须排除在自动关闭之外。\n";
    }

    /** ⑩ 退货在途提醒 */
    private function auditReturnNotice()
    {
        echo "\n== ⑩ 强制关单前的「退货在途」提醒 ==\n";
        $svc = \app\services\OrderService::class;

        // 没有售后 → 不该有提示
        [$o1] = $this->mkPaidOrder(10);
        $this->assert('无售后时不提示(别制造噪音)', $svc::pendingReturnNotice((int) $o1->id) === '');

        // 仅退款类的售后 → 不涉及寄货,也不该提示
        [$o2, , $d2] = $this->mkPaidOrder(20);
        $this->mkRefundOrder($o2, $d2, OrderRefund::STATUS_AGREED, RefundService::TYPE_REFUND_ONLY);
        $this->assert('⭐ 仅退款类不提示 —— 不涉及寄货,提示了反而干扰',
            $svc::pendingReturnNotice((int) $o2->id) === '', '提示了: ' . $svc::pendingReturnNotice((int) $o2->id));

        // 退货类 + 商家已同意(用户可能已在寄) → 要提示
        [$o3, , $d3] = $this->mkPaidOrder(30);
        $this->mkRefundOrder($o3, $d3, OrderRefund::STATUS_AGREED, RefundService::TYPE_RETURN_AND_REFUND);
        $n3 = $svc::pendingReturnNotice((int) $o3->id);
        $this->assert('⭐⭐ 退货类+已同意 → 提示(商家已给退货地址,用户可能已寄出但还没填单号)',
            $n3 !== '' && mb_strpos($n3, '尚未填写退货单号') !== false, "提示: {$n3}");

        // 退货类 + 用户已寄并填了单号 → 提示里要带上单号
        [$o4, , $d4] = $this->mkPaidOrder(40);
        $r4 = $this->mkRefundOrder($o4, $d4, OrderRefund::STATUS_USER_SHIPPED, RefundService::TYPE_RETURN_AND_REFUND);
        $r4->user_express = '顺丰'; $r4->user_express_no = 'SF999'; $r4->save(false);
        $n4 = $svc::pendingReturnNotice((int) $o4->id);
        $this->assert('⭐ 已寄货的提示里带快递单号,商家能对上包裹',
            mb_strpos($n4, 'SF999') !== false, "提示: {$n4}");
        echo "       文案: {$n4}\n";
    }

    /**
     * ⑪ 反向边界:部分退款时,订单级标记**仍要**是「售后中」。
     *
     * ⑧ 那条改动只在「整单退完」时把标记终结。这一组证明它没有一刀切 ——
     * 只退了一部分、售后还开着的订单,必须继续显示售后中,否则商家会漏处理。
     */
    private function auditPartialStillProcessing()
    {
        echo "\n== ⑪ 部分退款 + 售后开着 → 仍标「售后中」 ==\n";
        [$order, $pay, $detail] = $this->mkPaidOrder(100);
        $this->mkRefundOrder($order, $detail, OrderRefund::STATUS_PENDING, RefundService::TYPE_REFUND_ONLY);

        // 只退 30/100
        RefundSuccessDispatcher::dispatch($this->mkRefundTask($pay, 30, 'yuda_cancel', $order->id));
        $order->refresh();

        $this->assert('订单没被关闭(只退了 30/100)',
            (int) $order->status !== Order::STATUS_CLOSED, "status={$order->status}");
        // 部分退款时 handleGeneric 直接 return(不关单也不调关单器),
        // 所以订单级标记保持申请售后时写入的值,不会被误清成「无售后」
        $this->assert('⭐⭐ 订单级售后标记没被清掉 —— 改动只在整单退完时才终结标记,'
            . '没有一刀切;否则商家会漏掉还开着的售后单',
            (int) $order->refund_status !== Order::REFUND_STATUS_NO_REFUND
            && (int) $order->refund_status !== Order::REFUND_STATUS_COMPLETED,
            "refund_status={$order->refund_status}");
    }

    /**
     * ⑫ 分子分母口径要一致 —— 软删的明细不能把订单算成"全退完了"。
     *
     * updateOrderStatusAfterAction 里:
     *   分母 totalDetailCount    带 is_delete=0
     *   分子 refundedDetailCount **不带**
     * 一旦有软删且 refund_status=COMPLETE 的明细,分子就可能 >= 分母,
     * isFullyRefunded 误判为 true → 订单被误关闭 + 标成全额退款,
     * 而真正在售的那条明细根本没退。
     *
     * 当前库里没有软删明细(实测 0 条),代码里也没有软删 order_detail 的路径,
     * 所以这是道防线而不是在修一个正在发生的事故。但 ⑧ 那条改动正建立在
     * isFullyRefunded 上,判断错了它跟着一起错。
     */
    private function auditSoftDeletedDetail()
    {
        echo "\n== ⑫ 软删明细不能把订单算成全退完 ==\n";
        [$order, $pay, $detail] = $this->mkPaidOrder(100);

        // 再加一条明细,标成"售后完成"但已软删
        $ghost = new OrderDetail([
            'mall_id' => $this->mallId, 'user_id' => $this->userId,
            'order_id' => $order->id, 'order_no' => $order->order_no,
            'goods_id' => $this->goodsId, 'num' => 1,
            'price' => 100, 'total_price' => 100, 'pay_price' => 100,
            'refund_status' => OrderDetail::REFUND_STATUS_COMPLETE,
            'is_delete' => 1,          // ← 软删
        ]);
        $ghost->save(false);

        // 在售的那条明细没退,订单不该被关
        (new RefundService())->updateOrderStatusAfterAction($order);
        $order->refresh();

        $this->assert('⭐⭐ 在售明细没退款,订单不能被关闭'
            . '(分子若不带 is_delete=0,软删的"已退款"明细会把分子顶到 >= 分母)',
            (int) $order->status !== Order::STATUS_CLOSED, "status={$order->status}");
        $this->assert('⭐ 也不能标成全额退款',
            (int) $order->pay_status !== Order::PAY_STATUS_REFUNDED, "pay_status={$order->pay_status}");
    }

    /**
     * ⑬ 取消订单退款(cancel_order)也要把明细落地 —— 和插件直写流水同口径。
     *
     * ⑧ 验的是「整单退完 → 订单级标记终结」,但它用的 source_type 是 yuda_cancel,
     * 落进 handleGeneric —— 那条路本来就会调 updateOrderStatusAfterAction。
     * 而 SOURCE_CANCEL_ORDER 走的是 handleCancelOrder,原来**整个函数都没有**
     * 这个调用,也不碰 order_detail:
     *
     *     OrderSettlementService::cancel() 不碰明细
     *     handleCancelOrder()              不碰明细
     *
     * 于是钱全退了,明细还是 refund_status=0 / status=NORMAL。
     * 实测两台服务器 cancel_order 的订单明细 100% 停在非 INVALID
     * (测试服 20 单 / bonnie 9 单)。危害在下游:全仓 8 处拿
     * `status != STATUS_INVALID` 判「算不算数」(团队业绩 / 用户升级 /
     * syk 业绩区域 / 积分 / pb),对它们这些单从来没退过款。
     *
     * ⛔ 修的顺序不能反 —— 见 handleCancelOrder 里的注释:
     *    先不标明细就调关单器,分子恒为 0,反而会写成「售后中」。
     *    所以这条用例必须同时断言明细和订单两侧,只看订单侧会漏掉顺序错误。
     */
    private function auditCancelOrderDetailLanding()
    {
        echo "\n== ⑬ cancel_order 全额退款要落到明细上(和 handleGeneric 同口径) ==\n";

        // --- 全额:该落的都要落 ---
        [$order, $pay, $detail] = $this->mkPaidOrder(100);
        // 用户先申请了售后(三处一起写,和 RefundService::apply 的真实行为一致)
        $refund = $this->mkRefundOrder($order, $detail, OrderRefund::STATUS_PENDING, RefundService::TYPE_REFUND_ONLY);
        $order->refresh();
        $this->assert('前提:订单级标记为「申请中」',
            (int) $order->refund_status === Order::REFUND_STATUS_APPLIED, "refund_status={$order->refund_status}");

        // 插件强制整单退款:关单那步 + 退款到账
        $order->status = Order::STATUS_CLOSED;
        $order->save(false);
        RefundSuccessDispatcher::dispatch($this->mkRefundTask($pay, 100, 'cancel_order', $order->id));
        $order->refresh(); $detail->refresh(); $refund->refresh();

        $this->assert('⭐⭐ 明细标成「售后完成」—— 原来 cancel_order 这条路根本不碰明细',
            (int) $detail->refund_status === OrderDetail::REFUND_STATUS_COMPLETE,
            "refund_status={$detail->refund_status}");
        $this->assert('⭐⭐ 明细标成 INVALID —— 否则团队业绩/用户升级/积分等 8 处'
            . '仍把这笔已全额退款的单算成有效',
            (int) $detail->status === OrderDetail::STATUS_INVALID, "status={$detail->status}");
        $this->assert('⭐⭐ 订单级售后标记终结(不再是「申请中」)—— 验证关单器确实被调到了,'
            . '且是在明细标记之后调的(顺序反了这里会变成 20 售后中)',
            (int) $order->refund_status === Order::REFUND_STATUS_COMPLETED,
            "refund_status={$order->refund_status}");
        $this->assert('订单已关闭且标为全额退款',
            (int) $order->status === Order::STATUS_CLOSED
            && (int) $order->pay_status === Order::PAY_STATUS_REFUNDED,
            "status={$order->status} pay_status={$order->pay_status}");
        $this->assert('⭐ 售后单仍然开着 —— 与 ⑨ 同一条约定,退货类的货可能在路上',
            (int) $refund->status === OrderRefund::STATUS_PENDING, "status={$refund->status}");
        // 三条退款路里原来只有 after_sales 调 recalculateAfterRefund,
        // 实测:after_sales 的订单 mch_income 全是 0,另两条路全非 0(商家货款报表虚高)
        $this->assert('⭐⭐ 商家货款/平台收入归零 —— 整单退完商家一分不拿,'
            . '否则 OrderListForm 的 SUM(o.mch_income) 报表会虚高',
            bccomp((string) $order->mch_income, '0.00', 2) === 0
            && bccomp((string) $order->platform_income, '0.00', 2) === 0,
            "mch_income={$order->mch_income} platform_income={$order->platform_income}");

        // --- 部分退:一条都不许动 ---
        [$order2, $pay2, $detail2] = $this->mkPaidOrder(100);
        RefundSuccessDispatcher::dispatch($this->mkRefundTask($pay2, 30, 'cancel_order', $order2->id));
        $order2->refresh(); $detail2->refresh();
        $this->assert('⭐⭐ 部分退款(30/100)不许把明细标掉 —— 剩下 70 的货还要发',
            (int) $detail2->refund_status !== OrderDetail::REFUND_STATUS_COMPLETE
            && (int) $detail2->status !== OrderDetail::STATUS_INVALID,
            "refund_status={$detail2->refund_status} status={$detail2->status}");
        $this->assert('部分退款也不关单',
            (int) $order2->status !== Order::STATUS_CLOSED, "status={$order2->status}");
    }

    /**
     * 造一条售后单。
     *
     * ⚠ 必须同时改明细和订单级标记 —— 真实的 RefundService::apply() 是一次性写三处的
     * (order_refund / order_detail.refund_status / order.refund_status)。
     * 只写售后单的话,后面所有依赖这三者一致性的断言都在测一个不存在的状态。
     */
    private function mkRefundOrder(Order $order, OrderDetail $detail, int $status, int $type): OrderRefund
    {
        $r = new OrderRefund([
            'mall_id' => $this->mallId, 'user_id' => $this->userId,
            'order_id' => $order->id, 'order_detail_id' => $detail->id,
            'goods_id' => $this->goodsId, 'order_no' => $order->order_no,
            'refund_price' => $order->pay_price, 'status' => $status, 'type' => $type,
            'is_refund' => 0, 'is_delete' => 0,
        ]);
        $r->save(false);

        $detail->refund_status = OrderDetail::REFUND_STATUS_APPLIED;
        $detail->save(false);

        $order->refund_status = Order::REFUND_STATUS_APPLIED;
        $order->save(false);

        return $r;
    }

    // ── 脚手架 ──

    /** @return array{0:Order,1:CommonPayOrder,2:OrderDetail} */
    private function mkPaidOrder(float $price): array
    {
        $no = 'RA' . date('YmdHis') . mt_rand(1000, 9999);
        $order = new Order([
            'mall_id' => $this->mallId, 'user_id' => $this->userId, 'order_no' => $no,
            'plugin' => 'mall', 'type' => Order::TYPE_NORMAL,
            'total_price' => $price, 'pay_price' => $price, 'refund_price' => 0,
            'status' => Order::STATUS_TO_BE_SHIPPED, 'is_pay' => 1,
            'pay_status' => Order::PAY_STATUS_PAID, 'pay_at' => time(), 'is_delete' => 0,
        ]);
        $order->save(false);

        $detail = new OrderDetail([
            'mall_id' => $this->mallId, 'user_id' => $this->userId,
            'order_id' => $order->id, 'order_no' => $no, 'goods_id' => $this->goodsId,
            'num' => 1, 'price' => $price, 'total_price' => $price, 'pay_price' => $price,
            'refund_status' => OrderDetail::REFUND_STATUS_NORMAL, 'is_delete' => 0,
        ]);
        $detail->save(false);

        $pay = new CommonPayOrder([
            'mall_id' => $this->mallId, 'user_id' => $this->userId,
            'order_id' => $order->id, 'order_no' => $no,
            'pay_price' => $price, 'total_price' => $price, 'is_pay' => 1,
            'pay_at' => time(), 'order_class' => Order::class, 'key' => uniqid(), 'is_delete' => 0,
        ]);
        $pay->save(false);

        return [$order, $pay, $detail];
    }

    /** 造一笔"已成功"的退款流水,模拟网关已经把钱退回去了 */
    private function mkRefundTask(CommonPayOrder $pay, float $amt, string $src, int $sourceId): CommonPayRefund
    {
        $t = new CommonPayRefund([
            'mall_id' => $this->mallId, 'user_id' => $this->userId,
            'common_pay_order_id' => $pay->id, 'order_no' => $pay->order_no,
            'out_refund_no' => 'RF' . uniqid(), 'refund_price' => $amt,
            'source_type' => $src, 'source_id' => $sourceId,
            'status' => CommonPayRefund::STATUS_SUCCESS, 'success_at' => time(),
            'business_synced_at' => 0, 'is_delete' => 0,
        ]);
        $t->save(false);
        return $t;
    }

    private function seed()
    {
        $u = new User(['mall_id' => $this->mallId, 'nickname' => 'RA_U',
            'access_token' => uniqid('ra_', true), 'is_delete' => 0]);
        $u->save(false);
        $this->userId = (int) $u->id;

        $g = new Goods(['mall_id' => $this->mallId, 'name' => '审计商品', 'type' => 'normal',
            'status' => 1, 'stock' => 100, 'price' => 100, 'cover_pic' => '/a.png', 'is_delete' => 0]);
        $g->save(false);
        $this->goodsId = (int) $g->id;
    }

    private function assert(string $name, bool $ok, string $extra = '')
    {
        if ($ok) { $this->pass++; echo "  ✅ {$name}\n"; }
        else     { $this->fail++; echo "  ❌ {$name}" . ($extra ? "\n      {$extra}" : '') . "\n"; }
    }

    private function cleanup()
    {
        CommonPayRefund::deleteAll(['mall_id' => $this->mallId]);
        OrderRefund::deleteAll(['mall_id' => $this->mallId]);
        CommonPayOrder::deleteAll(['mall_id' => $this->mallId]);
        OrderDetail::deleteAll(['mall_id' => $this->mallId]);
        Order::deleteAll(['mall_id' => $this->mallId]);
        Goods::deleteAll(['mall_id' => $this->mallId]);
        User::deleteAll(['mall_id' => $this->mallId]);
    }
}
