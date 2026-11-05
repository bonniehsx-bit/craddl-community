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

namespace app\plugins\withdraw\services;

use app\helpers\Helper;
use app\models\User;
use app\models\UserSettlementInfo;
use app\plugins\withdraw\models\Channel;
use app\plugins\withdraw\models\WithdrawOrder;
use Yii;
use yii\helpers\Json;

/**
 * 提现核心编排服务（闭环状态机）
 *
 * 申请 → [审核] → 待打款 → 打款(网关) → 到账 / 失败退回
 *
 * 金融硬防线：
 *  - 后端重校验所有规则（资格/限额/倍数/最小额），绝不信任前端
 *  - 扣减走 AssetService（updateAllCounters 原子 `WHERE>=`），并发与超额天然挡掉
 *  - 短窗口防重 + order_no 唯一索引，挡双击/重放
 *  - 落单失败补偿退款；驳回/打款失败原路退回
 */
class WithdrawService
{
    // PayoutLog.source_type 存源单模型 FQCN（平台多态约定），让 PayoutResultDispatcher 能反查回流
    const SOURCE_TYPE = WithdrawOrder::class;
    const DEDUP_WINDOW = 10;             // 防重时间窗（秒）

    /**
     * 取某用户在某商城下「可用」的提现渠道（资格并集）。
     *
     * @param string $assetType 传入则只返回支持该资金来源的渠道
     * @return Channel[]
     */
    public function getAvailableChannels(User $user, int $mallId, string $assetType = ''): array
    {
        $userType = UserTypeResolver::resolve($user->id, $mallId);

        $channels = Channel::find()
            ->where(['mall_id' => $mallId, 'is_delete' => 0, 'status' => Channel::STATUS_ON])
            ->orderBy('sort ASC, id DESC')
            ->all();

        $result = [];
        foreach ($channels as $c) {
            if (!$c->allowsUserType($userType)) {
                continue;
            }
            if ($assetType !== '' && !$c->allowsAssetType($assetType)) {
                continue;
            }
            $result[] = $c;
        }
        return $result;
    }

    const ONBOARD_URL = '/others/fund-onboard/fund-onboard'; // 复用既有收款资料页

    /**
     * 资料/资格判定：委托给渠道自己的网关 checkUserEligibility()。
     *
     * @return array ['is_eligible'=>bool, 'message'=>string, 'require_onboarding'=>bool, 'redirect_url'=>string]
     *               destination='balance'（内部转余额）无需资料，恒通过。
     */
    public function checkEligibility(User $user, Channel $channel): array
    {
        // 内部转余额：无需收款资料
        if ($channel->destination === Channel::ASSET_BALANCE) {
            return ['is_eligible' => true, 'message' => '', 'require_onboarding' => false];
        }
        $fundClass = $channel->fund_class;
        if (!class_exists($fundClass) || !is_subclass_of($fundClass, \app\funds\BaseFund::class)) {
            return ['is_eligible' => false, 'message' => '打款网关无效', 'require_onboarding' => false];
        }
        try {
            /** @var \app\funds\BaseFund $fund */
            $fund = Yii::createObject($fundClass, [(int)$channel->mall_id]);
            $result = $fund->checkUserEligibility(['user_id' => $user->id]);
        } catch (\Throwable $e) {
            Yii::warning('[withdraw] 资格校验异常：' . $e->getMessage());
            return ['is_eligible' => false, 'message' => '资格校验失败，请稍后再试', 'require_onboarding' => false];
        }

        $eligible = !empty($result['is_eligible']);
        $requireOnboarding = !empty($result['require_onboarding']);
        $out = [
            'is_eligible'        => $eligible,
            'message'            => $result['message'] ?? ($eligible ? '' : '收款资料不完整'),
            'require_onboarding' => $requireOnboarding,
        ];
        // 需进件 → 给前端跳转地址(带 destination 供 UX + fund_class 供精确进件路由)
        // channel 保留 destination(前端页面标题/银行卡校验等语义分支仍按它走),
        // fund_class 才是进件真正落地的网关——两者都传,前端跳转时一起带上。
        if (!$eligible && $requireOnboarding) {
            $out['redirect_url'] = self::ONBOARD_URL;
            $out['channel'] = $channel->destination;
            $out['fund_class'] = $channel->fund_class;
        }
        return $out;
    }

