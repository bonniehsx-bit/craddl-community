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

namespace app\helpers;

use app\models\IntegralLog;
use app\models\User;
use app\helpers\CacheHelper;
use yii\base\Exception;

/**
 * 用户积分(User.integral)的写入口,与 BalanceLogHelper / PriceLogHelper 同一套口径。
 *
 * ⚠️ 积分比余额/佣金多一个**有意为之**的第二写入口:`IntegralExpireService`(过期回收)。
 *    它自己持 user 行锁(与本类同为 user→lot 序,防 AB-BA 死锁)、自带幂等键与审计支出行,
 *    刻意绕开 sub() 是为了避免二次 FIFO 扣减。除它之外没有第三处 —— 直接 `$user->integral = N`
 *    是零流水的、对不上账,新增加减一律走本类。
 *
 * ══════════ 职责边界 ══════════
 * 本类只保证「分和流水一致」;「这个用户该不该被操作」是业务判断,归调用方,
 * 所以**不按 is_delete 过滤**。早先只有 sub 的强制扣减分支单独带了 is_delete = 0
 * (add 与正常扣减都不带),方向正好是反的:强制扣减正是 `CommissionService::revert`
 * 追回积分佣金的落点,「拿了积分再注销」反而追不回 = 白送;报错还是「用户积分不足」,
 * 分明明还在,排查会一路查错方向。
 *
 * ══════════ 幂等靠唯一索引,不靠预先查一遍 ══════════
 * 传了 idempotencyKey 就只有两道:①事务外快速查一次(纯优化,绝大多数重复请求在这里就返回了);
 * ②`uk_idempotency_key` 唯一索引。**没有第三道**。
 *
 * 早先事务内还有一句 `where(idempotency_key)->forUpdate()`,注释说它是「最稳妥的行级排他锁」——
 * 恰恰相反:那一行**还不存在**,REPEATABLE-READ 下拿到的是 gap 锁,而 gap 锁是共享的,
 * 多个事务能同时持有,一点互斥作用都没有。它唯一的效果是制造锁序颠倒:
 *     T1 持 user 行 X 锁 → 等 uk_idempotency_key 的 insert intention
 *     T2 持 uk_idempotency_key 的 gap 锁 → 等 user 行 X 锁
 * 实测 20 进程同 key 并发,19 个拿到 1213 Deadlock(InnoDB 死锁报告即上述两段)。
 * 去掉之后所有事务都是「先 user 行、后插入」的统一顺序,不成环。
 *
 * 另一半是重复键**不会**让 save() 返回 false —— 模型上没有 unique 验证器,
 * MySQL 直接抛 yii\db\IntegrityException,所以老代码里 `if (!$log->save())` 那段
 * 幂等兜底是**永远进不去的死代码**。现在显式 catch,并回查确认确实是幂等键冲突才当命中。
 *
 * ⚠️ 调用方要自己判断能不能对该用户操作,以及 user_id 是不是本商城的。
 */
