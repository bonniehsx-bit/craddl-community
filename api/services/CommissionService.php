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

namespace app\services;

use app\helpers\BalanceLogHelper;
use app\helpers\IntegralLogHelper;
use app\helpers\PriceLogHelper;
use app\models\CommissionLog;
use app\models\Order;
use app\models\OrderDetail;
use app\models\User;
use Yii;
use yii\base\Component;
use yii\helpers\Json;

/**
 * 分佣中台服务类 (旗舰重构版)
 * 核心职能：
 * 1. 记录分佣权利 (Log)
 * 2. 路由结算逻辑 (Settle) -> 自动根据配置发钱给 Price/Balance/Integral/ExternalSplit
 * 3. 售后作废 (Invalid)
 */
class CommissionService extends Component
{
    // --- 结算目标常量 ---
    const PAYOUT_TO_COMMISSION = 'price';       // 结算到: 佣金/收益 兼容处理
    const PAYOUT_TO_PRICE = 'price';          // 结算到: 佣金/收益 (需提现)
    const PAYOUT_TO_BALANCE = 'balance';      // 结算到: 余额 (可直接消费)
    const PAYOUT_TO_INTEGRAL = 'integral';    // 结算到: 积分
    const PAYOUT_TO_MCH_FUNDS = 'mch.funds';  // 结算到: 商家货款账户 (独立于佣金,提现规则不同)
    const PAYOUT_TO_EXTERNAL_SPLIT = 'split'; // 结算到: 第三方外部分账

    // --- 发放前的退款判定(payoutRefundState)---
    const PAYOUT_OK = 'ok';                 // 照常发
    const PAYOUT_REFUNDED = 'refunded';     // 这件已退款 / 订单已关闭 → 作废
    const PAYOUT_PROCESSING = 'processing'; // 这件还在售后处理中 → 先不发、也别作废,处理完再看

    /**
     * 分润发放前的退款判定,各插件结算 / 到期发放 / 手动结算 / 领取共用,按件判断:
     *  · 订单已关闭(整单退完 / 取消)→ 整单作废;
     *  · 这件作废了(商家确认打款那一刻起,不看钱到没到)或退了款 → 作废这件;
     *    这件还在售后处理中(申请了、商家还没确认打款)→ PROCESSING,由调用方决定:订单结算那一刻不会出现(结算会等售后),
     *    照旧作废;到期发放 / 领取时先跳过、下次再看,售后被拒或撤销后照常发;
     *  · ⛔ 不看订单上的售后标记:部分退款后它是「售后已完成」,拿它判断会把没退的那几件也一起作废
     *    (分销 / 区域 / 经销 / 链动 / 商家推荐奖 / 招商原来都是这么连坐的,用户定:部分退款只作废退掉的那件,其余照常结算);
     *  · 没挂在具体商品上的整单流水($detailId=0)分不清是哪件,订单有过售后仍按老口径作废。
     *
     * @param Order|null $order 分润所属订单(调用方现查的最新数据;没有订单的流水传 null)
     */
    public static function payoutRefundState(?Order $order, int $detailId): string
    {
        if ($detailId > 0) {
            $detail = OrderDetail::findOne($detailId);
            if (!$detail || (int)$detail->status === OrderDetail::STATUS_INVALID) {
                return self::PAYOUT_REFUNDED;
            }
            $rs = (int)$detail->refund_status;
            if ($rs === OrderDetail::REFUND_STATUS_APPLIED || $rs === OrderDetail::REFUND_STATUS_PROCESSING) {
                return self::PAYOUT_PROCESSING;
            }
            if ($rs !== OrderDetail::REFUND_STATUS_NO_REFUND) {
                return self::PAYOUT_REFUNDED;
            }
        }
        if ($order && (int)$order->status === Order::STATUS_CLOSED) {
            return self::PAYOUT_REFUNDED;
        }
        if ($order && $detailId <= 0 && (int)$order->refund_status > 0) {
            return self::PAYOUT_REFUNDED;
        }
        return self::PAYOUT_OK;
    }