    /**
     * 提交提现申请。成功返回 WithdrawOrder，失败抛 \Exception（消息可直接给用户）。
     *
     * @param array $account 收款账户原始数据（bank/alipay 等需要；balance/manual 可空）
     * @throws \Throwable
     */
    public function apply(User $user, int $mallId, Channel $channel, string $assetType, $amount, array $account = []): WithdrawOrder
    {
        // ── 0. 账号状态硬拦截 ──
        // 提现是资金出口,不能把安全全押在登录鉴权单点:
        // 登录闸(checkBanStatus / LoginFilter / invalidateUserAuth)拦的是「能不能进来」,
        // 一旦有旁路(旧 token 未过期、内部调用、后台代操作),资金层就是最后一道门。
        // 已注销 / 被拉黑一律不得发起提现。
        if ((int)$user->is_delete === 1 || (int)$user->status !== User::STATUS_NORMAL) {
            throw new \Exception('账号状态异常,无法提现');
        }

        // ── 1. 资金/资格校验（全部后端重校验）──
        if (!AssetService::isValid($assetType)) {
            throw new \Exception('资金来源类型非法');
        }
        // 插件总开关 + 该资金来源入口开关（=全局开 且 该资产开），防绕过 prepare 直连 apply
        if (!\app\plugins\withdraw\forms\admin\setting\SettingForm::entryEnabled($mallId, $assetType)) {
            throw new \Exception('该资金来源提现暂未开放');
        }
        if ($channel->mall_id != $mallId || $channel->is_delete || $channel->status != Channel::STATUS_ON) {
            throw new \Exception('提现渠道不可用');
        }
        $userType = UserTypeResolver::resolve($user->id, $mallId);
        if (!$channel->allowsUserType($userType)) {
            throw new \Exception('当前账号类型不支持该提现渠道');
        }
        if (!$channel->allowsAssetType($assetType)) {
            throw new \Exception('该渠道不支持所选资金来源');
        }

        // 金额规范化（bc 定点，2 位）
        if (!is_numeric($amount) || bccomp((string)$amount, '0', 2) <= 0) {
            throw new \Exception('提现金额必须大于0');
        }
        $amount = bcadd((string)$amount, '0', 2);

        // 最小额
        if (bccomp($channel->min_amount, '0', 2) > 0 && bccomp($amount, $channel->min_amount, 2) < 0) {
            throw new \Exception('低于最小提现额 ' . $channel->min_amount);
        }
        // 单笔上限
        if (bccomp($channel->max_amount, '0', 2) > 0 && bccomp($amount, $channel->max_amount, 2) > 0) {
            throw new \Exception('超过单笔上限 ' . $channel->max_amount);
        }
        // 提现倍数
        if (bccomp($channel->multiple, '0', 2) > 0 && bccomp(bcmod($amount, $channel->multiple, 2), '0', 2) !== 0) {
            throw new \Exception('提现金额须为 ' . $channel->multiple . ' 的整数倍');
        }

        // ── 1.5 资料/资格硬门禁（防御纵深：即便前端跳过校验，后端也拦住）──
        $elig = $this->checkEligibility($user, $channel);
        if (empty($elig['is_eligible'])) {
            throw new \Exception($elig['message'] ?: '请先完善收款资料');
        }

        // ── 1.6 并发互斥锁（Redis SET NX）：把「限额→防重→扣减→落单」整段串行化 ──
        // 下方 step3 的 DB 防重是 check-then-act：两个并发请求会在彼此 insert 提交前都判定「不重复」
        // 而双双落单（双击/重放打到不同 worker），仅靠它挡不住「真并发」→ 可能重复提现。
        // 锁 key 取 user+channel+asset（不含 amount）：同用户同渠道同资产的并发申请整体串行，
        // 既盖住「同额重复提交→重复提现」也盖住「不同金额并发撞破当日限额」。抢不到即拒。
        // finally 释放：成功后由 DB 防重接管 10s 窗口的后续重复；失败即时释放，不阻塞用户重试。
        // TTL=DEDUP_WINDOW 仅作进程崩溃兜底（apply 实际耗时远小于此）。
        $lockKey = "withdraw:apply:{$mallId}:{$user->id}:{$channel->id}:{$assetType}";
        if (!Yii::$app->redis->executeCommand('SET', [$lockKey, 1, 'NX', 'EX', self::DEDUP_WINDOW])) {
            throw new \Exception('请勿重复提交');
        }

        try {
            // ── 2. 当日限额（INT 时间戳比较，避免字符串日期陷阱）──
            $this->checkDayLimits($user->id, $channel, $amount);

            // ── 2.5 全局当日提现次数（跨所有渠道统一计数;0=不限,后台 withdraw_setting.day_limit_count 可配）──
            $this->checkGlobalDayTimes($user->id, $mallId);

            // ── 3. 短窗口防重（挡双击/重放）──
            $dupExists = WithdrawOrder::find()
                ->where([
                    'user_id' => $user->id,
                    'channel_id' => $channel->id,
                    'asset_type' => $assetType,
                    'amount' => $amount,
                    'is_delete' => 0,
                ])
                ->andWhere(['not in', 'status', [WithdrawOrder::STATUS_REJECTED, WithdrawOrder::STATUS_FAILED, WithdrawOrder::STATUS_RETURNED]])
                ->andWhere(['>', 'created_at', time() - self::DEDUP_WINDOW])
                ->exists();
            if ($dupExists) {
                throw new \Exception('请勿重复提交');
            }

            // ── 4. 到账计算（bc 定点）──
            $calc = AmountCalculator::calc($amount, (string)$channel->fee_rate, (string)$channel->tax_rate);
            if (bccomp($calc['actual'], '0', 2) <= 0) {
                throw new \Exception('扣除手续费/税费后到账金额为0，无法提现');
            }

            // ── 5. 账户与展示快照（账户只来自已验证结算资料，不信前端）──
            [$payoutDisplay, $rawAccount] = $this->resolveAccount($mallId, $channel, $user);
            $snapshot = [
                'fund_class'   => $channel->fund_class,
                'destination'  => $channel->destination,
                'fee_rate'     => (string)$channel->fee_rate,
                'tax_rate'     => (string)$channel->tax_rate,
                'account'      => $rawAccount,
                // 快照用户下单平台，打款时传给网关（网关从上下文取 platform，不查业务模型）
                'platform'     => (string)(Yii::$app->platform ?? ''),
            ];

            // ── 6+7. 扣减 + 落单：单一事务保证「同生共死」──
            // AssetService::deduct 内部走 helper 的 updateAllCounters `WHERE field>=`（原子防超额），
            // 在外层事务内表现为 savepoint；落单失败则整体回滚，扣减一并撤销，杜绝「钱扣了单没落」。
            $assetLabel = AssetService::label($assetType);
            $orderNo = Helper::createOrderNo('WD', 6);

            $txn = Yii::$app->db->beginTransaction();
            try {
                // 幂等键=提现单号(先于扣减生成):同一单重试/重入不会双扣;
                // 重复提交是新单号,由上面的 $dupExists 校验拦截
                AssetService::deduct($user->id, $assetType, $amount, "{$assetLabel}提现申请 {$orderNo}", "{$assetLabel}提现", 'withdraw_deduct_' . $orderNo);

                $order = new WithdrawOrder();
                $order->mall_id       = $mallId;
                $order->user_id       = $user->id;
                $order->order_no      = $orderNo;
                $order->channel_id    = $channel->id;
                $order->channel_name  = $channel->name;
                $order->fund_class    = $channel->fund_class;
                $order->destination   = $channel->destination;
                $order->user_type     = $userType;
                $order->asset_type    = $assetType;
                $order->amount        = $calc['amount'];
                $order->fee           = $calc['fee'];
                $order->tax           = $calc['tax'];
                $order->actual_amount = $calc['actual'];
                $order->fee_rate      = $channel->fee_rate;
                $order->tax_rate      = $channel->tax_rate;
                $order->payout_info   = $payoutDisplay ? Json::encode($payoutDisplay) : null;
                $order->snapshot      = Json::encode($snapshot);
                $order->status        = $channel->need_audit ? WithdrawOrder::STATUS_PENDING_AUDIT : WithdrawOrder::STATUS_PENDING_PAYOUT;
                $order->user_remark   = isset($account['remark']) ? (string)$account['remark'] : null;

                if (!$order->save()) {
                    throw new \Exception('落单失败：' . Json::encode($order->errors));
                }
                $txn->commit();
            } catch (\Throwable $e) {
                $txn->rollBack(); // 扣减与落单整体回滚，无需补偿退款
                Yii::error("[withdraw] 提现申请失败已回滚 user={$user->id} no={$orderNo}：" . $e->getMessage(), 'withdraw');
                throw $e;
            }

            // 提现到商城余额(BalanceFund) + 免审核 = 内部转账，即时到账，一步到位（无需后台审核/打款）。
            // 对齐老 cash 的"提现到余额免审"逻辑。失败则保持待打款，后台可手动打款，不影响已落单。
            if ($order->status === WithdrawOrder::STATUS_PENDING_PAYOUT
                && $channel->fund_class === \app\funds\BalanceFund::class) {
                try {
                    $this->payout($order);
                } catch (\Throwable $e) {
                    Yii::warning("[withdraw] 提现到余额自动到账失败 {$order->order_no}，保持待打款：" . $e->getMessage(), 'withdraw');
                }
            }

            return $order;
        } finally {
            // 释放互斥锁（成功/失败/异常均释放）；崩溃未释放时由 TTL 兜底过期
            Yii::$app->redis->executeCommand('DEL', [$lockKey]);
        }
    }

