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

use app\events\UserLevelChangedEvent;
use app\models\Level;
use app\models\User;
use app\models\UserLevelGrant;
use app\models\UserUpgradeLog;
use Yii;

/**
 * 用户等级有效期台账服务(核心通用)
 *
 * 任何插件给用户升级且需要"到期降级"时:
 *   1. 自己完成升级(或调 setUserLevel)
 *   2. 调 record() 写一条带 expire_at 的台账
 * 核心 cron(UserLevelExpireTask)调 processExpired() 到点降级。
 *
 * 降级目标(已与产品确认):取该用户其它仍有效授予的最高等级;都没有则回退到授予前等级。
 */
class UserLevelGrantService
{
    /**
     * 自管降级来源:这些来源有自己的到期 cron 负责降级,本通用 cron 只收录、不重复降。
     * membership → MembershipExpireTask。
     */
    const SELF_MANAGED_SOURCES = ['membership'];

    /**
     * 写一条"带有效期"的等级授予台账(不做升级动作,升级由调用方完成)
     *
     * @param int $beforeLevelId 升级前的等级ID(快照,用于回退)
     * @param int $expireDays    有效期天数(>0 才写)
     */
    public static function record(int $mallId, int $userId, int $levelId, int $beforeLevelId, int $expireDays, string $source = '', string $bizNo = '', string $remark = ''): ?UserLevelGrant
    {
        if ($expireDays <= 0 || $levelId <= 0 || $userId <= 0) {
            return null;
        }
        $now = time();
        $g = new UserLevelGrant([
            'mall_id'             => $mallId,
            'user_id'             => $userId,
            'level_id'            => $levelId,
            'level_weight'        => self::levelWeight($levelId),
            'before_level_id'     => $beforeLevelId,
            'before_level_weight' => self::levelWeight($beforeLevelId),
            'grant_at'            => $now,
            'expire_at'           => $now + $expireDays * 86400,
            'status'              => UserLevelGrant::STATUS_ACTIVE,
            'source'              => $source,
            'biz_no'              => $bizNo,
            'remark'              => $remark,
        ]);
        if (!$g->save()) {
            Yii::error('[等级台账] 写入失败: ' . json_encode($g->getFirstErrors(), JSON_UNESCAPED_UNICODE));
            return null;
        }
        return $g;
    }

    /**
     * 写/刷新一条带有效期的台账(按 source+biz_no 幂等;续费时刷新到期时间)
     * @param int $expireAt 绝对到期时间戳
     */
    public static function recordOrRefresh(int $mallId, int $userId, int $levelId, int $beforeLevelId, int $expireAt, string $source, string $bizNo, string $remark = ''): ?UserLevelGrant
    {
        if ($levelId <= 0 || $userId <= 0 || $expireAt <= 0) {
            return null;
        }
        $g = UserLevelGrant::findOne([
            'mall_id' => $mallId, 'source' => $source, 'biz_no' => $bizNo,
            'status' => UserLevelGrant::STATUS_ACTIVE, 'is_delete' => 0,
        ]);
        if ($g) {
            $g->level_id     = $levelId;
            $g->level_weight = self::levelWeight($levelId);
            $g->expire_at    = $expireAt;
            $g->save(false);
            return $g;
        }
        $g = new UserLevelGrant([
            'mall_id' => $mallId, 'user_id' => $userId,
            'level_id' => $levelId, 'level_weight' => self::levelWeight($levelId),
            'before_level_id' => $beforeLevelId, 'before_level_weight' => self::levelWeight($beforeLevelId),
            'grant_at' => time(), 'expire_at' => $expireAt,
            'status' => UserLevelGrant::STATUS_ACTIVE, 'source' => $source, 'biz_no' => $bizNo, 'remark' => $remark,
        ]);
        if (!$g->save()) {
            Yii::error('[等级台账] recordOrRefresh 失败: ' . json_encode($g->getFirstErrors(), JSON_UNESCAPED_UNICODE));
            return null;
        }
        return $g;
    }

    /**
     * 标记某来源单号的有效台账为"已降级"(不动 user 等级)。
     * 供自管降级的插件(如 membership)在自己降级后调用,保持台账状态一致。
     */
    public static function markDowngradedByBiz(int $mallId, string $source, string $bizNo): void
    {
        UserLevelGrant::updateAll(
            ['status' => UserLevelGrant::STATUS_EXPIRED],
            ['mall_id' => $mallId, 'source' => $source, 'biz_no' => $bizNo, 'status' => UserLevelGrant::STATUS_ACTIVE]
        );
    }

