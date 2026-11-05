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
/**
 * Created by PhpStorm.
 * User: ganxi
 * Date: 2022/5/26
 * Time: 11:05
 * Note:
 */

namespace app\controllers\notify;

use app\events\FastpayEvent;
use app\models\CommonPayOrder;
use app\models\CommonPayRefund;

// 【新增】引入退款流水模型
use app\models\Mall;
use app\payments\BalancePayment;
use app\services\CommissionService;
use app\services\contracts\SplitGatewayNotifyHandlerInterface;
use app\services\PayoutHandlerRegistry;
use app\split\BaseSplit;
use Yii;
use yii\helpers\Json;

class FastController extends \yii\web\Controller
{
    /**
     * 允许由本端点(/notify/fastpay.php)处理的支付类白名单。
     *
     * 依据:全项目只有这 4 个类会把 notifyUrl 指到 /notify/fastpay.php,
     * 也就是说只有它们会真的收到快付的回调。其余支付类出现在这里一律是异常 ——
     * 要么数据被写坏,要么有人在伪造回调。
     *
     * 尤其要挡住 SystemPayment(人工补单用):它的 handleCallback 读报文里的
     * pay_order_id、且不验签,放进来等于开一个"任意订单置为已支付"的后门。
     * 新增快付通道时记得同步这里。
     */
    private const ALLOWED_PAYMENTS = [
        \app\payments\FastPayAliJsPayment::class,
        \app\payments\FastPayWechatJsPayment::class,
        \app\payments\FastPayMpwxPayment::class,
        \app\payments\FastPayQuickPayment::class,
    ];

    public $enableCsrfValidation = false;

    public function init()
    {
        \Yii::$app->isAdmin = false;
        parent::init();
    }