    /**
     * [第一步] 创建分佣记录 (待结算)
     * * @param int $userId 获佣用户ID
     * @param float $amount 数额
     * @param string $type 业务类型
     * @param string $payoutType 结算路由
     * @param array $params [mall_id, order_id, order_detail_id, buy_user_id, remark, extra_data]
     *                       mall_id 可选:显式传入用于 job/cron 等 Yii::$app->mall 未注入的上下文(如 xwy TradeService),
     *                       不传则回退当前商城,兼容所有现有调用。
     * @return CommissionLog
     * @throws \Exception
     */
    public function log(int $userId, float $amount, string $type, string $payoutType = self::PAYOUT_TO_PRICE, array $params = [])
    {
        if ($amount <= 0) {
            throw new \Exception('分佣数额必须大于0');
        }

        $log = new CommissionLog();
        // mall_id:优先取显式传入(job/cron 无 mall 上下文时防串场),否则回退当前商城
        $log->mall_id = $params['mall_id'] ?? (Yii::$app->mall->id ?? 0);
        $log->user_id = $userId;
        $log->type = $type;
        $log->commission_amount = $amount;
        $log->status = CommissionLog::STATUS_PENDING;

        $log->order_id = $params['order_id'] ?? 0;
        $log->order_detail_id = $params['order_detail_id'] ?? 0;
        $log->buy_user_id = $params['buy_user_id'] ?? 0;
        $log->calculation_price = $params['calculation_price'] ?? null;
        $log->commission_rate = $params['commission_rate'] ?? null;
        // remark 是 varchar(255) 且模型有 max=255 校验,超长会让 save() 失败 →
        // log() 抛异常 → **整笔分润发不出去**。而 remark 只是展示字段:
        // 插件常把商品名拼进去(goods.name 本身就是 varchar(255)),一个长商品名就能撑爆。
        // 丢掉尾巴远好过丢掉整笔佣金,所以这里主动截断。
        //
        // ⛔ 不要对 type 做同样处理:那是代码常量、是按类型过滤与退款回滚的锚点,
        //    截断会让回滚匹配不到、钱永远追不回 —— 那种情况就该响亮地失败。
        $remark = (string) ($params['remark'] ?? '');
        $log->remark = mb_strlen($remark) > 255 ? mb_substr($remark, 0, 252) . '...' : $remark;
        $log->level = $params['level'] ?? 0;

        // 【核心协议】记录结算目标及分账所需的私有协议
        $extraData = $params['extra_data'] ?? [];
        $extraData['payout_type'] = $payoutType;
        $log->extra_data = Json::encode($extraData);

        $log->created_at = time();
        $log->updated_at = time();

        if (!$log->save()) {
            $error = (new \app\models\ModelError())->getErrorMsg($log);
            Yii::error("创建分佣记录失败: {$error}", 'commission');
            throw new \Exception("创建分佣记录失败: {$error}");
        }

        return $log;
    }