    /**
     * 审核：通过 → 待打款；驳回 → 退回资产 + 已驳回。
     *
     * @throws \Throwable
     */
    public function audit(WithdrawOrder $order, int $adminId, bool $pass, string $remark = ''): WithdrawOrder
    {
        if ($order->status != WithdrawOrder::STATUS_PENDING_AUDIT) {
            throw new \Exception('该提现单当前状态不可审核');
        }
        $meta = ['audit_admin_id' => $adminId, 'audit_at' => time(), 'audit_remark' => $remark];

        if ($pass) {
            // 原子抢占：待审核 → 待打款
            if (!$this->transition($order, [WithdrawOrder::STATUS_PENDING_AUDIT], WithdrawOrder::STATUS_PENDING_PAYOUT, $meta)) {
                throw new \Exception('提现单状态已变更，操作取消');
            }
        } else {
            // 抢占(待审核→已驳回) + 退款 同一事务，抢到的才退、且退款失败整体回滚可重试
            if (!$this->claimAndRefund($order, [WithdrawOrder::STATUS_PENDING_AUDIT], WithdrawOrder::STATUS_REJECTED,
                $meta + ['finish_at' => time()], $remark ?: '审核驳回')) {
                throw new \Exception('提现单状态已变更，操作取消');
            }
        }
        return $order;
    }

