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

namespace app\cron;

use app\core\Task;
use app\models\CommonPayOrder;
use Yii;

class OrderPayCheckTask extends Task
{

    public function rule()
    {
        // 每分钟执行一次
        return "*/1 * * * *";
    }

    /**
     * 走命令行,不走 FastCGI。
     *
     * 本任务是「批量 × 外部 HTTP」的组合:一轮最多 500 单,每单都要向支付网关发一次
     * 查单请求(网关侧 CURLOPT_TIMEOUT 就有 30 秒)。而基类默认的 fpm 模式有 30 秒硬超时 ——
     * 就算每次查单只要 200ms,500 单也要 100 秒,必然被中途掐断。
     * 被掐断的后果不是"少查几单"这么轻:查询按 id DESC 排,每轮都从最新的开始,
     * 于是**老订单永远轮不到**,而老订单恰恰是最可能真掉单的那批。
     */
    public function runMode()
    {
        return 'cli';
    }

    /**
     * 任务描述
     */
    public function description()
    {
        return "支付掉单补偿：主动查询30分钟内未支付订单的状态";
    }

    /**
     * 运行定时任务
     */
    public function run($params = null)
    {
        // 1. 定义时间窗口
        // 结束时间：当前时间 - 60秒 (给正常回调留出1分钟缓冲期)
        // 开始时间：当前时间 - 30分钟 (覆盖"挂起后超过10分钟才回来付款"的用户；
        //   微信下单默认2小时内可付，但订单 expire_at 到点会被 AutoCancelOrderTask 关闭，
        //   关闭后 is_cancel=1 自动被下方条件排除，故 30 分钟足够且不会误补已关订单)
        $endTime = time() - 60;
        $startTime = time() - 1800;

        // 调度器逐商城遍历执行:本任务会被每个 mall 各跑一次。
        // 查询**必须**按当前商城过滤,否则每一轮都扫全表:
        //   · 同一批订单被查 N 次,网关查单接口也被打 N 倍;
        //   · limit 是全局的,排在前面的商城会把名额吃光,后面商城的订单永远排不上;
        //   · 最要紧的是上下文错位 —— 支付成功后的业务回调(OrderPaymentService →
        //     CommissionService / WalletService 等)会读 Yii::$app->mall,
        //     拿 A 商城的上下文去处理 B 商城的订单,分润和钱包就写到别人家去了。
        $mallId = Yii::$app->mall->id ?? 0;
        if (!$mallId) {
            Yii::error('[OrderPayCheckTask] 未注入商城上下文,跳过本轮以免跨商城误处理');
            return;
        }

        // 2. 查询符合条件的订单
        // 条件：未支付 + 未取消 + 未删除 + 创建时间在窗口内 + 必须有支付配置(已经发起过支付请求的)
        // 加 limit 防止极端高峰单次查太多、压垮支付网关查单接口；按 id 倒序优先查新单，
        // 老单若本轮没轮到，下一分钟（仍在窗口内）会再查，不会漏。
        $orders = CommonPayOrder::find()
            ->where(['mall_id' => $mallId, 'is_cancel' => 0, 'is_pay' => 0, 'is_delete' => 0])
            ->andWhere(['between', 'created_at', $startTime, $endTime])
            ->andWhere(['!=', 'payment_config', '']) // 必须是已经发起了支付（有配置快照）的
            ->andWhere(['is not', 'payment_config', null])
            ->orderBy('id DESC')
            ->limit(500)
            ->all();
        if (empty($orders)) {
            return;
        }
        /** @var CommonPayOrder $order */
        foreach ($orders as $order) {
            try {
                // 3~5. 逐个商户单号查单。
                //
                // ⚠ 这里**不能**只查 $order->out_trade_no、也不能按 $order->payment 实例化 ——
                // 那两个字段记的都是**最后一次**支付尝试,而用户真正付掉钱的可能是前面某一次:
                //   15:22:00 支付宝下单 → 号 A(用户在这一笔付了钱)
                //   15:22:40 改用余额   → 号 B,payment 被覆盖成 BalancePayment
                // 旧写法于是 new BalancePayment() 去查号 B,而 BalancePayment::queryOrder
                // 只读本地 is_pay、从不问外部网关 → 恒返回 null,号 A 那笔真实支付永远补不回来。
                // 真实事故:支付单 57311 ¥299,回调与本任务两道防线同时失效,最后靠人工补单。
                //
                // 改为遍历该单登记过的所有单号,各用各自登记的支付类去查,任一查到已支付即恢复。
                // 成本可控:实测每单平均 1.43 个单号,30 分钟窗口内峰值 39 单。
                $result  = null;
                $paidVia = '';   // 最终在哪个支付类上查到的,补单时要写回订单
                $paidNo  = '';   // 在哪个商户单号上查到的,同样要写回(退款/再查单都按它问网关)
                foreach ($order->allTradeNos() as $tradeNo => $registered) {
                    // 登记行的 payment 为空(改造前的老行/登记时没传)→ 回落订单字段,
                    // 别直接 continue:那会比改造前**少**查一个号,是回归。
                    $paymentClass = (string) ($registered !== '' ? $registered : $order->payment);
                    // 登记为余额支付的号:余额不经过任何外部网关,它的 queryOrder 只是回读
                    // 本地 is_pay(此刻必然是 0),查了不会有结果。
                    //
                    // 但**不能直接跳过** —— 存量里有「同秒撞号」导致登记值错成余额的行
                    // (成因见 CommonPayOrder::nextTradeNo,真实案例 57345)。
                    // 这种号实际是发给网关的,跳过就等于放弃把它的钱补回来。
                    // 订单字段记着最后一次、也就是真正发给网关的那次,拿它兜一把。
                    if ($paymentClass === \app\payments\BalancePayment::class) {
                        $fallback = (string) $order->payment;
                        if ($fallback === '' || $fallback === \app\payments\BalancePayment::class) {
                            continue;   // 订单字段也是余额 → 这单确实走的余额,没什么可查
                        }
                        $paymentClass = $fallback;
                    }
                    if (!$paymentClass) {
                        continue;
                    }
                    if (!class_exists($paymentClass)
                        || !is_subclass_of($paymentClass, \app\payments\BasePayment::class)) {
                        Yii::warning("[OrderPayCheckTask] 支付类不可用: {$paymentClass}(单号 {$tradeNo})");
                        continue;
                    }

                    // 每个号单独 try:各网关的构造函数在「该网关未配置/未开启」时会 throw
                    // (见 FastPayWechatJsPayment::__construct)。一个订单现在可能实例化多个类,
                    // 不隔离的话第一个抛异常就会把这单**剩下的号全跳过** —— 而真正付了钱的
                    // 可能恰恰是后面那个,等于把本次修复的意义抵消掉。
                    try {
                        // 先判断配置归属再实例化:这一步只依赖类名,放在 new 之前更直白
                        $useSnapshot = $order->paymentConfigBelongsTo($paymentClass);

                        /** @var \app\payments\BasePayment $paymentInstance */
                        $paymentInstance = new $paymentClass($order->mall_id);

                        // 下单时的配置快照只在「它确实属于这个支付类」时才注入 ——
                        // 判断走配置自带的 payment_type(见 paymentConfigBelongsTo),
                        // 不能拿 $order->payment 当代理:用户最后点余额时 payment 会被改成
                        // BalancePayment,而那份快照对网关来说仍然是有效的,误判就白白丢了它。
                        //
                        // 不属于本类时不注入:FastPayWechatJs / FastPayMpwx / MpwxPayment /
                        // WechatPayment 的 queryOrder 确实会读 $this->config,注进去就是拿
                        // A 家的密钥去问 B 家。构造函数已按类名加载了该商城当前配置
                        // (见各类 __construct),那至少是「对的网关的配置」,只是可能比下单时新。
                        if ($useSnapshot) {
                            $paymentInstance->config = \yii\helpers\Json::decode($order->payment_config);
                        }

                        // 两个单号都给:各网关 queryOrder 读的键不一样(多数读 out_trade_no,
                        // FastPayQuickPayment 读 order_no)。原来只给 out_trade_no,快捷支付那条
                        // 分支恒为 undefined → 查单必失败 → 它的掉单永远补不回来。
                        $one = $paymentInstance->queryOrder([
                            'out_trade_no' => $tradeNo,
                            'order_no'     => $order->order_no,
                        ]);
                    } catch (\Throwable $e) {
                        Yii::warning("[OrderPayCheckTask] 单号 {$tradeNo}({$paymentClass})查单异常,"
                            . "继续试该单其余单号: " . $e->getMessage());
                        continue;
                    }

                    if ($one && isset($one['code']) && $one['code'] === \app\core\ApiCode::CODE_SUCCESS) {
                        Yii::warning("[OrderPayCheckTask] 订单 {$order->order_no} 在单号 {$tradeNo}"
                            . "({$paymentClass})上查到已支付");
                        $result  = $one;
                        $paidVia = $paymentClass;
                        $paidNo  = (string) $tradeNo;
                        break;   // 一个号查到就够了,不必再问其余的
                    }
                }

                // 6. 判断结果
                // 根据你之前的代码，queryOrder 成功返回 ['code' => 0, 'msg' => '已支付']
                if ($result && isset($result['code']) && $result['code'] === \app\core\ApiCode::CODE_SUCCESS) {
                    Yii::warning("掉单恢复成功: 订单号 {$order->order_no} 远程已支付，正在同步状态...");
                    // 7. 走统一支付成功入口 paySuccess：
                    //    它自带「幂等检查 + 加锁 + 业务回调(发货/分润/事件) + 成功后才置 is_pay=1」。
                    // ⚠️ 严禁在这里预先 $freshOrder->is_pay = 1 再调：
                    //    那样 paySuccess 的幂等检查会命中 is_pay==1 直接跳过业务回调，
                    //    导致掉单订单"只标已支付、不发货不分润"（曾引发严重事故）。
                    $freshOrder = CommonPayOrder::findOne($order->id);
                    if (!$freshOrder || $freshOrder->is_pay == 1) {
                        continue;
                    }
                    // 直接调 paySuccess 并把查单拿到的平台流水号传进去。
                    // 不能再用 pay():它取的是 $this->transaction_id —— 而掉单订单从未收到过回调,
                    // 这个字段本来就是空的,补回来的订单会没有支付凭证,日后对账和退款都缺依据。
                    $transactionId = (string) ($result['transaction_id'] ?? '');
                    if ($transactionId === '') {
                        // 网关没给流水号不阻断补单(订单状态比凭证更要紧),但要留痕以便人工补录
                        Yii::warning("[OrderPayCheckTask] 订单 {$order->order_no} 查单未返回平台流水号,transaction_id 将为空");
                    }
                    // 支付类传**实际查到已支付的那个**,不是订单字段上那个 ——
                    // 字段可能停在用户最后点的余额支付上,若照抄进去,这笔明明走网关的钱
                    // 会被记成余额支付,后续退款按它反射实例化就退不出去了。
                    // 商户单号同理:写回真正付了钱的那个号,退款才能对上网关那边的原单。
                    $freshOrder->paySuccess($transactionId, $paidVia ?: ($freshOrder->payment ?: null), $paidNo ?: null);
                }

            } catch (\Throwable $e) {
                // 捕获 Throwable 而非 Exception:PHP 8 下类型错误等属于 \Error,
                // 用 \Exception 接不住,一单出错就会把整轮循环带崩、后面的订单全不处理。
                Yii::error("掉单查询异常 [订单:{$order->order_no}]: " . $e->getMessage());
            }
        }
    }
}