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

use app\events\IntegralEvent;
use app\helpers\CacheHelper;
use app\models\IntegralExpireRun;
use app\models\IntegralLog;
use app\models\User;
use Yii;

/**
 * 积分过期回收服务(有效期)
 *
 * 语义澄清:**不是把用户积分清零**,而是回收「入账日期早于截止日、且到现在仍未被花掉」的旧批次剩余。
 * 截止日之后新得的分、已花掉的分全不动;FIFO 保证常来消费的会员到期时旧批次早已为 0 → 几乎无损。
 *
 * 手动/自动两种方式共用本服务的 expireBefore(),仅"回收哪个截止日"由调用方决定(手动手填/自动按规则算)。
 *
 * 依赖 integral_log.remaining 批次(需商城开启有效期 integral_setting.expire.enabled)。
 */
class IntegralExpireService
{
    /**
     * 预览:统计将回收的用户数与积分总额(不落库,供管理员确认防误清)。
     */
    public function preview(int $mallId, string $beforeDate): array
    {
        $q = IntegralLog::find()
            ->where(['mall_id' => $mallId, 'type' => IntegralLog::TYPE_ADD, 'is_delete' => 0])
            ->andWhere(['<', 'date_at', $beforeDate])
            ->andWhere(['>', 'remaining', 0]);
        return [
            'affected_users'  => (int) (clone $q)->select('user_id')->distinct()->count(),
            'total_recovered' => (float) (clone $q)->sum('remaining'),
        ];
    }

    /**
     * 执行过期回收:回收 mall 内所有「入账早于 $beforeDate、remaining>0」的收益行剩余。
     * 逐用户独立事务(单用户失败不影响其他)。返回本次运行摘要。
     *
     * @param string $mode manual|rolling(仅记录用,不改逻辑)
     */
    public function expireBefore(int $mallId, string $beforeDate, string $mode = 'manual', int $operatorId = 0): array
    {
        $runNo = 'EXP' . date('YmdHis') . mt_rand(1000, 9999);

        // 找出有旧批次待回收的用户
        $userIds = IntegralLog::find()
            ->select('user_id')->distinct()
            ->where(['mall_id' => $mallId, 'type' => IntegralLog::TYPE_ADD, 'is_delete' => 0])
            ->andWhere(['<', 'date_at', $beforeDate])
            ->andWhere(['>', 'remaining', 0])
            ->column();

        $affected = 0;
        $totalRecovered = 0.0;

        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            $tx = Yii::$app->db->beginTransaction();
            try {
                // 先锁 user 行,再锁 lot 行 —— 与 sub()(user→lot)保持同序,规避 AB-BA 死锁
                $u = User::find()->select(['id', 'mall_id', 'integral'])->where(['id' => $uid])->forUpdate()->one();
                if (!$u) {
                    $tx->rollBack();
                    continue;
                }
                // 锁定该用户符合条件的旧收益行
                $lots = IntegralLog::find()
                    ->where(['user_id' => $uid, 'type' => IntegralLog::TYPE_ADD, 'is_delete' => 0])
                    ->andWhere(['<', 'date_at', $beforeDate])
                    ->andWhere(['>', 'remaining', 0])
                    ->forUpdate()
                    ->all();

                $recover = 0.0;
                foreach ($lots as $lot) {
                    $recover += (float) $lot->remaining;
                    IntegralLog::updateAll(['remaining' => 0], ['id' => $lot->id]);
                }
                if ($recover <= 0) {
                    $tx->rollBack();
                    continue;
                }

                // 扣余额(下限保护:不足则只扣到 0,不扣负)
                $bal = (float) $u['integral'];
                $dec = min($recover, max(0.0, $bal));
                if ($dec > 0) {
                    User::updateAllCounters(['integral' => -$dec], ['id' => $uid]);
                }
                // recover != dec 说明上游不变式已破(余额 < 批次和,通常=未 backfill),告警便于排查
                if (abs($recover - $dec) > 0.001) {
                    Yii::warning("[积分过期] 用户{$uid} 批次和{$recover} 大于余额{$bal},实扣{$dec}(疑不变式已破/未backfill)", 'integral');
                }

                // 写审计支出行(不走 sub(),避免二次 FIFO;金额记【实扣 dec】,与余额变动一致防台账虚高)
                $mall = (int) $u['mall_id'];
                $after = $bal - $dec;
                $log = new IntegralLog();
                $log->mall_id = $mall ?: $mallId;
                $log->user_id = $uid;
                $log->integral = $dec;
                $log->type = IntegralLog::TYPE_SUB;
                $log->biz_type = 'expire';
                $log->title = '积分过期';
                $log->content = '过期回收 ' . $runNo . '(截止 ' . $beforeDate . '前未用积分)';
                $log->current = $after;
                $log->date_at = date('Y-m-d');
                $log->idempotency_key = $runNo . '_' . $uid;
                $log->save(false);

                $tx->commit();

                CacheHelper::remove('user_data:' . $uid);
                $this->fireChanged($mall ?: $mallId, $uid, $dec, $log->id, $after, $runNo);

                $affected++;
                $totalRecovered += $dec;
            } catch (\Throwable $e) {
                $tx->rollBack();
                Yii::error('[积分过期] 用户 ' . $uid . ' 回收失败: ' . $e->getMessage(), 'integral');
            }
        }