    /**
     * 发起打款：反射网关 payout()，按返回结果流转状态。
     *
     * @throws \Throwable
     */
    /**
     * @param bool $offline 走「线下打款」兜底:强制用 ManualFund 代替订单原网关。
     *                      用于线上网关打不出去(风控/进件不全/接口故障)时,运营线下转账后回系统留痕。
     */
    public function payout(WithdrawOrder $order, bool $offline = false): WithdrawOrder
    {
        // 在途单的状态复查:用户申请时正常,随后被注销/拉黑。
        // 打款是【钱出系统】(进银行卡),不能因为「申请时是好人」就放行。
        // 这里只拦截并提示运营,不自动退款 —— 退不退、退到哪,是运营决策。
        if (!\app\services\UserService::isCommissionEligible((int)$order->user_id)) {
            throw new \Exception('该用户账号状态异常(已注销或被拉黑),无法打款。请先处理账号状态,或驳回本单退回资金');
        }

        if ($offline) {
            // 线下兜底只认 ManualFund:不开放"任意网关改派",否则等于给了后台绕过渠道配置的口子。
            // 且必须在「资金设置」里真的开启过 —— 未开启就打,进件字段/收款信息都没配,
            // 生成的打款单没人认领,后面对账也无据可依。
            $this->assertManualFundEnabled((int)$order->mall_id);
            $fundClass = \app\funds\ManualFund::class;
        } else {
            $fundClass = $order->fund_class;
        }
        if (!class_exists($fundClass) || !is_subclass_of($fundClass, \app\funds\BaseFund::class)) {
            throw new \Exception('打款网关无效：' . $fundClass);
        }

        // 【并发】原子抢占：待打款 → 打款中。抢不到说明别处已在处理，直接拒绝，杜绝重复打款。
        // 线下兜底额外允许从「打款失败(5)」直接发起 —— 这正是它存在的意义:线上打不出去时
        // 不必先「重试」绕回待打款再操作。资金全程冻结未退,不存在双花。
        $from = $offline
            ? [WithdrawOrder::STATUS_PENDING_PAYOUT, WithdrawOrder::STATUS_FAILED]
            : [WithdrawOrder::STATUS_PENDING_PAYOUT];
        $extra = ['payout_at' => time()];
        if ($offline) {
            // 【必须】把 fund_class 改写为实际执行打款的网关,与状态变更同一条原子 UPDATE。
            //
            // 不改的话:WithdrawPayoutQueryTask 每 3 分钟按 fund_class 白名单捞「打款中」的单去
            // orderQuery。线下单的 fund_class 若仍是快付这类异步网关,就会被捞走,拿一个网关侧
            // 根本不存在的单号去查 —— 网关返回 FAIL 时会经 PayoutResultDispatcher 置失败并退款,
            // 而钱运营已经线下转出去了,等于双付资损。
            //
            // 覆盖不丢信息:用户选的渠道由 channel_id / channel_name / destination 保留;
            // fund_class 的语义本就是「这单实际由哪个网关打款」(payout 与 cron 都据此反射)。
            $extra['fund_class'] = \app\funds\ManualFund::class;
        }
        if (!$this->transition($order, $from, WithdrawOrder::STATUS_PAYING, $extra)) {
            throw new \Exception('该提现单当前状态不可打款（可能已在打款中或状态已变更）');
        }

        /** @var \app\funds\BaseFund $fund */
        $fund = Yii::createObject($fundClass, [(int)$order->mall_id]);

        $snapshot = $this->decodeJson($order->snapshot);
        $account = $snapshot['account'] ?? [];

        // 到账方式。
        // 【仅线下兜底】才做"不支持就回落"的兼容:此时网关是被强制换成 ManualFund 的,
        // 订单原 destination 未必在它支持范围内(如 balance),不回落会直接抛"不支持的提现方式"。
        //
        // ⚠ 自动打款路径**不能**做这个回落:那里 destination 与网关是渠道配置时绑定校验过的,
        //   真出现不匹配就是渠道配错了(如 ManualFund 配了 destination=balance),必须让网关抛出来、
        //   落到「打款失败」让管理员去修配置 —— 悄悄换成一个"能跑"的到账方式等于掩盖错配,
        //   钱可能打去错误的目的地。
        $channel = (string)($order->destination ?: '');
        $supported = $fund->getSupportedChannels();
        if ($channel === '') {
            $channel = $supported[0] ?? '';
        } elseif ($offline && !in_array($channel, $supported, true)) {
            $channel = $supported[0] ?? '';
        }

        $payoutData = array_merge($account, [
            'channel'     => $channel,
            'user_id'     => (int)$order->user_id,
            'amount'      => $order->actual_amount,           // 打款实际到账金额
            'order_no'    => $order->order_no,
            'source_id'   => (int)$order->id,
            'source_type' => self::SOURCE_TYPE,
            'platform'    => (string)($snapshot['platform'] ?? ''), // 从快照传平台，网关不查业务模型
            'cash_model'  => null,
        ]);

        try {
            $result = $fund->payout($payoutData);
        } catch (\Throwable $e) {
            // 网关异常/拒绝(如 IP 白名单未配)→ 置「打款失败」态,资金冻结不自动退,
            // 由管理员在后台决定「重试」(修好配置)或「退回」。不逼用户重新申请。
            $this->markPayoutFailed($order, '打款异常：' . $e->getMessage());
            return $order;
        }

        $status = $result['status'] ?? '';
        $payoutNo = $result['payout_no'] ?? '';
        $message = $result['message'] ?? '';

        if ($status === 'success') {
            $this->markSuccess($order, $payoutNo);
        } elseif ($status === 'failed') {
            // 网关明确失败 → 置「打款失败」态,资金冻结不自动退,由管理员决定重试/退回。
            $this->markPayoutFailed($order, $message ?: '网关打款失败');
        } else {
            // 'doing' 或 契约外/未知/空 status:一律保守留 PAYING(打款中),交 cron 对账/人工处理。
            // 关键:未知 ≠ 失败——网关可能已受理甚至已出款,此时贸然 markFailed 退款会造成
            // "退款 + 出款" 双花资损。宁可卡在打款中等对账,也不抢先退款。
            WithdrawOrder::updateAll(['payout_no' => $payoutNo, 'updated_at' => time()],
                ['id' => $order->id, 'status' => WithdrawOrder::STATUS_PAYING]);
            $order->payout_no = $payoutNo;
            if ($status !== 'doing') {
                Yii::warning("[withdraw] payout 返回未知 status='{$status}' order={$order->order_no},"
                    . "保守留 PAYING 待对账(message: {$message})");
            }
        }
        return $order;
    }