    /**
     * 支付回调 (包含支付成功 + 退款成功)
     */
    public function actionPay()
    {
        $logPrefix = "[快付 支付/退款回调]";
        Yii::warning("====== {$logPrefix} 开始处理 ======");

        $rawBody = \Yii::$app->request->rawBody;
        Yii::warning("{$logPrefix} 收到原始请求报文: " . $rawBody);
        if (empty($rawBody)) {
            $this->responseJson('FAIL', '请求体为空');
            return;
        }
        $callbackData = Json::decode($rawBody);
        if (!$callbackData) {
            $this->responseJson('FAIL', 'JSON格式无效');
            return;
        }

        $data = $callbackData['data'] ?? null;
        $merOrderNo = $data['merOrderNo'] ?? null; // 商户订单号 或 退款单号
        $transCode = $callbackData['transCode'] ?? '';

        if (!$merOrderNo) {
            $this->responseJson('FAIL', '缺少merOrderNo');
            return;
        }

        Yii::warning($callbackData);

        // =================================================================
        // 【核心修改】 拦截退款回调逻辑，对接 CommonPayRefund 表
        // =================================================================
        if ($transCode == 'NORMAL_ORDER_REFUND') {
            Yii::warning("{$logPrefix} 识别为【退款回调】，单号: {$merOrderNo}");

            // 1. 在流水表中查找 (merOrderNo 对应我们的 out_refund_no)
            /** @var CommonPayRefund $refundTask */
            $refundTask = CommonPayRefund::findOne(['out_refund_no' => $merOrderNo]);

            // 🔒 动手之前先验签。
            //
            // 这里原来**完全不验签**就改状态并触发业务回写(关单/售后完成/佣金扣回),
            // 而退款单号是 `R + YmdHis + mt_rand(1000,9999)`(见 CommonPayRefund::generateRefundNo)
            // —— 时间部分可推测,随机部分只有 9000 种。申请过退款的买家知道自己申请的
            // 大致秒数,穷举约 9000 次就能命中自己的退款单,把它标成成功、触发整套业务回写,
            // 而网关那边根本没退钱。**白拿。**
            //
            // 验签用原支付单的配置(退款单挂着 common_pay_order_id),支付类走与支付分支
            // 同一份白名单;拿不到就拒绝并让网关重推,不做任何写入。
            if ($refundTask && !$this->verifyRefundSign($refundTask, $callbackData, $logPrefix)) {
                $this->responseJson('FAIL', '验签失败');
                return;
            }

            if ($refundTask) {
                // 2. 判断回调状态
                // 假设快付的成功状态字段是 orderStatus 或 status，具体需根据文档确认
                // 这里参考通常逻辑，假设 data['status'] 或 data['orderStatus'] 为 SUCCESS
                $status = $data['status'] ?? $data['orderStatus'] ?? '';

                if (strtoupper($status) === 'SUCCESS') {
                    // 退款成功
                    if ($refundTask->status != CommonPayRefund::STATUS_SUCCESS) {
                        // 状态变化：PENDING/PROCESSING/FAIL → SUCCESS
                        // 意味着 cron 没有同步处理成功（比如超时抛异常被标 FAIL），现在由异步回调兜底把业务状态闭环
                        $refundTask->status = CommonPayRefund::STATUS_SUCCESS;
                        $refundTask->updated_at = time();
                        if (empty($refundTask->success_at)) {
                            $refundTask->success_at = time();
                        }
                        $refundTask->save();
                        Yii::warning("{$logPrefix} 退款流水 {$refundTask->id} 状态已更新为成功");

                        // 【核心】触发业务层同步（关单/售后完成/佣金扣回等）
                        try {
                            \app\services\RefundSuccessDispatcher::dispatch($refundTask);
                        } catch (\Exception $e) {
                            Yii::error("{$logPrefix} 退款成功但业务回写失败 ID:{$refundTask->id} - " . $e->getMessage());
                        }
                    }
                    // else: cron 已经处理过了，notify 只是再来一次确认，幂等跳过
                } else {
                    // 退款失败
                    if ($refundTask->status != CommonPayRefund::STATUS_FAIL) {
                        $refundTask->status = CommonPayRefund::STATUS_FAIL;
                        $refundTask->error_msg = $data['failReason'] ?? '回调通知显示失败';
                        $refundTask->updated_at = time();
                        $refundTask->save();
                        Yii::warning("{$logPrefix} 退款流水 {$refundTask->id} 状态更新为失败: " . $refundTask->error_msg);
                    }
                }

                $this->responseJson('SUCCESS', '退款回调处理完成');
                return;
            } else {
                // 如果找不到流水记录，可能是旧系统的退款，或者单号不匹配
                // 这里可以选择记录日志并返回成功(防止对方一直重试)，或者尝试走旧逻辑
                Yii::warning("{$logPrefix} 未找到对应的 CommonPayRefund 记录，单号: {$merOrderNo}");

                // 如果你确定不再兼容旧数据，这里直接返回 SUCCESS 结束即可
                // 如果想兼容旧代码，可以不 return，让代码继续往下走 (但旧逻辑里 merOrderNo 是 exploded 的)

                // 建议：为了系统稳定性，如果没找到记录，打印日志并告诉对方收到了，避免阻塞
                $this->responseJson('SUCCESS', '未找到本地记录，忽略处理');
                return;
            }
        }

        // =================================================================
        // 下面是原有的【支付回调】逻辑 (保持不变)
        // =================================================================

        // merOrderNo 现在是 **out_trade_no**,不再是 order_no ——
        // PayForm 每次发起支付都会生成 `order_no . '_' . time()` 作为唯一支付单号透传给网关
        // (同一笔订单可以多次发起支付,网关那边单号不能重复)。
        // 这里原先只按 order_no 查、注释也还停在改造前,于是回调进来必然查不到,
        // 直接落到最后的「订单未找到」,连 handleCallback 都进不去 —— 支付成功但订单不置已支付。
        // handleCallback 内部走的也是 findByTradeNo,这里跟它对齐:
        // 当前号 → 历史号(该单重复发起过支付) → order_no(改造前的老单)。
        $order = CommonPayOrder::findByTradeNo($merOrderNo);

        if ($order && $order->mall_id) {
            // 【按单号定支付类,不能用 $order->payment】
            // 主表的 payment 记的是**最后一次**支付尝试,而这笔回调属于 merOrderNo 那一次。
            // 用户在余额/支付宝之间来回切时两者必然对不上,后果见
            // CommonPayOrder::resolvePaymentByTradeNo 的注释(真实事故:57311,¥299 掉单)。
            $paymentClass = CommonPayOrder::resolvePaymentByTradeNo($merOrderNo, $order);

            Yii::warning("{$logPrefix} 找到支付订单记录 ID: {$order->id}, 商城ID: {$order->mall_id}, "
                . "支付类: {$paymentClass}(订单当前字段: {$order->payment})");
            \Yii::$app->mall = Mall::findOne($order->mall_id);
            try {
                if ($paymentClass === BalancePayment::class) {
                    // 登记说这个号是余额支付发出去的 —— 但**余额支付根本不经过快付网关**,
                    // 所以「登记为余额 + 收到快付回调」这个组合本身就证明登记值是错的,
                    // 不能据此把回调丢掉。
                    //
                    // 已知成因:商户单号原来是 order_no + time(),同一秒内点两次支付会拿到
                    // 完全相同的号,登记行停在第一次那次的支付类。生成侧已由
                    // CommonPayOrder::nextTradeNo() 查重治本,但**存量错误登记行还在**。
                    // 真实案例:支付单 57345,同秒先点余额再点支付宝,支付宝 ¥1495 的
                    // SUCCESS 回调若按登记值分发就会被丢。
                    //
                    // 此时订单字段反而是可信的那个(它记着最后一次、也就是真正发给网关的那次),
                    // 回落过去再试;回落不到可用的网关类才放弃。
                    // 回落值同样只认白名单 —— 人工补过单的订单 payment 是 SystemPayment,
                    // 放它进来就等于绕开上面那道白名单(见 ALLOWED_PAYMENTS 的注释)。
                    $fallback = (string) $order->payment;
                    if (in_array($fallback, self::ALLOWED_PAYMENTS, true)) {
                        Yii::error("{$logPrefix} 单号 {$merOrderNo} 登记为余额支付却收到快付回调,"
                            . "登记值有误,回落订单字段 {$fallback}。pay_order_id={$order->id}", 'payment');
                        $paymentClass = $fallback;
                    } else {
                        // 订单字段也是余额/不可用 —— 无从判断该交给谁,只能放弃。
                        // 仍回 SUCCESS 让网关停止重推,避免一条异常把回调队列堵死;
                        // 真出问题靠这条 error 告警发现。
                        Yii::error("{$logPrefix} 单号 {$merOrderNo} 登记为余额支付却收到快付回调,"
                            . "且订单字段({$fallback})也无法回落,已跳过。pay_order_id={$order->id}", 'payment');
                        $this->responseJson('SUCCESS', '成功');
                        return;
                    }
                }

                // 类名来自库里的字段,反射实例化前必须过白名单 —— 只认真正会把
                // notifyUrl 指到 /notify/fastpay.php 的那几个快付类(见 self::ALLOWED_PAYMENTS)。
                //
                // 🔒 这不只是防御性编程,是堵一个真实的漏洞:
                // SystemPayment::handleCallback 读的是报文里的 pay_order_id(而不是我们查出来的
                // 那个订单),并且**全程不验签** —— 只要能让本控制器分发到它,再在报文里塞一个
                // pay_order_id,就能把任意订单标成已支付并触发发货/分润。
                // 而「订单被人工补过单」时 $order->payment 恰恰就是 SystemPayment,
                // 该订单的商户单号一旦泄露(它就印在回调报文里),这条路就通了。
                // 白名单之外一律拒绝,不给反射任何自由度。
                if (!in_array($paymentClass, self::ALLOWED_PAYMENTS, true)) {
                    Yii::error("{$logPrefix} 单号 {$merOrderNo} 解析出的支付类不在快付白名单内: "
                        . "'{$paymentClass}',已拒绝。pay_order_id={$order->id}", 'payment');
                    $this->responseJson('FAIL', '支付类不可用');
                    return;
                }

                // 实例化支付插件处理支付成功逻辑
                $payment = \Yii::createObject($paymentClass, [$order->mall_id]);
                $response = $payment->handleCallback($callbackData);

                Yii::$app->response->data = $response;
                Yii::$app->response->send();
                return;
            } catch (\Throwable $e) {
                // 接 Throwable 而非 Exception:PHP 8 下类型错误等属于 \Error,用 \Exception
                // 接不住 —— 会一路冒到框架变成 500。对网关来说 500 也是失败、也会重推,
                // 但我们就失去了这条带单号的 error 日志,排查时只剩一个没有上下文的 500。
                Yii::error("{$logPrefix} 处理订单 '{$merOrderNo}' 的回调时发生异常: " . $e->getMessage());
                // 不把 $e->getMessage() 回给网关:异常文本可能含内部路径/SQL,
                // 而这个端点是公网可达的。详情进日志,对外只给一句话。
                $this->responseJson('FAIL', '回调处理失败');
                return;
            }
        }

        $this->responseJson('FAIL', '订单未找到');
    }

