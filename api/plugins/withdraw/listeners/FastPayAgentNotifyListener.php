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

namespace app\plugins\withdraw\listeners;

use app\events\FastpayEvent;
use app\listeners\BaseListener;
use app\funds\FastPayAgentPayFund;
use app\funds\FastPayFlexibleLaborFund;
use app\plugins\withdraw\models\WithdrawOrder;
use app\services\payout\PayoutResultDispatcher;
use Yii;
use yii\helpers\Json;

/**
 * 快付「代付打款」异步通知落地。
 *
 * ## 修的是什么
 * 核心 FastController::actionFund 收到代付回调后只 trigger 了 fast_agent_pay 事件,
 * 而**全项目没有任何监听器** —— 回调进来触发一个空事件、随即给快付回 SUCCESS(快付因此
 * 认为已通知到、不再重推),打款结果就此永久丢失。
 *
 * 后果(生产实测):代付单从 2026-02 起 2212 笔全部停留在「已到账」、**一笔「打款失败」都
 * 没有产生过**,包括网关明确回了 {"status":"FAIL","desc":"收款账户无效"} 的那些 ——
 * 用户没收到钱,系统显示已到账。
 *
 * ## 为什么放在插件而不是核心
 * 回调要按 merOrderNo 找 WithdrawOrder,那是本插件的模型;核心只负责抛事件(通知型),
 * 插件来接。插件卸载后回调自然无人处理,核心不需要认识提现单。
 *
 * ## 回流路径
 * 与线上支付回调同一套:规范化成 ['status'=>success|failed|doing] 交
 * PayoutResultDispatcher → WithdrawOrder::onPayoutResult → markSuccess / markPayoutFailed。
 * 失败**不自动退款**(与同步链路一致):置「打款失败」、资金保持冻结,由管理员决定重试或退回。
 */
class FastPayAgentNotifyListener extends BaseListener
{
    /** 回调 data.status → 内部规范化状态 */
    private const STATUS_MAP = [
        'SUCCESS' => 'success',
        'FAIL'    => 'failed',
        'FAILED'  => 'failed',
        'DOING'   => 'doing',
    ];

    public function register()
    {
        // 代付与灵工下发的回调报文同构(merOrderNo + data.status + RSA2 签名),
        // 差别只在「这笔单该归哪个网关」,故共用一套处理,用期望网关类区分。
        $map = [
            FastpayEvent::EVENT_AGENT_PAY => FastPayAgentPayFund::class,
            FastpayEvent::EVENT_LG_GRANT  => FastPayFlexibleLaborFund::class,
        ];
        foreach ($map as $eventName => $expectFund) {
            Yii::$app->on($eventName, function (FastpayEvent $e) use ($expectFund) {
                try {
                    $this->handle($e, $expectFund);
                } catch (\Throwable $ex) {
                // 异常不冒泡(冒泡会变成 500,网关拿不到我们约定的 respCode 报文),
                // 改为置 retry 让核心回 FAIL —— 数据库抖动这类瞬时故障重推一次就好了。
                    $e->retry = true;
                    Yii::error(self::tag($expectFund) . ' 处理异常,已请求重推: ' . $ex->getMessage(), 'withdraw');
                }
            });
        }
    }

    /** 日志前缀:让两条链路的日志一眼可分 */
    private static function tag(string $fundClass): string
    {
        return $fundClass === FastPayFlexibleLaborFund::class ? '[快付灵工回调]' : '[快付代付回调]';
    }