    /** 标记到账成功（同步成功 / 异步回调 / 手动确认 共用，原子抢占） */
    public function markSuccess(WithdrawOrder $order, string $payoutNo = '', string $receipt = ''): WithdrawOrder
    {
        // 线下转账后的「确认到账」同样是钱已出系统的终态,与 payout 同口径复查。
        if (!\app\services\UserService::isCommissionEligible((int)$order->user_id)) {
            throw new \Exception('该用户账号状态异常(已注销或被拉黑),无法标记到账。请先处理账号状态');
        }

        $extra = ['finish_at' => time()];
        if ($payoutNo !== '') {
            $extra['payout_no'] = $payoutNo;
        }
        if ($receipt !== '') {
            $extra['receipt'] = $receipt;
        }
        if (!$this->transition($order, [WithdrawOrder::STATUS_PAYING, WithdrawOrder::STATUS_PENDING_PAYOUT],
            WithdrawOrder::STATUS_SUCCESS, $extra)) {
            throw new \Exception('该提现单当前状态不可标记到账');
        }
        // 手动打款的流水没有回调推它,在此收尾,否则永远停在「处理中」
        $this->settlePayoutLogs($order, \app\models\PayoutLog::STATUS_SUCCESS);
        return $order;
    }

    /** 单独上传/更新打款凭证（不改状态，已到账/打款中均可补传） */
    public function updateReceipt(WithdrawOrder $order, string $receipt): WithdrawOrder
    {
        WithdrawOrder::updateAll(['receipt' => $receipt, 'updated_at' => time()], ['id' => $order->id, 'is_delete' => 0]);
        $order->receipt = $receipt;
        return $order;
    }