    /**
     * 处理到期记录,执行降级(cron 调用,Manager 已注入 mall 上下文)
     * 跳过 SELF_MANAGED_SOURCES(由各自插件 cron 降级,本 cron 只收录不重复降)。
     * @return int 降级用户数
     */
    public static function processExpired(int $mallId): int
    {
        $now = time();
        // 有到期待降级记录的用户(排除自管来源)
        $userIds = UserLevelGrant::find()
            ->select('user_id')->distinct()
            ->where(['mall_id' => $mallId, 'status' => UserLevelGrant::STATUS_ACTIVE])
            ->andWhere(['<=', 'expire_at', $now])
            ->andWhere(['not in', 'source', self::SELF_MANAGED_SOURCES])
            ->column();

        $changed = 0;
        foreach ($userIds as $userId) {
            $userId = (int)$userId;
            // 本批到期记录(排除自管来源)
            $expired = UserLevelGrant::find()
                ->where(['mall_id' => $mallId, 'user_id' => $userId, 'status' => UserLevelGrant::STATUS_ACTIVE])
                ->andWhere(['<=', 'expire_at', $now])
                ->andWhere(['not in', 'source', self::SELF_MANAGED_SOURCES])->all();
            if (empty($expired)) continue;

            // 标记已降级
            $ids = array_map(fn($g) => $g->id, $expired);
            $expiredLevelIds = array_map(fn($g) => (int)$g->level_id, $expired);
            UserLevelGrant::updateAll(['status' => UserLevelGrant::STATUS_EXPIRED], ['id' => $ids]);

            if (self::downgradeUser($mallId, $userId, $expiredLevelIds, 'level_expire', '等级到期降级')) {
                $changed++;
            }
        }
        return $changed;
    }

    /**
     * 撤销某来源单号的授予(退款用):置撤销 + 重算降级
     */
    public static function revokeByBiz(int $mallId, string $source, string $bizNo): void
    {
        $rows = UserLevelGrant::find()
            ->where(['mall_id' => $mallId, 'source' => $source, 'biz_no' => $bizNo, 'status' => UserLevelGrant::STATUS_ACTIVE])
            ->all();
        if (empty($rows)) return;
        $byUser = [];
        foreach ($rows as $r) { $byUser[(int)$r->user_id][] = $r; }
        UserLevelGrant::updateAll(['status' => UserLevelGrant::STATUS_REVOKED], ['id' => array_map(fn($r) => $r->id, $rows)]);
        foreach ($byUser as $userId => $batch) {
            $lvIds = array_map(fn($r) => (int)$r->level_id, $batch);
            self::downgradeUser($mallId, (int)$userId, $lvIds, 'level_revoke', '授予撤销降级');
        }
    }

    /**
     * 降级一个用户(带"等级匹配"守卫)
     *
     * 守卫:只有当用户【当前 level_id】正是这条到期/撤销授予给的等级时,才执行降级。
     * 否则说明现等级不是本授予在生效(后台手改成更高或更低、membership、永久升级等)
     * —— 一律不动,既不误降更高的,也不覆盖后台手设的更低等级。
     *
     * @param int[] $expiredLevelIds 本批到期/撤销授予的目标等级ID
     * @return bool 是否实际降级
     */
    private static function downgradeUser(int $mallId, int $userId, array $expiredLevelIds, string $source, string $marks): bool
    {
        $user = User::getUser($userId);
        if (!$user) {
            return false;
        }
        if (!in_array((int)$user->level_id, $expiredLevelIds, true)) {
            Yii::warning("[等级台账] user={$userId} 现等级 level_id={$user->level_id} 非本批到期授予(" . implode(',', $expiredLevelIds) . "),判定来自台账外/已被改,跳过降级");
            return false;
        }
        $target = self::resolveTargetLevel($mallId, $userId, time());
        return self::setUserLevel($userId, $target['level_id'], $target['weight'], $source, $marks);
    }