        // 记录运行流水
        $run = new IntegralExpireRun();
        $run->mall_id = $mallId;
        $run->run_no = $runNo;
        $run->mode = $mode;
        $run->clear_before = $beforeDate;
        $run->affected_users = $affected;
        $run->total_recovered = $totalRecovered;
        $run->operator_id = $operatorId;
        $run->executed_at = time();
        $run->save(false);

        return ['run_no' => $runNo, 'affected_users' => $affected, 'total_recovered' => $totalRecovered];
    }

    /**
     * 存量 backfill:为历史收益行重建 remaining(开启有效期时一次性执行)。
     *
     * 算法(利用 FIFO 不变式):FIFO 先花旧分 → 未花的余额一定沉在**最新**的收益行里。
     * 故按 created_at 从**新到旧**给收益行填 remaining,填满当前余额为止,更旧的置 0。
     * 与"逐笔重放消费"等价但 O(n)。支出行 remaining 恒 null。
     *
     * @return int 处理的用户数
     */
    public function backfill(int $mallId): int
    {
        $userIds = IntegralLog::find()
            ->select('user_id')->distinct()
            ->where(['mall_id' => $mallId, 'type' => IntegralLog::TYPE_ADD, 'is_delete' => 0])
            ->column();

        $count = 0;
        foreach ($userIds as $uid) {
            $this->backfillUser($mallId, (int) $uid);
            $count++;
        }
        return $count;
    }

    /**
     * 单用户 backfill:按 created_at 新→旧给收益行填 remaining 至当前余额,更旧置 0。
     */
    public function backfillUser(int $mallId, int $userId): void
    {
        $balance = (float) User::find()->select('integral')->where(['id' => $userId])->scalar();
        $left = max(0.0, $balance);

        $earns = IntegralLog::find()
            ->where(['mall_id' => $mallId, 'user_id' => $userId, 'type' => IntegralLog::TYPE_ADD, 'is_delete' => 0])
            ->orderBy(['created_at' => SORT_DESC, 'id' => SORT_DESC]) // 新→旧
            ->all();

        foreach ($earns as $earn) {
            $assign = min((float) $earn->integral, $left);
            IntegralLog::updateAll(['remaining' => $assign], ['id' => $earn->id]);
            $left -= $assign;
        }
    }

    /**
     * 对账:逐用户校验 SUM(收益行 remaining) == user.integral。返回不平的用户明细(空=全平)。
     */
    public function reconcile(int $mallId): array
    {
        // 未开启有效期的商城不维护批次,不适用本不变式,直接返回空(避免对全体 null 用户假报不平)
        if (empty(\app\helpers\IntegralSettingHelper::get($mallId)['expire']['enabled'])) {
            return [];
        }
        // 用 COALESCE(remaining,0):把"未 backfill 的 null 收益行"按 0 计入,
        // 从而能检出「余额>0 但批次全 null」的存量漏网用户(旧 is-not-null 过滤会把他们整体漏掉=假阳性)。
        $rows = IntegralLog::find()
            ->select(['user_id', 'lot_sum' => 'SUM(COALESCE(remaining,0))'])
            ->where(['mall_id' => $mallId, 'type' => IntegralLog::TYPE_ADD, 'is_delete' => 0])
            ->groupBy('user_id')
            ->asArray()
            ->all();

        $diffs = [];
        foreach ($rows as $r) {
            $uid = (int) $r['user_id'];
            $lotSum = (float) $r['lot_sum'];
            $bal = (float) User::find()->select('integral')->where(['id' => $uid])->scalar();
            if (abs($lotSum - $bal) > 0.001) {
                $diffs[] = ['user_id' => $uid, 'lot_sum' => $lotSum, 'balance' => $bal, 'diff' => $lotSum - $bal];
            }
        }
        return $diffs;
    }

    /**
     * 派发过期回收的积分变动事件(与账本入口一致,保证升级/通知等 listener 不漏)
     */
    private function fireChanged(int $mallId, int $userId, float $amount, $logId, float $balanceAfter, string $runNo): void
    {
        try {
            $e = new IntegralEvent();
            $e->mallId = $mallId;
            $e->userId = $userId;
            $e->amount = $amount;
            $e->direction = 'sub';
            $e->bizType = 'expire';
            $e->logId = $logId ? (int) $logId : null;
            $e->balanceAfter = $balanceAfter;
            $e->params = ['run_no' => $runNo];
            Yii::$app->trigger(IntegralEvent::CHANGED, $e);
        } catch (\Throwable $e) {
            Yii::error('[积分过期] 事件派发失败: ' . $e->getMessage(), 'integral');
        }
    }
}