    /**
     * 退款回调验签。
     *
     * 退款单本身不存支付配置,得顺着 common_pay_order_id 找回原支付单 —— 那上面才有
     * 下单时的 payment_config(含平台公钥)。支付类同样过白名单,不给反射自由度。
     *
     * 任何一步拿不到东西都返回 false(拒绝 + 让网关重推),**绝不放行** ——
     * 这个方法存在的全部意义就是不让未验证的报文改动退款状态。
     *
     * 注:快付三个通道的 platformPublicKey 实测是同一把(applicationId/appid 才不同),
     * 所以即使原支付单的支付类与退款走的通道不完全对应,验签也能过。
     */
    private function verifyRefundSign(CommonPayRefund $refundTask, array $callbackData, string $logPrefix): bool
    {
        $sign = (string) ($callbackData['sign'] ?? '');
        if ($sign === '') {
            Yii::error("{$logPrefix} 退款回调缺少 sign,拒绝。out_refund_no={$refundTask->out_refund_no}", 'payment');
            return false;
        }

        $payOrder = CommonPayOrder::findOne((int) $refundTask->common_pay_order_id);
        if (!$payOrder || empty($payOrder->payment_config)) {
            Yii::error("{$logPrefix} 退款单 {$refundTask->out_refund_no} 找不到原支付单或其配置,拒绝。"
                . "common_pay_order_id={$refundTask->common_pay_order_id}", 'payment');
            return false;
        }

        $paymentClass = (string) $payOrder->payment;
        if (!in_array($paymentClass, self::ALLOWED_PAYMENTS, true)) {
            Yii::error("{$logPrefix} 退款单 {$refundTask->out_refund_no} 的原支付类不在快付白名单内:"
                . "'{$paymentClass}',拒绝。", 'payment');
            return false;
        }

        try {
            $cfg = Json::decode($payOrder->payment_config);
            $publicKey = is_array($cfg) ? (string) ($cfg['platformPublicKey'] ?? '') : '';
            if ($publicKey === '') {
                Yii::error("{$logPrefix} 退款单 {$refundTask->out_refund_no} 的支付配置缺平台公钥,拒绝。", 'payment');
                return false;
            }
            $payment = \Yii::createObject($paymentClass, [$payOrder->mall_id]);
            // 白名单里那四个快付类都实现了 _verifySign(算法逐字一致),但基类没有声明它 ——
            // 显式确认一次:万一以后往白名单加了没有该方法的类,宁可拒绝也不能放行。
            if (!method_exists($payment, '_verifySign')) {
                Yii::error("{$logPrefix} 支付类 {$paymentClass} 没有 _verifySign,无法验签,拒绝。", 'payment');
                return false;
            }
            if (!$payment->_verifySign(Json::encode($callbackData), $sign, $publicKey)) {
                Yii::error("{$logPrefix} 退款单 {$refundTask->out_refund_no} 验签不通过,拒绝。", 'payment');
                return false;
            }
        } catch (\Throwable $e) {
            Yii::error("{$logPrefix} 退款单 {$refundTask->out_refund_no} 验签过程异常,拒绝: "
                . $e->getMessage(), 'payment');
            return false;
        }

        return true;
    }