    /** 标记打款失败 → 原路退回（同步失败 / 异步回调 / 手动 共用，原子抢占后退款，杜绝双退） */
    public function markFailed(WithdrawOrder $order, string $reason): WithdrawOrder
    {
        // 仅从「未退款的非终态」抢占到 已退回；抢到的才退款
        $from = [
            WithdrawOrder::STATUS_PENDING_AUDIT,
            WithdrawOrder::STATUS_PENDING_PAYOUT,
            WithdrawOrder::STATUS_PAYING,
            WithdrawOrder::STATUS_FAILED,
        ];
        if (!$this->claimAndRefund($order, $from, WithdrawOrder::STATUS_RETURNED,
            ['fail_reason' => mb_substr($reason, 0, 250), 'finish_at' => time()], $reason)) {
            throw new \Exception('该提现单已是终态或状态已变更，不可重复操作');
        }
        // 单子已退回退款,遗留的「处理中」流水一并作废,免得台账上挂着一笔查无下文的打款
        $this->settlePayoutLogs($order, \app\models\PayoutLog::STATUS_FAIL, $reason);
        return $order;
    }

    /**
     * 打款失败 → 置「打款失败(5)」态。⚠ 资金保持冻结、不自动退款!
     * 由管理员在后台决定「重试打款」(如修好 IP 白名单/配置后)或「退回」(手动退款)。
     * 这样可修复的失败(配置错/网络错)不会一失败就自动退回、逼用户重新申请。
     */
    public function markPayoutFailed(WithdrawOrder $order, string $reason): WithdrawOrder
    {
        if (!$this->transition($order,
            [WithdrawOrder::STATUS_PAYING, WithdrawOrder::STATUS_PENDING_PAYOUT],
            WithdrawOrder::STATUS_FAILED,
            ['fail_reason' => mb_substr($reason, 0, 250)])) {
            throw new \Exception('该提现单当前状态不可置为打款失败');
        }
        return $order;
    }

    /**
     * 「打款失败」后重试 → 退回「待打款(1)」,资金保持冻结不动。
     * 管理员修好问题(如配好快付 IP 白名单)后再点「打款」重新发起,无需用户重新申请。
     */
    public function retryPayout(WithdrawOrder $order): WithdrawOrder
    {
        if (!$this->transition($order,
            [WithdrawOrder::STATUS_FAILED],
            WithdrawOrder::STATUS_PENDING_PAYOUT,
            ['fail_reason' => ''])) {
            throw new \Exception('仅「打款失败」的单可重试');
        }
        return $order;
    }

    // ───────────────────── 内部 ─────────────────────

    /**
     * 原子状态流转：仅当当前 status ∈ $from 时置为 $to，返回是否抢占成功。
     * 用条件 UPDATE 的受影响行数做并发闸门，杜绝重复打款/重复退款。
     */
    /**
     * 「线下打款」是否可用 —— 即 ManualFund 在「资金设置」里已开启。
     *
     * 判断口径与其它网关一致:funds 配置里该类的 status==1。
     * 注意 ManualFund 构造函数在未配置时**不会抛异常**(它兜底成空配置),
     * 所以不能像别的网关那样靠"实例化失败"来判断,必须显式读 status。
     */
    public static function isOfflinePayoutEnabled(int $mallId): bool
    {
        $funds = \app\helpers\OptionHelper::get('funds', $mallId, []);
        if (!is_array($funds)) {
            return false;
        }
        return (int)($funds[\app\funds\ManualFund::class]['status'] ?? 0) === 1;
    }