    private function handle(FastpayEvent $e, string $expectFund): void
    {
        $tag = self::tag($expectFund);
        // 验签要整份报文($e->raw);$e->body 只有 data 段,签不出来。
        $raw = (string)($e->raw ?? '');
        $message = $raw !== '' ? Json::decode($raw) : null;
        $data = is_array($message) ? ($message['data'] ?? null) : null;
        if (!is_array($data)) {
            $data = Json::decode((string)$e->body);
            $message = null;   // 拿不到全量报文 = 无法验签,下面会直接拒绝
        }
        if (!is_array($data) || empty($data['merOrderNo'])) {
            Yii::warning($tag . ' 报文缺少 merOrderNo,跳过', 'withdraw');
            return;
        }

        $merOrderNo = (string)$data['merOrderNo'];

        // 代付的 merOrderNo 就是提现单号(payout 时以 order_no 上送,见 FastPayAgentPayFund::payout)
        $order = WithdrawOrder::findOne(['order_no' => $merOrderNo, 'is_delete' => 0]);
        if (!$order) {
            // 不是本插件的单(可能是老 cash 链路或别的业务),静默放过,别刷 error
            Yii::info("{$tag} 未找到提现单 {$merOrderNo},非本插件业务", 'withdraw');
            return;
        }

        // ── 网关归属校验:这笔单现在是不是还归快付代付管 ──
        // 典型场景:快付返回 DOING 迟迟不给结果 → 运营改走「线下打款」(fund_class 被改写成
        // ManualFund、钱已线下转出)→ 快付的延迟回调这时才到。若不拦,一条 FAIL 就会把
        // 线下已完成的打款推翻成「打款失败」,或一条 SUCCESS 把线下单认成快付到账、对账错位。
        // 不请求重推:归属已变是既成事实,再推多少次结论都一样。
        if ($order->fund_class !== $expectFund) {
            Yii::warning(
                "{$tag} {$merOrderNo} 当前网关已是 {$order->fund_class}(多半已改走线下打款),"
                . "忽略这条迟到的快付回调",
                'withdraw'
            );
            return;
        }

        // ── 验签:回调是能把提现改成「已到账」的写操作入口,必须证明来自快付 ──
        if (!is_array($message)) {
            // 报文残缺 → 无法验签。属可恢复(重推一次多半就完整了),请求重推。
            $e->retry = true;
            Yii::error("{$tag} {$merOrderNo} 无法取得完整报文,拒绝处理并请求重推(不可验签)", 'withdraw');
            return;
        }
        try {
            /** @var FastPayAgentPayFund $fund */
            $fund = Yii::createObject($expectFund, [(int)$order->mall_id]);
            if (!$fund->verifyNotifySign($message)) {
                // 签名对不上 = 报文不是快付发的(或被篡改)。重推同一份还是对不上,
                // 故**不**请求重推,直接回 SUCCESS 打发掉,避免被人拿来打洪水。
                Yii::error("{$tag} {$merOrderNo} 验签失败,拒绝处理(疑似伪造)", 'withdraw');
                return;
            }
        } catch (\Throwable $ex) {
            // 抛异常 ≠ 验签不通过:这里是**我们自己**的问题(平台公钥没配 / 格式错)。
            // 若也直接回 SUCCESS,网关就不再重推,真实打款结果永久丢失 —— 正是本次事故的形态。
            // 故请求重推:配置修好后的下一次推送就能正常落地。
            $e->retry = true;
            Yii::error("{$tag} {$merOrderNo} 验签异常(多半是平台公钥未配置),已请求重推: " . $ex->getMessage(), 'withdraw');
            return;
        }

        $rawStatus = strtoupper((string)($data['status'] ?? ''));
        $status = self::STATUS_MAP[$rawStatus] ?? '';
        if ($status === '' || $status === 'doing') {
            // 未知状态**绝不猜**:未知 ≠ 成功,也 ≠ 失败。保持「打款中」等下一次通知/人工。
            Yii::warning("{$tag} {$merOrderNo} 状态 '{$rawStatus}' 未识别或处理中,保持打款中", 'withdraw');
            return;
        }

        // 网关流水号:优先平台流水,回落渠道流水。这是事后对账的唯一凭据 ——
        // 原先 2212 笔代付单 payout_no 全空,出问题时无从与快付后台核对。
        $payoutNo = (string)($data['sysOrderNo'] ?? ($data['channelFlowNo'] ?? ''));

        $result = ['status' => $status, 'payout_no' => $payoutNo];
        if ($status === 'failed') {
            $result['message'] = '快付代付失败：' . ($data['desc'] ?? '未知原因');
        }

        Yii::warning(
            "{$tag} {$merOrderNo} 验签通过 → {$status}"
            . ($payoutNo !== '' ? " 流水={$payoutNo}" : '')
            . ($status === 'failed' ? ' 原因=' . ($data['desc'] ?? '') : ''),
            'withdraw'
        );

        // 幂等由 WithdrawService 的条件 UPDATE 抢占保证:已是终态的单再收到重推不会被改
        // (那种情况下 onPayoutResult 内部吞掉抢占冲突并 warning,dispatch 仍返回 true)。
        // 返回 false = 分发链路本身出了问题(类不存在/未实现契约/抛异常),结果没落地 → 请求重推。
        if (!PayoutResultDispatcher::dispatch(WithdrawOrder::class, (int)$order->id, $result)) {
            $e->retry = true;
            Yii::error("{$tag} {$merOrderNo} 结果回流失败,已请求重推", 'withdraw');
        }
    }
}