    public function actionFund()
    {
        $logPrefix = "[快付 代付打款回调]";
        Yii::warning("====== {$logPrefix} 开始处理 ======");
        $rawBody = \Yii::$app->request->rawBody;
        Yii::warning("{$logPrefix} 收到原始请求报文: " . $rawBody);
        $callbackData = Json::decode($rawBody);
        Yii::warning($callbackData);
        $data = $callbackData['data'] ?? null;
        if ($data) {
            $event = new FastpayEvent();
            $event->body = Json::encode($data);
            // 同时带上全量报文:监听器要拿它验签(只有 data 段算不出签名)
            $event->raw = $rawBody;
            Yii::$app->trigger(FastpayEvent::EVENT_AGENT_PAY, $event);
            // 监听器遇到「可恢复的失败」(公钥没配、回流异常)会置 retry —— 此时必须回 FAIL
            // 让快付重推。原来无脑回 SUCCESS:网关认为已送达不再重推,这笔打款结果就永久丢了,
            // 与"压根没有监听器"是同一个后果。
            if (!empty($event->retry)) {
                Yii::error("{$logPrefix} 处理未完成,已请求网关重推");
                $this->responseJson('FAIL', '处理未完成，请重试');
                return;
            }
            $this->responseJson('SUCCESS', '代付打款回调成功');
            return;
        }
        // 此处无业务逻辑，可根据需要添加
        $this->responseJson('FAIL', '代付打款回调');
        Yii::warning("====== {$logPrefix} 处理结束 ======");
    }