    /**
     * [第二步] 结算 (路由分发)
     * 根据 log 中的 payout_type 自动分发执行
     *
     * @param int|CommissionLog $log
     * @return bool
     * @throws \Throwable
     */
    public function settle($log)
    {
        if (is_numeric($log)) {
            $log = CommissionLog::findOne($log);
        }
        if (!$log || $log->is_delete) {
            throw new \Exception('分佣记录不存在');
        }

        if ($log->status == CommissionLog::STATUS_SETTLED) {
            return true;
        }
        if ($log->status == CommissionLog::STATUS_INVALID) {
            throw new \Exception('该记录已失效，无法结算');
        }

        // 收款人已注销:墓碑节点不参与分润。
        // 语义与 distribution 的 is_disabled 完全一致 —— 该层丢弃,层级不前移,
        // 上级不会因为中间节点消失而"升级"多拿(那会改变分润归属,且注销 360 天内可恢复,无法追回)。
        // 判定以「结算时刻」为准:计提时正常、结算前才注销的,这里一并拦下。
        //
        // 只拦「存在且已注销」。收款人整行不存在时保持原有行为 —— 历史上有这类记录,
        // 在此 invalid 会改变既有语义,不在本次范围内。
        $receiver = User::find()
            ->select(['id', 'is_delete', 'status'])
            ->where(['id' => $log->user_id])
            ->asArray()
            ->one();
        if ($receiver
            && ((int)$receiver['is_delete'] === 1 || (int)$receiver['status'] === User::STATUS_DELETE)) {
            Yii::warning("[CommissionService] 收款人 #{$log->user_id} 已注销,作废佣金 Log#{$log->id}", 'commission');
            // remark 是 varchar(255) 且有 max=255 校验,而 invalid() 里的 save() 不检查返回值:
            // 追加后超长会导致 status 写不进库,佣金永远卡在 PENDING 被反复重试。必须截断。
            $note = '[收款人账号已注销,佣金作废]';
            $merged = trim(($log->remark ?: '') . ' ' . $note);
            $log->remark = mb_strlen($merged) <= 255 ? $merged : $note;
            $this->invalid($log);
            return false;
        }

        $extraData = Json::decode($log->extra_data, true) ?: [];
        $payoutType = $extraData['payout_type'] ?? self::PAYOUT_TO_PRICE;
        $this->requirePayoutHandler($payoutType, $extraData);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // [并发保护] 用条件 UPDATE 做原子状态切换：PENDING → SETTLED
            // 只有一个进程能成功把状态从 PENDING 改到 SETTLED，其余 affected=0 直接返回
            // 防止两个进程同时看到 PENDING 都走到 PayoutHelper → 同笔佣金转账两次
            $affected = CommissionLog::updateAll(
                ['status' => CommissionLog::STATUS_SETTLED, 'settled_at' => time(), 'updated_at' => time()],
                ['id' => $log->id, 'status' => CommissionLog::STATUS_PENDING, 'is_delete' => 0]
            );
            if ($affected === 0) {
                // 其他进程已经抢先结算，幂等返回
                $transaction->commit();
                Yii::warning("[CommissionService] Log#{$log->id} 已被其他进程抢先结算，本次跳过");
                return true;
            }
            // 把内存对象同步成最新状态，避免下文 $log->save() 回写 PENDING
            $log->status = CommissionLog::STATUS_SETTLED;

            // 1. 路由分发逻辑处理
            $title = $log->getTypeText();
            $remark = $log->remark ?: "{$title}结算";

            // 插件接管的结算目标(如收银台第三方分账 'split')优先:核心委托给注册 handler,自身不认识其落库/调用实现。
            if (PayoutHandlerRegistry::has($payoutType)) {
                $handler = PayoutHandlerRegistry::handler($payoutType);
                if ($handler) {
                    $handler->handle($log, $extraData);
                } else {
                    // 注册了但实例化/契约校验失败:属配置错误，抛错回滚状态，避免记录结算却没有落账
                    throw new \RuntimeException('结算目标处理器不可用');
                }
            } else {
                switch ($payoutType) {
                    case self::PAYOUT_TO_BALANCE:
                        BalanceLogHelper::add($log->user_id, $log->commission_amount, $remark, 0, 0, $title);
                        break;

                    case self::PAYOUT_TO_INTEGRAL:
                        IntegralLogHelper::add($log->user_id, $log->commission_amount, $remark, $title, "settle_commission_{$log->id}");
                        break;

                    case self::PAYOUT_TO_PRICE:
                        PriceLogHelper::add($log->user_id, $log->commission_amount, $remark, $log->order_detail_id, $title);
                        break;

                    default:
                        // 扩展钱包:payout 目标即钱包 key(mch.funds / xwy.channel / 任意插件声明的钱包),经核心 WalletService 入账;
                        // 不是已注册钱包则兜底进佣金(price),与历史一致,避免脏值生成野钱包。
                        //
                        // 入账用 existsIn(带 mall)而非 exists:钱包必须在本商城【已授权且已启用】才允许打进去。
                        // 否则会出现「钱进得去、出不来」—— 提现来源按本商城过滤,那笔钱在提现列表里根本看不到,
                        // 用户查不到也提不出来。打不进去时兜底进佣金,和遇到未知 key 的处理一致。
                        if (\app\services\WalletRegistry::existsIn($payoutType, (int)$log->mall_id)) {
                            \app\services\WalletService::add($log->user_id, $payoutType, $log->commission_amount, $remark, $title, "settle_commission_{$log->id}");
                        } else {
                            PriceLogHelper::add($log->user_id, $log->commission_amount, $remark, $log->order_detail_id, $title);
                        }
                        break;
                }
            }

            // 2. 补写其他已同步的字段（状态此前已通过条件 UPDATE 设置）
            $log->status = CommissionLog::STATUS_SETTLED;
            $log->settled_at = time();
            $log->updated_at = time();
            if (!$log->save()) {
                throw new \Exception('更新结算状态失败');
            }

            $transaction->commit();
            Yii::warning("结算完成[{$payoutType}] ID:{$log->id} User:{$log->user_id}", 'commission');
            return true;

        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error("结算失败 ID:{$log->id} Error:{$e->getMessage()}", 'commission');
            throw $e;
        }
    }

    /**
     * [第三步] 作废
     */
    public function invalid($log)
    {
        if (is_numeric($log)) {
            $log = CommissionLog::findOne($log);
        }
        if (!$log) {
            throw new \Exception('记录不存在');
        }

        if ($log->status != CommissionLog::STATUS_PENDING) {
            throw new \Exception('只有待结算的记录可以作废');
        }

        // ⛔ 必须按「当前仍是待结算」做条件更新,不能 $log->save():
        //   调用方传进来的 $log 多半是早先查出来的,结算(settle / T+N 到期 / 别的链路)可能已在中间提交。
        //   原来的 save() 只按 id 写,会把「已结算」直接覆盖成「作废」—— 钱已经发出去,流水却记成作废,
        //   之后 revert() 看到 INVALID 幂等跳过,这笔钱永远追不回(2026-09 核查,与 revert 同一类问题)。
        // 调用方改过的其它字段(如 settle 注销分支追加的 remark)照旧一起写入。
        $attrs = $log->getDirtyAttributes();
        unset($attrs['status']);
        $attrs['updated_at'] = time();
        $attrs['status'] = CommissionLog::STATUS_INVALID;
        $affected = CommissionLog::updateAll($attrs, ['id' => $log->id, 'status' => CommissionLog::STATUS_PENDING]);
        if ($affected === 0) {
            // 没抢到:当前读(加锁)看它现在是什么。已被别处作废 → 幂等成功;已被结算 → 明确失败,交给调用方走 revert
            $cur = CommissionLog::find()->select(['status'])->where(['id' => $log->id])->forUpdate()->scalar();
            if ((int)$cur === CommissionLog::STATUS_INVALID) {
                $log->status = CommissionLog::STATUS_INVALID;
                return true;
            }
            throw new \Exception("流水 #{$log->id} 已被结算(状态 {$cur}),不能作废;要追回已发的钱请走 revert()");
        }
        foreach ($attrs as $k => $v) {
            $log->setAttribute($k, $v);
            $log->setOldAttribute($k, $v);
        }

        return true;
    }

    /**
     * [统一回滚] settle() 的逆操作 —— 订单分润「大一统」的中台落点。
     *
     * 与 invalid() 的区别:invalid() 只能作废 PENDING;revert() 同时覆盖「未结算」和「已结算」:
     *   · PENDING  → 直接置无效(从未发放,无需追钱)
     *   · SETTLED  → 按当初的发放口径(payout_type)反向把钱从对应钱包追回,再置无效
     *
     * 各分润插件监听 order.commission.revert 时,只需「查出本插件该订单(/该明细)的 log → 逐条 revert()」即可,
     * 不必各自手写 PriceLogHelper::sub、也不会扣错钱包(price/balance/integral/mch.funds 自动路由)。
     *
     * 幂等 + 并发安全:已 INVALID/已删除直接返回;用条件 UPDATE 抢占状态,只有一个进程真正执行追回。
     * 外部分账(EXTERNAL_SPLIT)已出账无法本地冲账,记录告警留人工对账,不阻断主流程。
     *
     * @param int|CommissionLog $log
     * @return bool
     * @throws \Throwable
     */
    public function revert($log)
    {
        $logId = is_numeric($log) ? (int)$log : (int)$log->id;

        // 先普通读确认记录存在,不存在直接返回,不去加锁:
        // 对不存在的主键 FOR UPDATE 会锁住那段间隙 —— id 比最大值还大时,锁住的正是下一条新流水的位置,
        // 回滚若嵌在外层事务里,期间所有订单写分润流水都会被挡住(本地实测挡了 2 秒)。
        // 分润流水只软删不物理删,这里查到了,下面加锁时一定还在,锁的是这一行本身
        // 调用方已加载的真实记录可直接进入锁定读；不能再被外层旧快照误判为不存在。
        $loadedRecord = $log instanceof CommissionLog && !$log->isNewRecord;
        if (!$loadedRecord && !CommissionLog::find()->where(['id' => $logId])->exists()) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // 取最新状态并锁行。⛔ 必须 FOR UPDATE,不能普通读:
            //   普通读是事务快照,settle() 正好在「读」和下面的「条件更新」之间提交时,这里读到的还是 PENDING
            //   → $wasSettled=false;条件更新却接受 SETTLED、照样置无效 → 流水作废、已发的钱不追回(2026-09 本地复现)。
            //   加锁读拿到的是当前值;settle 事务没提交时这里会等它提交,读到 SETTLED 再按已结算追回。
            //   与 settle() 同序(先锁流水、再动钱包),单条流水的 revert 与 settle 之间不会互相死锁;
            //   调用方若在一个事务里连着处理多条流水 / 多个钱包(如 brand/xwy 回滚任务),死锁仍可能发生,那是既有问题。
            $fresh = CommissionLog::find()
                ->where(['id' => $logId, 'is_delete' => 0])
                ->forUpdate()
                ->one();
            if (!$fresh) {
                $transaction->commit();
                return false; // 记录不存在
            }
            if ($fresh->status == CommissionLog::STATUS_INVALID) {
                $transaction->commit();
                return true; // 已作废,幂等返回
            }

            // 只有待结算 / 已结算能回滚;将来若加了别的状态,不在这里顺手置无效
            if (!in_array((int)$fresh->status, [CommissionLog::STATUS_PENDING, CommissionLog::STATUS_SETTLED], true)) {
                $transaction->commit();
                Yii::warning("[CommissionService] Log#{$logId} 状态 {$fresh->status} 不可回滚,跳过", 'commission');
                return true;
            }
            $wasSettled = ((int)$fresh->status === CommissionLog::STATUS_SETTLED);

            // [并发护栏] 原子置无效:条件只认上面读到的那个状态,与「是否追回」的判断严格对应;
            // 其余 affected=0 幂等返回,杜绝重复追回
            $affected = CommissionLog::updateAll(
                ['status' => CommissionLog::STATUS_INVALID, 'updated_at' => time()],
                ['id' => $logId, 'is_delete' => 0, 'status' => (int)$fresh->status]
            );
            if ($affected === 0) {
                $transaction->commit();
                Yii::warning("[CommissionService] Log#{$logId} 已被处理,revert 跳过(幂等)", 'commission');
                return true;
            }

            // 只有「已结算」才需要把发出去的钱按原口径追回(PENDING 从未发放)
            if ($wasSettled) {
                $extraData = Json::decode($fresh->extra_data, true) ?: [];
                $payoutType = $extraData['payout_type'] ?? self::PAYOUT_TO_PRICE;
                $title = $fresh->getTypeText() ?: $fresh->type;
                $remark = '退款追回:' . ($fresh->remark ?: $title);
                $amount = $fresh->commission_amount;

                $this->requirePayoutHandler($payoutType, $extraData);
                $handler = PayoutHandlerRegistry::has($payoutType) ? PayoutHandlerRegistry::handler($payoutType) : null;
                if ($handler instanceof \app\services\contracts\ReversiblePayoutHandlerInterface) {
                    $handler->revert($fresh, $extraData);
                } elseif (!empty($extraData['payout_handler_required'])) {
                    throw new \RuntimeException('结算目标不支持退款，已回滚本次状态变更');
                } else switch ($payoutType) {
                    case self::PAYOUT_TO_BALANCE:
                        BalanceLogHelper::sub($fresh->user_id, $amount, $remark, 0, 0, $title, true);
                        break;
                    case self::PAYOUT_TO_INTEGRAL:
                        IntegralLogHelper::sub($fresh->user_id, $amount, $remark, $title, "revert_commission_{$fresh->id}", true);
                        break;
                    case self::PAYOUT_TO_EXTERNAL_SPLIT:
                        // 第三方分账已出账,无法本地反向冲账:记录告警留人工/对账处理,不阻断回滚主流程
                        Yii::error("[CommissionService] 外部分账已结算无法自动追回,需人工冲账 Log:{$fresh->id} Amount:{$amount}", 'commission');
                        break;
                    case self::PAYOUT_TO_PRICE:
                        PriceLogHelper::sub($fresh->user_id, $amount, $remark, $fresh->order_detail_id, $title, true);
                        break;
                    default:
                        // 扩展钱包冲账:force=true 允许扣成负数(钱可能已提走);非钱包目标兜底进佣金。
                        // ⚠ 这里**故意**用宽松的 exists 而不是入账那侧的 existsIn:钱已经打进去了,
                        // 哪怕之后插件取消授权、钱包被后台关掉,也必须还能扣回来。
                        // 收紧成 existsIn 会让这些单子冲账时走进 else 分支去扣佣金,变成扣错账户。
                        if (\app\services\WalletRegistry::exists($payoutType)) {
                            \app\services\WalletService::sub($fresh->user_id, $payoutType, $amount, $remark, $title, "revert_commission_{$fresh->id}", true);
                        } else {
                            PriceLogHelper::sub($fresh->user_id, $amount, $remark, $fresh->order_detail_id, $title, true);
                        }
                        break;
                }
            }

            $transaction->commit();
            Yii::warning("[CommissionService] revert 完成 ID:{$logId} wasSettled:" . ($wasSettled ? 1 : 0) . " User:{$fresh->user_id}", 'commission');
            return true;

        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error("[CommissionService] revert 失败 ID:{$logId} Error:{$e->getMessage()}", 'commission');
            throw $e;
        }
    }
    /** 显式要求处理器的记录必须失败关闭，禁止降级进收入账户。 */
    private function requirePayoutHandler(string $payoutType, array $extraData): void
    {
        if (!empty($extraData['payout_handler_required']) && !PayoutHandlerRegistry::handler($payoutType)) {
            throw new \RuntimeException('结算目标处理器不可用，请恢复对应插件后重试');
        }
    }

}
