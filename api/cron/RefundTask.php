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

use app\core\ApiCode;
use app\core\Task;
use app\models\CommonPayOrder;
use app\models\CommonPayRefund;
use app\payments\BasePayment;
use app\services\RefundAttentionService;
use Yii;

/**
 * Class RefundTask
 * @package app\cron
 *
 * 重构说明：
 * 1. 数据源变更：不再查询 CommonPayOrder，改为查询 common_pay_refund 流水表。
 * 2. 并发处理：处理前先标记为“处理中”，防止多进程重复执行。
 * 3. 幂等性：使用流水表生成的 out_refund_no 传给支付接口，确保多次部分退款不会冲突。
 * 4. 结果记录：成功或失败的结果直接记录在流水表中，不再污染支付主表。
 */
class RefundTask extends Task
{
    /** error_msg 前缀:上次退款请求「发出去了但没拿到网关回应」,结果未知(见 run() 里两处注释) */
    private const OUTCOME_UNKNOWN_PREFIX = '[结果未知]';

    public function run($params = null)
    {
        // 调度器逐商城遍历执行:本任务会被每个 mall 各跑一次(与 OrderPayCheckTask 同一约定)。
        // 两个查询都**必须**按当前商城过滤,否则每一轮都扫全表:
        //   · 同一批任务被每个商城的那一轮各捞一次,limit 名额被排在前面的商城吃光,后面商城的永远排不上;
        //   · 更要紧的是上下文错位 —— 退款成功后的业务回写(RefundSuccessDispatcher → 关单/售后/佣金追回)
        //     读的是 Yii::$app->mall,拿 A 商城的上下文去处理 B 商城的退款单。
        $mallId = Yii::$app->mall->id ?? 0;
        if (!$mallId) {
            Yii::error('[RefundTask] 未注入商城上下文,跳过本轮以免跨商城误处理');
            return;
        }

        // 1. 获取待处理的退款任务
        // 状态: 0=待处理
        // 排序: 先进先出 (ID ASC)
        // 限制: 每次处理 10 条，避免脚本超时
        // [补做链路] 扫描那些 status 已 SUCCESS 但 business_synced_at=0 的孤儿记录
        // 这类记录是 dispatch 抛异常导致的业务没闭环。重试 dispatch 即可，不再走网关重发。
        /** @var CommonPayRefund[] $orphanList */
        $orphanList = CommonPayRefund::find()
            ->where([
                'mall_id' => $mallId,
                'status' => CommonPayRefund::STATUS_SUCCESS,
                'business_synced_at' => 0,
                'is_delete' => 0,
            ])
            ->limit(20)
            ->all();
        foreach ($orphanList as $orphan) {
            try {
                \app\services\RefundSuccessDispatcher::dispatch($orphan);
                Yii::warning("[RefundTask.orphan] 补做业务闭环成功 TaskID:{$orphan->id}");
            } catch (\Exception $e) {
                Yii::error("[RefundTask.orphan] 补做业务闭环再次失败 TaskID:{$orphan->id} - " . $e->getMessage());
            }
        }

        /** @var CommonPayRefund[] $taskList */
        $taskList = self::dueQuery($mallId, time())
            ->with(['commonPayOrder']) // 预加载支付主单
            ->orderBy(['id' => SORT_ASC])
            ->limit(10)
            ->all();

        if (empty($taskList)) {
            return;
        }

        foreach ($taskList as $task) {
            // 记录当前是否是失败后的重试
            $isRetry = ($task->retry_count > 0);

            // 2. 抢占任务：标记为“处理中”
            // 这一步非常重要，防止下一分钟的定时任务重复读取同一条数据
            $task->status = CommonPayRefund::STATUS_PROCESSING;
            $task->retry_count += 1; // 增加重试次数
            $task->save();
            try {
                // 3. 数据完整性检查
                /** @var CommonPayOrder $payOrder */
                $payOrder = $task->commonPayOrder;
                if (!$payOrder) {
                    throw new \Exception("关联的支付订单(CommonPayOrder)不存在，ID: {$task->common_pay_order_id}");
                }

                if (!$payOrder->payment) {
                    throw new \Exception("支付订单缺少支付类配置(payment字段)");
                }

                // 4. 实例化支付插件
                // 这里的 $payOrder->payment 存储的是类名 (例如 app\plugins\wxapp\models\Pay)
                if (!class_exists($payOrder->payment)) {
                    throw new \Exception("支付类不存在: {$payOrder->payment}");
                }

                /** @var BasePayment $paymentInstance */
                $paymentInstance = Yii::createObject($payOrder->payment, [$payOrder->mall_id]);

                // ==============================================================
                // 【核心重构】 重试防双回防、单号刷新逻辑
                // ==============================================================
                if ($isRetry) {
                    $isRefundAlreadySuccess = false;
                    $queryInconclusive = false;   // 网关不支持退款查询 / 查询本身失败 → 上次结果无从确认
                    try {
                        // 主动向网关查单，确认上个单号是否早已退款成功只是回调断网
                        $queryResult = $paymentInstance->queryRefund([
                            'out_trade_no' => $task->out_trade_no ?: ($payOrder->out_trade_no ?: $payOrder->order_no),
                            'out_refund_no' => $task->out_refund_no
                        ]);
                        if (isset($queryResult['code']) && $queryResult['code'] == ApiCode::CODE_SUCCESS) {
                            $isRefundAlreadySuccess = true;
                        }
                    } catch (\Exception $qe) {
                        // 网关抛出异常(余额不足、查无此单等)，或是暂不支持独立查单
                        $queryInconclusive = true;
                        Yii::warning("RefundTask [TaskID:{$task->id}] queryRefund未返回成功状态. 原因: " . $qe->getMessage());
                    }

                    if ($isRefundAlreadySuccess) {
                        // 网关回复：退款早就成功了！ -> 拦截双退，直接改本地并结单
                        $task->status = CommonPayRefund::STATUS_SUCCESS;
                        $task->success_at = time();
                        $task->next_retry_at = 0;
                        $task->save();

                        try {
                            \app\services\RefundSuccessDispatcher::dispatch($task);
                        } catch (\Exception $e) {
                            Yii::error("查单确认退款成功，但业务状态闭环回写失败 ID:{$task->id} - " . $e->getMessage());
                        }
                        continue; // 直接结束当前Task，绝不往下继续发 refund 退钱
                    }

                    // 🔒 换号重发的前提是「确认上次没成功」。两种情况不能这么认:
                    //   · 上次失败是网络层的(超时/连不上)—— 请求可能已经到了网关并退成功,只是回应没回来;
                    //   · 而这个网关又查不了(快付四个类、余额、企微都没实现 queryRefund,基类直接 throw)。
                    // 两者同时成立时换号重发 = 同一笔钱退两次(部分退款尤其);而且换号之后,旧号的异步
                    // 退款回调回来会 findOne(out_refund_no) 查空被忽略,双退不留任何痕迹。
                    // 这种情况一律不重发:保持 FAIL,等网关的退款回调把它翻成功,或人工核实;
                    // 按常规节奏(1 / 5 / 30 分钟)再看几次,还没结果就停止自动重试、走下面 catch 里的告警通道,不会静默。
                    // 「网关明确拒绝」(余额不足、参数错)的失败没有这个前缀,照旧换号重发。
                    if ($queryInconclusive && str_starts_with((string) $task->error_msg, self::OUTCOME_UNKNOWN_PREFIX)) {
                        throw new \Exception(self::OUTCOME_UNKNOWN_PREFIX
                            . "上次请求未收到网关回应,且该网关不支持退款查询,为防双退不再换号重发;等待退款回调或人工核实。原因: "
                            . preg_replace('/^' . preg_quote(self::OUTCOME_UNKNOWN_PREFIX, '/') . '/', '', (string) $task->error_msg));
                    }

                    // 网关确实没成功(明确拒绝过)→ 生成全新编号重试(部分网关不接受重复的退款单号)
                    $task->out_refund_no = CommonPayRefund::generateRefundNo();
                    $task->save();
                }
                // ==============================================================


                // 5. 组装参数并调用支付接口
                $refundData = [
                    'pay_order_id' => $payOrder->id,
                    'user_id' => $payOrder->user_id,
                    'out_trade_no' => $task->out_trade_no ?: ($payOrder->out_trade_no ?: $payOrder->order_no), // 优先使用流水表记录的 out_trade_no
                    // 【关键】使用上述确定的最终退款唯一单号发给微信
                    'refund_order_no' => $task->out_refund_no,
                    // 【关键】使用流水表记录的本次退款金额
                    'refund_price' => (float) $task->refund_price,
                    'pay_price' => (float) $payOrder->pay_price, // 订单总额
                ];

                // 调用原有支付类的退款方法
                $res = $paymentInstance->refundOrder($refundData);
                if (isset($res['code']) && $res['code'] == ApiCode::CODE_SUCCESS) {
                    // 6. 退款成功
                    $task->status = CommonPayRefund::STATUS_SUCCESS;
                    $task->success_at = time(); // 使用时间戳
                    $task->next_retry_at = 0;

                    if (!$task->save()) {
                        Yii::warning("退款任务保存失败 ID:{$task->id}");
                        Yii::warning($task->getErrors());
                    }

                    // ============================================
                    // 7. 【核心新增】回调业务层，同步状态
                    // ============================================
                    try {
                        \app\services\RefundSuccessDispatcher::dispatch($task);
                    } catch (\Exception $e) {
                        Yii::error("退款成功但业务回写失败 ID:{$task->id} - " . $e->getMessage());
                        // 注意：这里不应该抛出异常让任务重试，因为钱已经退了。
                        // 应该发告警或者记录人工介入日志
                    }

                } else {
                    // 业务逻辑上的失败（如：余额不足、参数错误）
                    throw new \Exception(isset($res['msg']) ? $res['msg'] : '支付接口返回未知错误');
                }

            } catch (\Exception $e) {
                // 7. 退款失败
                // 记录错误信息，方便在后台查看原因 (如：商户余额不足)
                $task->status = CommonPayRefund::STATUS_FAIL;
                // 网络层失败(网关类用 RuntimeException 抛)= 请求可能已到网关、结果未知,打上前缀;
                // 下一轮重试据此决定能不能换号重发(见上面 $queryInconclusive 那段)。
                $msg = $e->getMessage();
                if ($e instanceof \RuntimeException && !str_starts_with($msg, self::OUTCOME_UNKNOWN_PREFIX)) {
                    $msg = self::OUTCOME_UNKNOWN_PREFIX . $msg;
                }
                $task->error_msg = substr($msg, 0, 500); // 截取长度防止报错
                $task->updated_at = time();
                // 按失败原因算下次什么时候再试(余额不足隔得久、配置类不再试,见 RefundAttentionService);
                // 记过退款成功的绝不自动再退。0 = 不再自动重试,出现在后台「退款失败处理」等人处理。
                $payOrder = $task->commonPayOrder;
                $payable = $payOrder && RefundAttentionService::isPayment($payOrder->payment);
                $task->next_retry_at = (int)$task->success_at > 0
                    ? 0 : RefundAttentionService::nextRetryAt($task->error_msg, (int)$task->retry_count, $payable, time());
                $task->save();

                // 不再自动重试时,completeRefund 可能已提前返还券/积分/库存,形成"资产已返、钱未退"的中间态,必须人工介入:
                // 升级为 ERROR + 推 Redis 告警列表(refund_failed_alerts);后台「退款失败处理」页和首页提醒也会列出来。
                if ((int)$task->next_retry_at === 0) {
                    Yii::error("[退款告警·需人工介入] 退款失败且不再自动重试 TaskID:{$task->id} mall:{$task->mall_id} source:{$task->source_type}/{$task->source_id} 金额:{$task->refund_price} 已试:{$task->retry_count}次 原因:" . $e->getMessage());
                    try {
                        Yii::$app->redis->lpush('refund_failed_alerts', json_encode([
                            'task_id' => $task->id,
                            'mall_id' => $task->mall_id,
                            'source_type' => $task->source_type,
                            'source_id' => $task->source_id,
                            'refund_price' => $task->refund_price,
                            'error' => substr($e->getMessage(), 0, 200),
                            'at' => time(),
                        ], JSON_UNESCAPED_UNICODE));
                        Yii::$app->redis->ltrim('refund_failed_alerts', 0, 999); // 只保留最近 1000 条
                    } catch (\Throwable $re) {
                        Yii::error("[退款告警] 写 Redis 告警列表失败 TaskID:{$task->id}: " . $re->getMessage());
                    }
                } else {
                    Yii::warning("退款任务执行异常(已试 {$task->retry_count} 次,将在 " . date('m-d H:i', (int)$task->next_retry_at) . " 自动再试) [TaskID:{$task->id}]: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * 这一轮该执行的退款:待处理的,以及失败后约定的重试时间已到的。
     * 记过退款成功的(网关或人工确认过)即使后来被网关回调改回失败,也不在这里自动再退,只交给人(处理台)。
     *
     * 失败记录是「释放额度」的(见 RefundTaskService / CommonPayOrder::getRefundedAmount):同一来源失败后若再建一条新任务,
     * 旧的失败记录仍可能按时重试,就会退两次。目前没有这种业务路径(售后打款后不会回到已同意,取消 / 到账退回只触发一次);
     * 将来要加,先把旧的失败记录处理掉(停掉重试)再建新的。
     */
    public static function dueQuery(int $mallId, int $now): \yii\db\ActiveQuery
    {
        return CommonPayRefund::find()
            ->where(['mall_id' => $mallId, 'is_delete' => 0])
            ->andWhere(['or',
                ['status' => CommonPayRefund::STATUS_PENDING],
                ['and',
                    ['status' => CommonPayRefund::STATUS_FAIL],
                    ['>', 'next_retry_at', 0],
                    ['<=', 'next_retry_at', $now],
                    ['or', ['success_at' => 0], ['success_at' => null]],
                ],
            ]);
    }


    public function rule()
    {
        // 保持每分钟执行一次
        return "*/1 * * * *";
    }

    public function description()
    {
        return "新版定时退款任务 (扫描common_pay_refund表)";
    }
}