    /**
     * 分账进件通知
     */
    public function actionRegister()
    {
        $logPrefix = "[快付 进件回调]";
        Yii::warning("====== {$logPrefix} 开始处理 ======");
        $rawBody = \Yii::$app->request->rawBody;
        Yii::warning("{$logPrefix} 收到原始请求报文: " . $rawBody);

        $callbackData = Json::decode($rawBody);
        $data = $callbackData['data'] ?? null;
        Yii::warning("{$logPrefix} 解析后数据, 外部商户号: " . ($data['outMerCode'] ?? '未提供'));

        // 这里应该调用 FastPaySplit->handleSplitNotify() 方法来处理业务
        $this->responseJson('SUCCESS', '进件回调已收到');
        Yii::warning("====== {$logPrefix} 处理结束 ======");
    }

    /**
     * 分账执行结果回调
     */
    public function actionSplit()
    {
        $logPrefix = "[快付 分账回调]";
        Yii::warning("====== {$logPrefix} 开始处理 ======");
        $rawBody = \Yii::$app->request->rawBody;
        Yii::warning("{$logPrefix} 收到原始请求报文: " . $rawBody);

        $callbackData = Json::decode($rawBody);
        $data = $callbackData['data'] ?? null;
        $merOrderNo = $data['merOrderNo'] ?? null; // 这是分账单号
        Yii::warning("{$logPrefix} 回调数据解析成功。分账单号: " . ($merOrderNo ?: '未提供') . ", 状态: " . ($data['status'] ?? '未提供'));

        if (!$merOrderNo) {
            Yii::error("{$logPrefix} 回调数据中未找到 'merOrderNo' (分账单号)。");
            $this->responseJson('FAIL', '缺少merOrderNo');
            Yii::warning("====== {$logPrefix} 处理结束 ======");
            return;
        }
        $data['status'] = BaseSplit::transStatus($data['status'] ?? '');

        // 分账记录的落库与放款实现属于收银台插件;核心 notify 只做解析,委托给注册 handler,自身不认识分账模型。
        $handler = PayoutHandlerRegistry::handler(CommissionService::PAYOUT_TO_EXTERNAL_SPLIT);
        if ($handler instanceof SplitGatewayNotifyHandlerInterface) {
            $ok = $handler->onGatewayNotify($merOrderNo, $data);
            $this->responseJson($ok ? 'SUCCESS' : 'FAIL', $ok ? '分账回调已收到' : '分账回调处理失败');
        } else {
            Yii::error("{$logPrefix} 未找到分账回调处理插件(收银台未安装或未启用?),单号: {$merOrderNo}");
            $this->responseJson('FAIL', '无分账处理插件');
        }
        Yii::warning("====== {$logPrefix} 处理结束 ======");
    }