class IntegralLogHelper
{
    /**
     * [最终优化版] 安全地增加用户积分 (原子更新 + 幂等性支持 + 缓存清理)
     * @param int $user_id
     * @param float|string $integral
     * @param string|null $content
     * @param string|null $title
     * @param string|null $idempotencyKey [幂等键] 可选，防止重复加分
     * @return bool|int 返回日志ID或true
     * @throws \Throwable
     */
    /**
     * @param string|null $bizType     业务类型(order_base/sign/exchange...);由 IntegralService 网关透传,历史调用可为 null
     * @param array       $eventParams 附加参数,随 IntegralEvent::CHANGED 事件透传(order_no/mch_id 等)
     */
    public static function add($user_id, $integral, $content = null, $title = null, $idempotencyKey = null, $bizType = null, $eventParams = [])
    {
        if (!is_numeric($integral) || $integral <= 0) {
            throw new Exception('积分必须是大于0的数字！');
        }

        // 1. [第一道防线] 事务外快速幂等检查 (性能优化)
        if (!empty($idempotencyKey)) {
            $existingLog = IntegralLog::findOne(['idempotency_key' => $idempotencyKey]);
            if ($existingLog) {
                // \Yii::warning("幂等操作命中(Cache/Check)：key '{$idempotencyKey}' 已被处理。");
                return $existingLog->id;
            }
        }

        $transaction = \Yii::$app->db->beginTransaction();
        $rolledBack = false;   // 撞唯一索引那支会先回滚,外层 catch 据此不再回滚第二次(见 dupKeyExistingId)
        try {
            // 2. [核心] SQL 原子更新 (同时增加 integral 和 total_integral)
            // SQL: UPDATE user SET integral = integral + :val, total_integral = total_integral + :val WHERE id = :id
            $updateCount = User::updateAllCounters([
                'integral' => $integral,
                'total_integral' => $integral
            ], ['id' => $user_id]);

            if ($updateCount <= 0) {
                throw new Exception('用户不存在或更新失败');
            }

            // 4. 获取最新积分状态 (用于准确记录日志)
            $user = User::find()
                ->select(['id', 'mall_id', 'integral'])
                ->where(['id' => $user_id])
                ->asArray()
                ->one();

            if (!$user) {
                throw new Exception('获取用户信息失败');
            }

            // 5. 记录日志
            $log = new IntegralLog();
            $log->mall_id = $user['mall_id'];
            $log->user_id = $user_id;
            $log->integral = $integral;
            $log->type = IntegralLog::TYPE_ADD; // 1=收入
            $log->content = $content;
            $log->current = $user['integral']; // 记录原子更新后的最新值
            $log->date_at = date('Y-m-d');
            $log->title = $title ?: '收入';
            $log->biz_type = $bizType; // 业务类型(网关透传;历史调用为 null)
            // 有效期开启时:本收益行即一个批次,remaining=本批剩余(供 FIFO 消费/过期回收);未开启为 null
            $log->remaining = self::expireEnabled($user['mall_id']) ? $integral : null;

            if (!empty($idempotencyKey)) {
                $log->idempotency_key = $idempotencyKey;
            }

            // 5. 保存日志。唯一索引是幂等的**唯一**真实防线 —— 见类注释。
            try {
                if (!$log->save()) {
                    throw new Exception('记录积分日志失败: ' . current($log->getFirstErrors()));
                }
            } catch (\yii\db\IntegrityException $e) {
                $rolledBack = true;   // 先置位:rollBack() 自己抛异常时层级也已经退了,外层不能再退
                $transaction->rollBack();
                $existId = self::dupKeyExistingId($idempotencyKey);
                if (!$existId) {
                    throw $e;   // 不是幂等键冲突,别把真实的完整性错误掩盖成"已处理"
                }
                \Yii::warning("幂等操作命中(DB Unique):key '{$idempotencyKey}' 已被处理。");
                return $existId;
            }

            $transaction->commit();

            // 7. 清理缓存
            try {
                CacheHelper::remove('user_data:' . $user_id);
            } catch (\Throwable $e) {
                \Yii::warning('清理用户缓存失败: ' . $e->getMessage());
            }

            // 8. 派发统一「积分变动」事件(chokepoint,覆盖全部加分;listener 异常不影响主流程)
            self::dispatchChanged($user['mall_id'], $user_id, $integral, 'add', $bizType, $log->id, $user['integral'], $eventParams);

            return $log->id;
        } catch (\Throwable $e) {
            if (!$rolledBack) {
                $transaction->rollBack();
            }
            \Yii::error('增加用户积分失败: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 撞了唯一索引之后,回查这个幂等键是不是已经被别的请求处理过;是就返回那条流水的 id。
     * 调用前必须已经回滚本次的写入(扣/加的分要撤掉),并且调用方要置 $rolledBack = true。
     *
     * ① 回查必须是**锁定读**。普通读走的是 REPEATABLE-READ 快照,快照可能早于赢家提交,
     *    看不见那条刚落的行,就会误判成「不是幂等冲突」抛出裸 1062。
     *    早先的做法是「先回滚再普通读」:本 helper 自己开的事务回滚后是自动提交,读得到最新 ——
     *    (当时实测 20 进程同 key,不先回滚有 14 个拿到裸 1062)。但被套在调用方事务里时,
     *    回滚只退到保存点,事务还在、快照还是调用方更早建立的那个,照样看不见。锁定读总是读最新提交的版本。
     *    用**共享锁**不用 FOR UPDATE:撞键时 INSERT 已经给这条索引记录加了共享锁(回滚到保存点不释放),
     *    再要排他锁就是共享→排他升级,两个请求同时这样会互等成死锁;这里只为读到最新版本,共享锁就够。
     *    撞键说明这行已存在,锁的是记录本身而不是间隙,不会有类注释里那种间隙锁死锁。
     * ② 外层 catch 不能再回滚一次。嵌套事务 = 保存点,共用同一个 Transaction 对象,
     *    每次 rollBack() 退一层,第二次退掉的就是**调用方**那层:
     *    调用方的事务整个被回滚,它若吞了异常继续写,前半截丢了、后半截自动提交落库,
     *    最后 commit 报「transaction was inactive」(2026-09-23 实测)。
     *
     * @return int|null 已存在的流水 id;不是幂等键冲突(或没传幂等键)时返回 null
     */
    private static function dupKeyExistingId($idempotencyKey)
    {
        if (empty($idempotencyKey)) {
            return null;
        }
        $id = IntegralLog::find()->select('id')->where(['idempotency_key' => $idempotencyKey])->sharedLock()->scalar();
        return $id ? (int)$id : null;
    }

    /**
     * 该商城是否开启积分有效期(开启才维护批次 remaining + FIFO;默认关=行为零变更)
     */
    private static function expireEnabled($mallId): bool
    {
        return !empty(\app\helpers\IntegralSettingHelper::get((int) $mallId)['expire']['enabled']);
    }

    /**
     * FIFO 批次扣减:从该用户最老的、未用尽的收益行(type=1, remaining>0)依次扣减 $amount。
     * 必须在 sub() 的事务内、原子扣完 user.integral 之后调用。
     * 锁顺序统一 created_at ASC, id ASC 防死锁。批次不足的余量(存量未 backfill / 扣负场景)容忍不记负。
     */
    private static function deductFifo($userId, $amount)
    {
        $left = (float) $amount;
        if ($left <= 0) {
            return;
        }
        $lots = IntegralLog::find()
            ->where(['user_id' => $userId, 'type' => IntegralLog::TYPE_ADD, 'is_delete' => 0])
            ->andWhere(['>', 'remaining', 0])
            ->orderBy(['created_at' => SORT_ASC, 'id' => SORT_ASC])
            ->forUpdate()
            ->all();
        foreach ($lots as $lot) {
            if ($left <= 0.0001) {
                break;
            }
            $lotRemain = (float) $lot->remaining;
            $take = min($lotRemain, $left);
            IntegralLog::updateAll(['remaining' => $lotRemain - $take], ['id' => $lot->id]);
            $left -= $take;
        }
        // 批次不足以覆盖本次消费(通常=开启有效期前未跑 backfill,存量收益行 remaining 仍为 null 对 FIFO 不可见)。
        // 不静默吞:告警,提示需对该用户 backfill,避免"批次和 < 余额"的静默不平。
        if ($left > 0.0001) {
            \Yii::warning("[积分FIFO] 用户{$userId} 消费{$amount} 批次仅覆盖 " . ($amount - $left) . ",差 {$left} 未落批次(疑未 backfill),请核对 integral-expire-ops/backfill", 'integral');
        }
    }

    /**
     * 派发积分变动事件(集中一处,便于两个方法复用;任何 listener 异常都不冒泡影响账本)
     */
    private static function dispatchChanged($mallId, $userId, $amount, $direction, $bizType, $logId, $balanceAfter, $eventParams = [])
    {
        try {
            $event = new \app\events\IntegralEvent();
            $event->mallId = (int) $mallId;
            $event->userId = (int) $userId;
            $event->amount = (float) $amount;
            $event->direction = $direction;
            $event->bizType = $bizType;
            $event->logId = $logId ? (int) $logId : null;
            $event->balanceAfter = $balanceAfter !== null ? (float) $balanceAfter : null;
            $event->params = (array) $eventParams;
            \Yii::$app->trigger(\app\events\IntegralEvent::CHANGED, $event);
        } catch (\Throwable $e) {
            \Yii::error('[IntegralEvent] 派发积分变动事件失败: ' . $e->getMessage());
        }
    }

    /**
     * [最终优化版] 安全地减少用户积分 (原子更新 + 余额检查 + 幂等性)
     * @param int $user_id
     * @param float|string $integral
     * @param string|null $content
     * @param string|null $title
     * @param string|null $idempotencyKey [幂等键] 可选
     * @return bool|int
     * @throws \Throwable
     */
    /**
     * @param bool $ignore_balance 是否忽略积分不足（默认 false；退款追回等场景传 true，允许扣成负数）
     */
    public static function sub($user_id, $integral, $content = null, $title = null, $idempotencyKey = null, $ignore_balance = false, $bizType = null, $eventParams = [])
    {
        if (!is_numeric($integral) || $integral <= 0) {
            throw new Exception('积分必须是大于0的数字！');
        }

        // 1. 事务外快速检查
        if (!empty($idempotencyKey)) {
            $existingLog = IntegralLog::findOne(['idempotency_key' => $idempotencyKey]);
            if ($existingLog) {
                return $existingLog->id;
            }
        }

        $transaction = \Yii::$app->db->beginTransaction();
        $rolledBack = false;   // 同 add
        try {
            // 2. [核心] 原子更新并检查余额
            if ($ignore_balance) {
                // 强制扣减:只按 ID 匹配,可能扣成负数(退款/佣金追回场景)。
                // 不按 is_delete 过滤 —— 见类注释:追回本来就最该对已注销用户生效
                $updateCount = User::updateAllCounters(
                    ['integral' => -$integral],
                    ['id' => $user_id]
                );
            } else {
                // 正常消费：SQL WHERE integral >= :val 保证积分充足
                $updateCount = User::updateAllCounters(
                    ['integral' => -$integral],
                    ['and', ['id' => $user_id], ['>=', 'integral', $integral]]
                );
            }

            // 把当前积分带进异常信息 —— 只报一句「积分不足」时,如果真实原因是别的条件
            // 没匹配上,排查的人会盯着积分查半天(三个资金助手统一这个口径)
            if ($updateCount <= 0) {
                $cur = User::find()->select('integral')->where(['id' => $user_id])->scalar();
                if ($cur === false || $cur === null) {
                    throw new Exception('用户不存在！');
                }
                throw new Exception("用户积分不足！当前 {$cur},需扣 {$integral}");
            }

            // 4. 获取最新状态
            $user = User::find()
                ->select(['id', 'mall_id', 'integral'])
                ->where(['id' => $user_id])
                ->asArray()
                ->one();

            if (!$user) {
                throw new Exception('获取用户信息失败');
            }

            // 5. 记录日志
            $log = new IntegralLog();
            $log->mall_id = $user['mall_id'];
            $log->user_id = $user_id;
            $log->integral = $integral;
            $log->type = IntegralLog::TYPE_SUB; // 0=支出
            $log->content = $content;
            $log->current = $user['integral'];
            $log->date_at = date('Y-m-d');
            $log->title = $title ?: '支出';
            $log->biz_type = $bizType; // 业务类型(网关透传;历史调用为 null)

            if (!empty($idempotencyKey)) {
                $log->idempotency_key = $idempotencyKey;
            }

            // 5. 保存日志(同 add:唯一索引是唯一真实防线)
            try {
                if (!$log->save()) {
                    throw new Exception('记录积分日志失败: ' . current($log->getFirstErrors()));
                }
            } catch (\yii\db\IntegrityException $e) {
                // 同 add:先撤掉本次扣分,再锁定读回查
                $rolledBack = true;
                $transaction->rollBack();
                $existId = self::dupKeyExistingId($idempotencyKey);
                if (!$existId) {
                    throw $e;
                }
                \Yii::warning("幂等操作命中(DB Unique):key '{$idempotencyKey}' 已被处理。");
                return $existId;
            }

            // 6.5 有效期开启时:从最老收益行 FIFO 扣减 remaining(优先用先获取的积分)
            if (self::expireEnabled($user['mall_id'])) {
                self::deductFifo($user_id, (float) $integral);
            }

            $transaction->commit();

            // 7. 清理缓存
            try {
                CacheHelper::remove('user_data:' . $user_id);
            } catch (\Throwable $e) {
                \Yii::warning('清理用户缓存失败: ' . $e->getMessage());
            }

            // 8. 派发统一「积分变动」事件(chokepoint,覆盖全部扣分)
            self::dispatchChanged($user['mall_id'], $user_id, $integral, 'sub', $bizType, $log->id, $user['integral'], $eventParams);

            return $log->id;
        } catch (\Throwable $e) {
            if (!$rolledBack) {
                $transaction->rollBack();
            }
            \Yii::error('减少用户积分失败: ' . $e->getMessage());
            throw $e;
        }
    }
}