    private function assertManualFundEnabled(int $mallId): void
    {
        if (!self::isOfflinePayoutEnabled($mallId)) {
            throw new \Exception('「线下打款」网关未开启：请先到「设置 → 资金渠道 → 手动打款」中启用后再操作');
        }
    }

    /**
     * 收尾本单遗留在「处理中」的打款流水(PayoutLog)。
     *
     * 背景:各线上网关会在自己的回调/查单里把流水推到 SUCCESS/FAIL,但**手动打款没有回调也没有
     * 查单** —— ManualFund::payout 落的是 PENDING,此后再没有任何路径推动它。结果就是提现单
     * 已经「已到账」,那条打款流水还永远停在「处理中」。渠道直接绑手动打款的场景早就如此,
     * 「线下打款」兜底会让它频繁得多。将来一旦有人拿 PayoutLog 做对账,这些钱会全部算不进已打款。
     *
     * 只推 PENDING 的:网关已经给出过结论的流水以网关为准,不覆盖(同时天然幂等)。
     * 台账写失败不影响业务状态 —— 钱的流转以 WithdrawOrder 为准,这里只记账,失败仅告警。
     *
     * @param string $status PayoutLog::STATUS_SUCCESS | STATUS_FAIL
     */
    private function settlePayoutLogs(WithdrawOrder $order, string $status, string $remark = ''): void
    {
        try {
            $set = [
                'status'       => $status,
                'completed_at' => time(),
                'updated_at'   => time(),
            ];
            if ($remark !== '') {
                $set['remark'] = mb_substr($remark, 0, 250);
            }
            \app\models\PayoutLog::updateAll($set, [
                'source_id'   => (int)$order->id,
                'source_type' => self::SOURCE_TYPE,
                'status'      => \app\models\PayoutLog::STATUS_PENDING,
                'is_delete'   => 0,
            ]);
        } catch (\Throwable $e) {
            Yii::warning("[withdraw] 打款流水收尾失败 order={$order->order_no}: " . $e->getMessage());
        }
    }

    private function transition(WithdrawOrder $order, array $from, int $to, array $extra = []): bool
    {
        $set = array_merge(['status' => $to, 'updated_at' => time()], $extra);
        $affected = WithdrawOrder::updateAll($set, [
            'id' => $order->id,
            'is_delete' => 0,
            'status' => $from,
        ]);
        if ($affected === 1) {
            foreach ($set as $k => $v) {
                $order->$k = $v;  // 同步内存对象
            }
            return true;
        }
        return false;
    }

    /**
     * 抢占终态 + 退款，单一事务原子完成。
     * - transition 用条件 UPDATE 受影响行数做并发闸门：抢到才退（杜绝并发双退）。
     * - 退款失败 → 整体回滚，状态不落终态，可重试（避免「已退回但没退钱」黑洞）。
     * @return bool 是否抢占成功（false=状态已变/被他人处理）
     * @throws \Throwable 退款过程异常（事务已回滚）
     */
    private function claimAndRefund(WithdrawOrder $order, array $from, int $finalStatus, array $extra, string $reason): bool
    {
        $txn = Yii::$app->db->beginTransaction();
        try {
            if (!$this->transition($order, $from, $finalStatus, $extra)) {
                $txn->rollBack();
                return false;
            }
            $this->doRefund($order, $reason);
            $txn->commit();
            return true;
        } catch (\Throwable $e) {
            $txn->rollBack();
            Yii::error("[withdraw] 退款失败已回滚 no={$order->order_no}：" . $e->getMessage(), 'withdraw');
            throw $e;
        }
    }

    /** 仅退款（状态已由 transition 原子抢占，保证只执行一次） */
    private function doRefund(WithdrawOrder $order, string $reason): void
    {
        $assetLabel = AssetService::label($order->asset_type);
        AssetService::refund(
            (int)$order->user_id,
            $order->asset_type,
            $order->amount,
            "{$assetLabel}提现退回 {$order->order_no}：{$reason}",
            "{$assetLabel}提现退回",
            // 幂等键用提现单号:一单最多退一次(状态抢占已保证,这里是账本层二道闸)
            'withdraw_refund_' . $order->order_no
        );
    }