    /**
     * 结算回调
     */
    public function actionSettle()
    {
        $logPrefix = "[快付 结算回调]";
        Yii::warning("====== {$logPrefix} 开始处理 ======");

        $rawBody = \Yii::$app->request->rawBody;
        Yii::warning("{$logPrefix} 收到原始请求报文: " . $rawBody);

        if (empty($rawBody)) {
            $this->responseJson('FAIL', '请求体为空');
            return;
        }

        $callbackData = Json::decode($rawBody);
        if (!$callbackData) {
            $this->responseJson('FAIL', 'JSON格式无效');
            return;
        }

        $data = $callbackData['data'] ?? null;
        $merOrderNo = $data['merOrderNo'] ?? null; // 这是结算单号
        Yii::warning("{$logPrefix} 回调数据解析成功。结算单号: " . ($merOrderNo ?: '未提供') . ", 状态: " . ($data['status'] ?? '未提供'));
        if (!$merOrderNo) {
            $this->responseJson('FAIL', '缺少merOrderNo');
            return;
        }

        $this->responseJson('SUCCESS', '订单结算回调已收到');
        Yii::warning("====== {$logPrefix} 处理结束 ======");
    }

    /**
     * 灵工下发异步通知(入口 web/lg/fastpay.php)。
     *
     * 原实现只打日志、无条件回 SUCCESS,不做任何业务处理 —— 与代付那次事故同因:
     * 网关据此认为已送达、不再重推,下发结果就此丢失,失败的单永远不会置「打款失败」。
     * (更早之前入口文件 web/lg/fastpay.php 都不存在,回调直接 404。)
     *
     * 现与 actionFund(代付)同构:核心只收报文 + 抛事件,由 withdraw 插件按 merOrderNo
     * 认领并回流结果。核心因此不需要认识提现单;插件卸载后回调自然无人处理。
     */
    public function actionLg()
    {
        $logPrefix = "[快付 灵工下发回调]";
        Yii::warning("====== {$logPrefix} 开始处理 ======");

        $rawBody = \Yii::$app->request->rawBody;
        Yii::warning("{$logPrefix} 收到原始请求报文: " . $rawBody);

        if (empty($rawBody)) {
            $this->responseJson('FAIL', '请求体为空');
            return;
        }
        $callbackData = Json::decode($rawBody);
        $data = is_array($callbackData) ? ($callbackData['data'] ?? null) : null;
        if (!$data) {
            $this->responseJson('FAIL', 'JSON格式无效或缺少 data');
            return;
        }

        $event = new FastpayEvent();
        $event->body = Json::encode($data);
        // 验签要整份报文,只有 data 段签不出来
        $event->raw = $rawBody;
        Yii::$app->trigger(FastpayEvent::EVENT_LG_GRANT, $event);

        // 监听器遇到「可恢复的失败」(公钥没配、回流异常)会置 retry → 回 FAIL 让快付重推;
        // 无脑回 SUCCESS 会让网关不再重推,真实结果永久丢失。
        if (!empty($event->retry)) {
            Yii::error("{$logPrefix} 处理未完成,已请求网关重推");
            $this->responseJson('FAIL', '处理未完成，请重试');
            return;
        }
        $this->responseJson('SUCCESS', '灵工下发回调成功');
    }

    // 辅助方法：统一响应格式
    private function responseJson($code, $desc)
    {
        Yii::$app->response->data = ['respCode' => $code, 'respDesc' => $desc];
        Yii::$app->response->send();
    }
}