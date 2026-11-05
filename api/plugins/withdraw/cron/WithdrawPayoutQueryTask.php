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

namespace app\plugins\withdraw\cron;

use app\core\Task;
use app\funds\BaseFund;
use app\plugins\withdraw\models\WithdrawOrder;
use app\services\payout\PayoutResultDispatcher;
use Yii;

/**
 * 异步打款状态轮询
 *
 * 针对走第三方 API 网关（如快付）且返回 doing 的提现单：
 * 周期性调用网关 orderQuery() 查最终结果，自动流转到「已到账 / 已退回」，
 * 不再依赖人工确认。手动打款(ManualFund 无 orderQuery)的单不在此列，仍由后台确认。
 *
 * 商城级任务：由 Manager 在 foreach(mall) 中注入 $params['mall']，必须按 mall_id 过滤。
 */
class WithdrawPayoutQueryTask extends Task
{
    public function run($params = null)
    {
        $mall = $params['mall'] ?? Yii::$app->mall ?? null;
        if (!$mall) {
            return true;
        }

        // 只轮询「实现了 orderQuery 的异步网关」的打款中单。
        // 为什么不再用 payout_no 过滤:orderQuery 实际按 order_no(merOrderNo)查,不依赖网关单号;
        //   若某网关返回 doing 却没给 sysOrderNo(payout_no=''),旧过滤会把它永久漏轮询卡死。
        // 为什么要按 fund_class 白名单:线下手动(BankFund/AlipayFund/ManualFund)与未知态单现也会进 PAYING,
        //   它们无 orderQuery,若不排除会占满 limit 把真正要轮询的第三方单挤掉(饿死)。
        $asyncClasses = [];
        foreach (\app\plugins\withdraw\services\FundRegistry::list((int)$mall->id) as $g) {
            $cls = $g['class'] ?? '';
            if ($cls && class_exists($cls) && method_exists($cls, 'orderQuery')) {
                $asyncClasses[] = $cls;
            }
        }
        if (!$asyncClasses) {
            return true; // 本商城无异步查单网关,无需轮询
        }

        $list = WithdrawOrder::find()
            ->where(['mall_id' => $mall->id, 'is_delete' => 0, 'status' => WithdrawOrder::STATUS_PAYING])
            ->andWhere(['fund_class' => $asyncClasses])
            ->limit(200)
            ->all();

        if (!$list) {
            return true;
        }

        foreach ($list as $order) {
            try {
                $fundClass = $order->fund_class;
                if (!class_exists($fundClass) || !is_subclass_of($fundClass, BaseFund::class)) {
                    continue;
                }
                $fund = Yii::createObject($fundClass, [(int)$order->mall_id]);
                // 仅支持异步查单的网关（手动/余额网关无此方法，跳过）
                if (!method_exists($fund, 'orderQuery')) {
                    continue;
                }

                $res = $fund->orderQuery($order->attributes);
                if (!is_array($res) || ($res['code'] ?? -1) != 0 || empty($res['data']['status'])) {
                    continue; // 查询失败/仍处理中 → 下个周期再查
                }

                $qd = $res['data'];
                // 网关流水号:平台流水优先,回落渠道流水。既是回填 payout_no 的来源,
                // 也是下面判「这条响应到底描述的是不是这笔打款」的依据。
                $payoutNo = (string)($qd['sysOrderNo'] ?? ($qd['channelFlowNo'] ?? ($qd['payout_no'] ?? '')));

                // 规范化结果 → 交统一分发器按 source_type 回流（网关结果与业务状态机解耦）
                $norm = null;
                switch (strtoupper((string)$qd['status'])) {
                    case 'SUCCESS':
                        // ⚠ 只有拿到网关流水号才认这个 SUCCESS。
                        //
                        // 事故背景:快付代付走 NORMAL_ORDER_QUERY 查单时,响应里的 data.status
                        // 并不是「这笔代付的结果」—— 生产实测 2026-02~08 共 2212 笔代付**全部**
                        // 被判成功、一笔失败都没出现过,其中包含网关异步通知里明确回了
                        // {"status":"FAIL","desc":"收款账户无效"} 的单子;且这 2212 笔的
                        // payout_no **无一例外为空**,说明该响应压根不携带这笔交易的流水号。
                        // 也就是说那个 status 更像是「查询请求本身受理成功」。
                        //
                        // 打款结果的权威来源是异步通知(FastPayAgentNotifyListener,带验签)。
                        // 查单在这里只做兜底,拿不出流水号就不足以证明打款成功 —— 判错成功是
                        // 资损(用户没收到钱却显示已到账),卡在「打款中」只是等回调或人工,可控。
                        if ($payoutNo === '') {
                            Yii::warning(
                                "[withdraw] 查单返回 SUCCESS 但无网关流水号,不予采信,保持打款中"
                                . " order={$order->order_no} fund={$fundClass} resp=" . json_encode($qd, JSON_UNESCAPED_UNICODE),
                                'cron'
                            );
                            break;
                        }
                        $norm = ['status' => 'success', 'payout_no' => $payoutNo];
                        break;
                    case 'FAIL':
                    case 'FAILED':
                        // 失败可以直接采信:置「打款失败」资金仍冻结、不自动退款,
                        // 由管理员决定重试或退回 —— 判错的代价远小于判错成功。
                        $norm = ['status' => 'failed', 'message' => '网关返回打款失败：' . ($qd['desc'] ?? ($qd['fail_reason'] ?? ''))];
                        break;
                    default:
                        // PROCESSING / 处理中 / 未识别 → 保持打款中，下周期再查
                        break;
                }
                if ($norm) {
                    PayoutResultDispatcher::dispatch(WithdrawOrder::class, (int)$order->id, $norm);
                    Yii::warning("[withdraw] 异步回流 {$order->order_no} → {$norm['status']}", 'cron');
                }
            } catch (\Throwable $e) {
                // 与后台手动操作竞争时，transition 抢占失败属预期（另一方已处理），降级为 warning；
                // 其余才是真异常。单条异常不影响其它单。
                $msg = $e->getMessage();
                if (strpos($msg, '状态') !== false || strpos($msg, '不可') !== false) {
                    Yii::warning("[withdraw] 轮询单 {$order->order_no} 已被并发处理：{$msg}", 'cron');
                } else {
                    Yii::error("[withdraw] 轮询单 {$order->order_no} 异常：{$msg}", 'cron');
                }
            }
        }
        return true;
    }

    public function rule()
    {
        // 每 3 分钟查一次
        return '*/3 * * * *';
    }

    public function description()
    {
        return '提现异步打款状态轮询';
    }
}