    /** 当日限额校验（次数 + 累计金额），INT 时间戳比较 */
    private function checkDayLimits(int $userId, Channel $channel, string $amount): void
    {
        $needCount = (int)$channel->day_limit_count > 0;
        $needAmount = bccomp($channel->day_limit_amount, '0', 2) > 0;
        if (!$needCount && !$needAmount) {
            return;
        }
        $dayStart = (int)strtotime(date('Y-m-d 00:00:00'));
        $baseQuery = WithdrawOrder::find()
            ->where(['user_id' => $userId, 'channel_id' => $channel->id, 'is_delete' => 0])
            ->andWhere(['not in', 'status', [WithdrawOrder::STATUS_REJECTED, WithdrawOrder::STATUS_FAILED, WithdrawOrder::STATUS_RETURNED]])
            ->andWhere(['>=', 'created_at', $dayStart]);

        if ($needCount) {
            $cnt = (clone $baseQuery)->count();
            if ($cnt >= (int)$channel->day_limit_count) {
                throw new \Exception('已达当日提现次数上限');
            }
        }
        if ($needAmount) {
            $sum = (clone $baseQuery)->sum('amount') ?: '0';
            if (bccomp(bcadd((string)$sum, $amount, 2), $channel->day_limit_amount, 2) > 0) {
                throw new \Exception('超过当日累计提现限额');
            }
        }
    }

    /**
     * 全局当日提现次数限制（跨所有渠道统一计数,与渠道级 Channel.day_limit_count 并存且都生效）。
     * 上限来自后台全局配置 withdraw_setting.day_limit_count（0=不限,可配）。在 apply 的互斥锁内调用,串行化防并发突破。
     */
    private function checkGlobalDayTimes(int $userId, int $mallId): void
    {
        $setting = \app\plugins\withdraw\forms\admin\setting\SettingForm::get($mallId);
        $limit = (int)($setting['day_limit_count'] ?? 0);
        if ($limit <= 0) {
            return; // 0=不限
        }
        $dayStart = (int)strtotime(date('Y-m-d 00:00:00'));
        $cnt = (int)WithdrawOrder::find()
            ->where(['user_id' => $userId, 'mall_id' => $mallId, 'is_delete' => 0])
            ->andWhere(['not in', 'status', [WithdrawOrder::STATUS_REJECTED, WithdrawOrder::STATUS_FAILED, WithdrawOrder::STATUS_RETURNED]])
            ->andWhere(['>=', 'created_at', $dayStart])
            ->count();
        if ($cnt >= $limit) {
            // 拦截文案后台可配({count}=上限次数)。配置未含 {count} 且上限>1 时,回退带次数的默认文案,避免"一次"与实际不符。
            $tip = trim((string)($setting['day_limit_tip'] ?? ''));
            if ($tip === '') {
                $tip = \app\plugins\withdraw\forms\admin\setting\SettingForm::DEFAULT_DAY_LIMIT_TIP;
            }
            if ($limit > 1) {
                $tip = (strpos($tip, '{count}') !== false)
                    ? str_replace('{count}', (string)$limit, $tip)
                    : "今日提现次数已达上限({$limit}次),请明天再来提现哦";
            } else {
                $tip = str_replace('{count}', (string)$limit, $tip);
            }
            throw new \Exception($tip);
        }
    }

    /**
     * 解析收款账户：[展示列表 payout_display, 原始账户 rawAccount]
     * 按网关能力 best-effort；不需要账户的网关（余额/手动）返回空。
     */
    private function resolveAccount(int $mallId, Channel $channel, User $user): array
    {
        $fundClass = $channel->fund_class;
        if (!class_exists($fundClass)) {
            return [[], []];
        }
        try {
            /** @var \app\funds\BaseFund $fund */
            $fund = Yii::createObject($fundClass, [$mallId]);

            // 【安全】打款账户只来自已验证的 UserSettlementInfo，绝不接受前端传入，
            // 否则用户可把钱打到任意账户（越权打款）。
            $raw = [];
            if (method_exists($fund, 'getUserAccountInfo')) {
                $settlement = UserSettlementInfo::findOne(['user_id' => $user->id, 'mall_id' => $mallId, 'is_delete' => 0]);
                if ($settlement) {
                    $fromSettlement = $fund->getUserAccountInfo($settlement);
                    if (is_array($fromSettlement)) {
                        $raw = $fromSettlement;
                    }
                }
            }

            $display = [];
            if (method_exists($fund, 'getPayoutDisplay')) {
                $display = $fund->getPayoutDisplay($raw) ?: [];
            }
            return [$display, $raw];
        } catch (\Throwable $e) {
            Yii::warning('[withdraw] 解析收款账户失败：' . $e->getMessage());
            return [[], []];
        }
    }

    private function decodeJson($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $d = json_decode($raw, true);
        return is_array($d) ? $d : [];
    }
}