    /**
     * 计算降级目标 [level_id, weight]
     */
    private static function resolveTargetLevel(int $mallId, int $userId, int $now): array
    {
        // 其它仍有效授予里权重最高的
        $active = UserLevelGrant::find()
            ->where(['mall_id' => $mallId, 'user_id' => $userId, 'status' => UserLevelGrant::STATUS_ACTIVE])
            ->andWhere(['>', 'expire_at', $now])->all();
        $best = ['level_id' => 0, 'weight' => -1];
        foreach ($active as $g) {
            if ((int)$g->level_weight > $best['weight']) {
                $best = ['level_id' => (int)$g->level_id, 'weight' => (int)$g->level_weight];
            }
        }
        if ($best['weight'] >= 0) {
            return $best;
        }
        // 没有其它有效授予 → 回退到"原始底":该用户所有授予记录里最低的 before 等级。
        // 取全历史最低 before(而非仅本批),可正确处理叠加授予顺序到期的场景,避免落在中间过期级。
        $all = UserLevelGrant::find()
            ->where(['mall_id' => $mallId, 'user_id' => $userId])
            ->select(['before_level_id', 'before_level_weight'])->asArray()->all();
        $floor = null;
        foreach ($all as $g) {
            if ($floor === null || (int)$g['before_level_weight'] < $floor['weight']) {
                $floor = ['level_id' => (int)$g['before_level_id'], 'weight' => (int)$g['before_level_weight']];
            }
        }
        return $floor ?: ['level_id' => 0, 'weight' => 0];
    }

    /**
     * 设置用户等级 —— 全平台统一 canonical 写入链路(行锁 + level + UserUpgradeLog + onLevelChanged + 广播)。
     *
     * 所有写 user.level_id 的业务(限时授予/到期降级/会员开通/数智情感升级码等)都应走这里,
     * 以保证:① 并发写者在 user 行上串行化、各取锁内真实 old；② 团队统计 + 下游订阅方统一同步。
     *
     * @param bool $raiseOnly true=仅升不降(目标权重不高于锁内真实当前权重则不动,用于会员/赠送等级)
     * @param bool $isManual  true=置 user.is_manual=1(后台/手动/码核销等"非条件引擎"来源)
     * @return bool 是否实际变更
     */
    public static function setUserLevel(int $userId, int $targetLevelId, int $targetWeight, string $source, string $marks, bool $raiseOnly = false, bool $isManual = false): bool
    {
        $user = User::getUser($userId);
        if (!$user) return false;

        // 目标权重:调用方给了就用,否则按等级反查(raiseOnly 守卫要用它和锁内当前权重比)
        $effectiveWeight = $targetLevelId > 0 ? ($targetWeight > 0 ? $targetWeight : self::levelWeight($targetLevelId)) : 0;

        $db = Yii::$app->db;
        $tx = $db->beginTransaction();
        $beforeLevelId = 0;
        try {
            // 行锁重读真实当前等级:与并发写者(升级Job/会员/后台改级)串行化,取准确 old
            $cur = \app\services\UserService::lockUserLevelRow($userId);
            if ($cur === null) { $tx->rollBack(); return false; }
            $beforeLevelId = $cur['level_id'];
            // 已是目标等级 → 无需变更
            if ($beforeLevelId === (int)$targetLevelId) { $tx->rollBack(); return false; }
            // 仅升不降守卫(对锁内真实当前权重判定):目标权重不高于当前则不动
            if ($raiseOnly && $effectiveWeight <= $cur['level']) { $tx->rollBack(); return false; }

            $user->level_id = $targetLevelId;
            $user->level    = $effectiveWeight;
            $user->level_at = time();
            if ($isManual) {
                $user->is_manual = 1;
            }
            if (!$user->save(false)) {
                $tx->rollBack();
                Yii::error("[等级台账] 设置等级失败 user={$userId}");
                return false;
            }

            $log = new UserUpgradeLog([
                'mall_id' => $user->mall_id, 'user_id' => $user->id,
                'before_level_id' => $beforeLevelId, 'after_level_id' => $targetLevelId,
                'reason_by' => 3, 'marks' => $marks,
            ]);
            $log->save();

            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            Yii::error('[等级台账] 设置等级异常: ' . $e->getMessage());
            return false;
        }

        try {
            \app\services\TeamStatUpdater::onLevelChanged($user->id, $user->mall_id, $beforeLevelId, $targetLevelId);
            Yii::$app->trigger(UserLevelChangedEvent::EVENT_NAME, new UserLevelChangedEvent([
                'user' => $user, 'oldLevelId' => $beforeLevelId, 'newLevelId' => $targetLevelId, 'source' => $source,
            ]));
        } catch (\Throwable $e) {
            Yii::error('[等级台账] 等级变化广播失败: ' . $e->getMessage());
        }
        return true;
    }

    private static function levelWeight(int $levelId): int
    {
        if ($levelId <= 0) return 0;
        $lv = Level::findOne(['id' => $levelId, 'is_delete' => 0]);
        return $lv ? (int)$lv->level : 0;
    }
}
