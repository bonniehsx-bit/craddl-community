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

use app\models\Order;
use app\models\OrderDetail;
use app\models\User;
use app\models\UserPath;
use app\models\UserTeamStat;
use Yii;
use yii\helpers\Json;

/**
 * 团队统计增量更新服务
 * 在关键业务节点调用，增量维护 UserTeamStat 表
 */
class TeamStatUpdater
{
    /** 单条 IN(...) 里最多放多少个 id;超了分批执行 —— 分批不等于截断,一个祖先都不会少 */
    private const IN_CHUNK = 1000;

    /**
     * 取某人的**完整**祖先链(自顶向下,不含本人)。
     *
     * ⚠ 这里曾经写成 array_slice(parseAncestorIds(...), 0, 50),六处都是,
     *   注释还写着「限制最多50层」。那段代码有两个错:
     *
     *   ① **方向反了**。parseAncestorIds 返回的是「根在前、直属上级在末」
     *      (UserService.php 里凡是要「近→远」的地方都得先 array_reverse)。
     *      slice(0,50) 保留的是最靠近根的 50 个,**把离本人最近的那一段全丢了**。
     *      实测:173 层的用户,拿到的是链顶那 50 个,他自己的直属上级根本不在里面;
     *      抽样 500 个超 50 层的用户,490 个(98%)的直属上级被漏掉。
     *
     *   ② **本就不该封顶**。它是性能保护(引入提交自述「50层覆盖99.9%的业务场景」),
     *      但代价是丢数据。祖先集来自完整 path,增量更新只是一条 WHERE user_id IN (...),
     *      173 个 id 的 SQL 还不到 2KB,省这点开销毫无意义。
     *
     *   线上最深 173 层、超 50 层的有 16341 人(16.5%),属常态触发;
     *   而 dev 库最深仅 22 层 —— 这个 bug 在本地永远测不出来。
     *
     *   要控开销请用 IN_CHUNK 分批,不要截断链。
     *
     * 读库里现成的 path(getVerifiedPath:2 条 SQL,顺带核对与 parent_id 是否一致,不一致当场重建),不用 ensurePath 按 parent_id 逐层上溯:
     * 后者每层一条 SELECT,千层链一个事件就是上千条 SQL,付款 / 退款 / 绑上级 / 改等级每次都要走。
     * 口径和分润一致 —— 分润取上级走 getParents,本来就读的是库里的 path。
     * path 的正确性由写入侧保证:bindParent 按 parent_id 强制重建自己的 path,子孙由 PathTreeUpdateJob 改写;
     * 读的时候核对 path 与 parent_id,改挂后子孙 path 还没改写到、或 path 损坏,都会被发现并当场按 parent_id 重建。
     */
    private static function ancestorsOf(int $userId): array
    {
        return UserService::parseAncestorIds(UserService::getVerifiedPath($userId));
    }

    /**
     * 按 user_id 批量更新 user_team_stat,id 多了自动分批。
     *
     * @param array       $ids    目标 user_id(不去重、不截断)
     * @param string      $set    SET 子句,如 "team_count = team_count + 1, dirty = 1"
     * @param array       $params 绑定参数
     * @param object|null $db     指定连接(事务内调用时传入),null=默认连接
     */
    private static function updateStatIn(array $ids, string $set, array $params = [], $db = null): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return;
        }
        $db = $db ?: Yii::$app->db;
        foreach (array_chunk($ids, self::IN_CHUNK) as $part) {
            $in = implode(',', $part);
            $db->createCommand(
                'UPDATE ' . UserTeamStat::tableName() . " SET {$set} WHERE user_id IN ({$in})",
                $params
            )->execute();
        }
    }

    /**
     * 绑定 / 改上级 / 解绑之后调用(bindParent 提交之后):把「本人 + 他整个下级团队」从旧上级链挪到新上级链。
     *
     * 原来的 onUserBind 只给新链加:新上级 direct_count +1、新链 team_count +1,旧链一个都不减 ——
     * 后台改上级后,旧链上每个人的团队人数都多算 1,旧上级的直推人数、直推 / 团队等级分布、各直推线里也都还算着他;
     * 连人带团队挪走时新链也只加 1。这些只能等 TeamStatScrubTask 巡检全量重算才纠正(2026-09 端到端实测)。
     *
     * 口径与 fullRebuild 一致:
     *   · team_count:子树里 user_path 的行数(含已注销的,fullRebuild 也不过滤);
     *   · direct_count / direct_level_map:本人未注销才算;team_level_map:队里未注销、有等级的人按 level_id 计;
     *   · team_legs_map:旧上级删掉这条线,旧链往上各线 count 减、max_level 去掉这一队后重算;
     *     新上级加上这条线 {count: 整队人数, max_level: 队里最高等级},新链往上各线 count 加、max_level 只升;
     *   · team_spend_paid / team_spend_settled:这一队(含本人)的消费一起挪,金额按 fullRebuild 同一口径现算(spendQuery)。
     *     团队消费在别处都按「现在的链」记 —— 付款、结算加,退款扣,都取那一刻的祖先链;巡检按现在的子树重算。
     *     改挂时不挪,旧链多算、新链少算,改挂前的单在改挂后退款会扣到新链上(新链没加过,扣到 0 为止),都要等巡检轮到才纠正。
     *     month_team_spend 不挪:没有地方读它,fullRebuild 也不重算它。
     *
     * 新旧两条链都由 bindParent 在改挂事务里取好传进来,不在提交后再读 path:提交后到这里之间,
     * 同一个人可能又被改挂一次,再读就会两次都挂到最新那条链上。
     * 每一步都是单条原子 UPDATE(与 onOrderPaid / onLevelChanged 同法);失败只记日志,不影响已提交的改挂,漏的由巡检兜底。
     *
     * @param array       $oldAncestors 改挂前的祖先链(根在前,不含本人);原来没有上级为 []
     * @param string      $oldSelfPath  改挂前本人的 path
     * @param string|null $newSelfPath  改挂后本人的 path(改挂事务里写入的那个);null = 按现在的 path 取
     */
    public static function onUserMoved(int $userId, int $mallId, array $oldAncestors, string $oldSelfPath, ?string $newSelfPath = null): void
    {
        try {
            UserTeamStat::getOrCreate($userId, $mallId);   // 首次绑定的新用户,自己的行也建上
            $newSelfPath = $newSelfPath ?? UserService::getVerifiedPath($userId);
            $newAncestors = UserService::parseAncestorIds($newSelfPath);
            $oldAncestors = array_values(array_filter(array_map('intval', $oldAncestors)));
            if ($oldAncestors === $newAncestors) {
                return;
            }
            $team = self::movedTeam($userId, $mallId, $oldSelfPath, $newSelfPath);
            if ($oldAncestors) {
                self::detachTeam($userId, $mallId, $oldAncestors, $team);
            }
            if ($newAncestors) {
                self::attachTeam($userId, $mallId, $newAncestors, $team);
            }
        } catch (\Throwable $e) {
            Yii::error("[TeamStat] onUserMoved 失败 user={$userId}: " . $e->getMessage());
        }
    }

    /**
     * 首次绑定(原来没有上级)的入口:等于旧链为空的 onUserMoved,新链按本人现在的 path 取。
     * 改上级 / 解绑必须走 onUserMoved(要带改挂前的旧链),bindParent 已经这么做了;保留本方法给直接模拟绑定事件的测试用。
     * $parentId 只为兼容原签名,不参与计算。
     */
    public static function onUserBind(int $userId, int $parentId, int $mallId): void
    {
        self::onUserMoved($userId, $mallId, [], '');
    }

    /**
     * 被挪的这一队(本人 + 全部子孙):人数、等级分布、最高等级、团队消费。
     * 按「path 里含有 -本人-」圈人,不按前缀:子孙 path 改写时机不定(当场改写失败退回排队、或同一个人前后被改挂两次),
     * 子孙可能还挂在更早的前缀下;含有本人 id 这一点不随前缀变。代价是扫一遍本商城 user_path(与 PathTreeUpdateJob 同一扫法),
     * 只有带下级的改挂才走 —— 首次绑定、普通会员改挂都是单人,不扫。
     * likes(改挂前后本人 path 的前缀)只用于旧链各线重算时排除这一队。
     */
    private static function movedTeam(int $userId, int $mallId, string $oldSelfPath, string $newSelfPath): array
    {
        $me = User::find()->select(['level_id', 'level', 'is_delete'])->where(['id' => $userId])->asArray()->one() ?: [];
        $alive = isset($me['is_delete']) && (int)$me['is_delete'] === 0;
        $likes = [];
        foreach ([$oldSelfPath, $newSelfPath] as $p) {
            if (substr($p, -strlen("-{$userId}-")) === "-{$userId}-") {
                $likes[] = $p . '%';
            }
        }
        $team = [
            'alive' => $alive,
            'level_id' => $alive ? (int)$me['level_id'] : 0,
            'size' => 1,
            'levels' => $alive && (int)$me['level_id'] > 0 ? [(int)$me['level_id'] => 1] : [],
            'max' => $alive ? (int)$me['level'] : 0,
            'likes' => array_values(array_unique($likes)),
            'hasKids' => User::find()->where(['parent_id' => $userId])->exists(),   // 含已注销的下级:他们的 path 行也算人数
        ];
        if ($team['hasKids']) {
            // 一次扫描出:行数(含注销、含没有 user 记录的孤儿 path,与 fullRebuild 的 team_count 同口径)、未注销按等级计数、未注销最高等级
            $rows = (new \yii\db\Query())
                ->select(['u.level_id', 'u.is_delete', 'u.mall_id', 'cnt' => 'COUNT(*)', 'mx' => 'MAX(u.level)'])
                ->from(UserPath::tableName() . ' p')
                ->leftJoin(User::tableName() . ' u', 'u.id = p.user_id')
                ->where(['p.mall_id' => $mallId])
                ->andWhere(new \yii\db\Expression('LOCATE(:needle, p.path) > 0', [':needle' => "-{$userId}-"]))
                ->groupBy(['u.level_id', 'u.is_delete', 'u.mall_id'])
                ->all();
            $team['size'] = 0;
            $team['levels'] = [];
            $team['max'] = 0;
            foreach ($rows as $r) {
                $team['size'] += (int)$r['cnt'];
                if ($r['is_delete'] !== null && (int)$r['is_delete'] === 0) {
                    if ((int)$r['level_id'] > 0) {
                        $team['levels'][(int)$r['level_id']] = ($team['levels'][(int)$r['level_id']] ?? 0) + (int)$r['cnt'];
                    }
                    if ((int)$r['mall_id'] === $mallId) {
                        $team['max'] = max($team['max'], (int)$r['mx']);
                    }
                }
            }
            $team['size'] = max($team['size'], 1);
        }
        self::useAccountedLevels($team, $userId, $mallId);
        self::useAccountedMax($team, $userId, $mallId);
        [$team['spendPaid'], $team['spendSettled']] = self::movedSpend($userId, $mallId, $team['hasKids']);
        return $team;
    }

    /**
     * 这一队的最高等级也按统计里记着的等级算(见 lowerLegMaxIn):新链各线按它只升,
     * 改等级还在排队的人先按新等级算进去,事件落地时降级重算就会停错地方。队里没人排队就不动。
     */
    private static function useAccountedMax(array &$team, int $userId, int $mallId): void
    {
        $in = array_filter(self::pendingLevelWeights($mallId),
            fn($x, $uid) => $uid === $userId || strpos($x['path'], "-{$userId}-") !== false, ARRAY_FILTER_USE_BOTH);
        if (!$in) {
            return;
        }
        $max = 0;
        if ($team['hasKids']) {   // 其余队员按现在的等级(圈法同 movedTeam)
            $max = (int)(UserPath::find()->alias('p')
                ->innerJoin(User::tableName() . ' u', 'u.id = p.user_id')
                ->where(['p.mall_id' => $mallId, 'u.is_delete' => 0, 'u.mall_id' => $mallId])
                ->andWhere(new \yii\db\Expression('LOCATE(:needle, p.path) > 0', [':needle' => "-{$userId}-"]))
                ->andWhere(['not in', 'u.id', array_keys($in)])
                ->max('u.level') ?: 0);
        }
        $team['max'] = max($max, max(array_column($in, 'w')));
    }

    /**
     * 这一队的团队消费 [已付, 已结算]:与 fullRebuild 同一口径(spendQuery),圈人同 movedTeam(path 含 -本人-)。
     * 先扫一遍 user_path 取出这一队的 id,再按订单 user_id 索引分批求和;没下级的只算本人。
     * 不把 LOCATE 条件直接连进求和:优化器会从本商城全部已付订单扫起、逐单回查 path
     * (实测 5 万单的库,777 人的队一次 0.5 秒,且随订单量涨);分批按 id 查每千人 2 毫秒上下。
     * fullRebuild 能直接连,是因为它按前缀圈人、走得了 path 索引;这里每批 IN_CHUNK 个 id,撞不到占位符上限。
     *
     * 付款 / 结算事件还在排队的单不算、回滚还在排队的照算(见 EffectiveSpend::accounted):
     * 挪的是统计里已经记上的,排队的事件落地时按那一刻的链记,自然记到新链上 —— 与 useAccountedLevels 同理。
     */
    private static function movedSpend(int $userId, int $mallId, bool $hasKids): array
    {
        $ids = $hasKids
            ? array_map('intval', UserPath::find()->select('user_id')->where(['mall_id' => $mallId])
                ->andWhere(new \yii\db\Expression('LOCATE(:needle, path) > 0', [':needle' => "-{$userId}-"]))->column())
            : [$userId];
        $out = [0.0, 0.0];
        foreach (array_chunk($ids, self::IN_CHUNK) as $part) {
            foreach ([0 => false, 1 => true] as $i => $settled) {
                $out[$i] += (float)(self::spendQuery($mallId, $settled)->andWhere(['o.user_id' => $part])->sum('od.pay_price') ?: 0);
            }
        }
        return [round($out[0], 2), round($out[1], 2)];
    }

    /** 团队消费随这一队加($sign=1)或减($sign=-1,减到 0 为止)的 SET 片段,以逗号开头;金额为 0 的不写 */
    private static function sqlSpendShift(array $team, int $sign): string
    {
        $sql = '';
        foreach (['team_spend_paid' => $team['spendPaid'], 'team_spend_settled' => $team['spendSettled']] as $col => $amount) {
            if ($amount > 0) {
                $v = number_format($amount, 2, '.', '');
                $sql .= $sign > 0 ? ", {$col} = {$col} + {$v}" : ", {$col} = GREATEST({$col} - {$v}, 0)";
            }
        }
        return $sql;
    }

    /**
     * 等级分布按「统计里记着的等级」挪,而不是 user 表现在的等级。
     *
     * 开了 outbox 时,改等级是先改 user 表、事件排队(每分钟消费一次),统计里记的还是改之前的等级;
     * 事件落地时按那一刻的链加减(onLevelChanged 取当前 path)。所以挪人时:旧链减掉它当初加的(改之前的等级),
     * 新链也先按改之前的加,排队的事件之后落到新链上自己把它换成新等级。
     * 按 user 表现在的等级挪的话,旧链减的是它没加过的等级(减到 0 截住,等于没减),事件落到新链又加一次(2026-09 端到端实测)。
     * 只看还在排队的(PENDING):进了死信的不会再被消费,巡检按 user 表的现等级重建,统计最终收敛到的就是现等级。
     * 各线最高等级是按现在的等级重算 / 只升,不受影响。
     */
    private static function useAccountedLevels(array &$team, int $userId, int $mallId): void
    {
        // uid => 统计里记着的等级。没开 outbox 时为空:改等级当场同步落统计,user 表就是统计里记着的。
        // 只有本人(注册绑定等最常见的情况)时只取他自己的事件,不把整个商城排队中的事件都读出来
        $firstBefore = TeamStatOutbox::accountedLevelIds($mallId, $team['hasKids'] ? null : $userId);
        if (!$firstBefore) {
            return;
        }
        // 只管这一队里的人:本人,或 path 里含有本人的(与 movedTeam 同一圈法)
        $rows = UserPath::find()->alias('p')
            ->innerJoin(User::tableName() . ' u', 'u.id = p.user_id')
            ->select(['u.level_id', 'u.id'])
            ->where(['p.user_id' => array_keys($firstBefore), 'u.is_delete' => 0])
            ->andWhere(['or', ['p.user_id' => $userId], new \yii\db\Expression('LOCATE(:n, p.path) > 0', [':n' => "-{$userId}-"])])
            ->indexBy('id')->column();
        foreach ($rows as $uid => $now) {
            $now = (int)$now;
            $was = $firstBefore[(int)$uid];
            if ($now === $was) {
                continue;
            }
            if ($now > 0 && isset($team['levels'][$now])) {
                if (--$team['levels'][$now] <= 0) {
                    unset($team['levels'][$now]);
                }
            }
            if ($was > 0) {
                $team['levels'][$was] = ($team['levels'][$was] ?? 0) + 1;
            }
            if ((int)$uid === $userId) {
                $team['level_id'] = $was;
            }
        }
    }

    /**
     * 旧链减掉这一队。
     * 先写人数、等级分布这些纯计数,各直推线(JSON)放在后面、每步单独兜底:线写失败(比如 MEDIUMTEXT 迁移前超长)不连累人数。
     */
    private static function detachTeam(int $userId, int $mallId, array $chain, array $team): void
    {
        $parent = (int)end($chain);
        // 旧链每个人:团队人数、团队等级分布、团队消费减去这一队
        $set = 'team_count = GREATEST(team_count - ' . (int)$team['size'] . ', 0), dirty = 1';
        if ($team['levels']) {
            $set .= ', team_level_map = ' . self::sqlMapShift('team_level_map', array_map(fn($n) => -$n, $team['levels']));
        }
        $set .= self::sqlSpendShift($team, -1);
        self::execChainUpdate($chain, $set);
        // 旧上级:他未注销时直推 -1、直推等级分布 -1
        if ($team['alive']) {
            $set = 'direct_count = GREATEST(direct_count - 1, 0), dirty = 1';
            if ($team['level_id'] > 0) {
                $set .= ', direct_level_map = ' . self::sqlMapShift('direct_level_map', [$team['level_id'] => -1]);
            }
            self::execWithDeadlockRetry('UPDATE ' . UserTeamStat::tableName() . " SET {$set} WHERE user_id = {$parent}");
        }
        // 各线:旧上级删掉通往他的这条线;再往上通往他的那条线人数减去这一队,最高等级去掉这一队重算(没变就停)
        self::legStep($userId, '旧上级删线', fn() => self::execWithDeadlockRetry('UPDATE ' . UserTeamStat::tableName()
            . ' SET team_legs_map = JSON_REMOVE(' . self::sqlJsonObj('team_legs_map') . ", '$.\"{$userId}\"'), dirty = 1 WHERE user_id = {$parent}"));
        self::legStep($userId, '旧链线人数', fn() => self::shiftLegCountIn(self::chainLegs($chain), -(int)$team['size']));
        self::legStep($userId, '旧链线最高等级', fn() => self::lowerLegMaxIn($chain, $mallId, $team['likes']));
    }

    /** 新链加上这一队(写入顺序同 detachTeam) */
    private static function attachTeam(int $userId, int $mallId, array $chain, array $team): void
    {
        $sorted = $chain;
        sort($sorted);
        self::ensureStatRows($sorted, $mallId);
        $parent = (int)end($chain);
        // 新链每个人:团队人数、团队等级分布、团队消费加上这一队
        $set = 'team_count = team_count + ' . (int)$team['size'] . ', dirty = 1';
        if ($team['levels']) {
            $set .= ', team_level_map = ' . self::sqlMapShift('team_level_map', $team['levels']);
        }
        $set .= self::sqlSpendShift($team, 1);
        self::execChainUpdate($chain, $set);
        // 新上级:他未注销时直推 +1、直推等级分布 +1
        if ($team['alive']) {
            $set = 'direct_count = direct_count + 1, dirty = 1';
            if ($team['level_id'] > 0) {
                $set .= ', direct_level_map = ' . self::sqlMapShift('direct_level_map', [$team['level_id'] => 1]);
            }
            self::execWithDeadlockRetry('UPDATE ' . UserTeamStat::tableName() . " SET {$set} WHERE user_id = {$parent}");
        }
        // 各线:新上级这条线就是这一队,人数、最高等级直接写准;再往上通往他的那条线人数加上这一队,最高等级只升。
        // 先加人数再抬等级:抬等级时补建的线不知道整条线多少人,按惯例记 0,不能把这一队的人数当成整条线的
        self::legStep($userId, '新上级加线', fn() => self::execWithDeadlockRetry('UPDATE ' . UserTeamStat::tableName()
            . ' SET team_legs_map = JSON_SET(' . self::sqlJsonObj('team_legs_map') . ", '$.\"{$userId}\"', JSON_OBJECT('count', "
            . (int)$team['size'] . ", 'max_level', " . (int)$team['max'] . ")), dirty = 1 WHERE user_id = {$parent}"));
        $legs = self::chainLegs($chain);
        self::legStep($userId, '新链线人数', fn() => self::shiftLegCountIn($legs, (int)$team['size']));
        if ($team['max'] > 0 && $legs) {
            $pairs = [];
            foreach ($legs as [$aid, $leg]) {
                $pairs[$aid] = $leg;
            }
            self::legStep($userId, '新链线最高等级', fn() => self::bumpLegMaxIn($pairs, (int)$team['max'], $mallId));
        }
    }

    /** 各直推线的一步:失败只记日志(巡检会按全量重算纠正),不影响已写好的人数和后面的步骤 */
    private static function legStep(int $userId, string $what, callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Yii::error("[TeamStat] onUserMoved {$what}失败 user={$userId}: " . $e->getMessage());
        }
    }

    /** 链上 [祖先, 他名下通往链尾那条直推线的线根] 两两一对(不含链尾本人那一对) */
    private static function chainLegs(array $chain): array
    {
        $out = [];
        for ($i = 0, $n = count($chain); $i < $n - 1; $i++) {
            $out[] = [(int)$chain[$i], (int)$chain[$i + 1]];
        }
        return $out;
    }

    /** 整条链同一个 SET,按主键升序、每 IN_CHUNK 人一条语句 */
    private static function execChainUpdate(array $ids, string $set): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        sort($ids);
        foreach (array_chunk($ids, self::IN_CHUNK) as $part) {
            self::execWithDeadlockRetry('UPDATE ' . UserTeamStat::tableName() . " SET {$set} WHERE user_id IN (" . implode(',', $part) . ')');
        }
    }

    /**
     * 各 (祖先, 线根) 这条线的 count 加减 $delta(不低于 0),max_level 原样保留、标脏;库里没有这条线的不动。
     * 每条线不同,用派生表关联更新,每 LEG_CHUNK 对一条 SQL。
     */
    private static function shiftLegCountIn(array $pairs, int $delta): void
    {
        if (!$pairs || $delta === 0) {
            return;
        }
        usort($pairs, fn($a, $b) => $a[0] <=> $b[0]);   // 按祖先主键升序,与其它批量更新同序
        $m = self::sqlJsonObj('s.team_legs_map');
        $p = "CONCAT('$.\"', v.leg, '\"')";
        $cnt = self::sqlJsonInt($m, "CONCAT('$.\"', v.leg, '\".count')");
        $max = "IFNULL(JSON_EXTRACT({$m}, CONCAT('$.\"', v.leg, '\".max_level')), CAST(0 AS JSON))";
        $isObj = "(JSON_TYPE(JSON_EXTRACT({$m}, {$p})) = 'OBJECT')";
        foreach (array_chunk($pairs, self::LEG_CHUNK) as $part) {
            $rows = [];
            foreach ($part as [$aid, $leg]) {
                $rows[] = 'SELECT ' . (int)$aid . ' AS uid, ' . (int)$leg . ' AS leg';
            }
            self::execWithDeadlockRetry(
                'UPDATE ' . UserTeamStat::tableName() . ' s JOIN (' . implode(' UNION ALL ', $rows) . ') v ON s.user_id = v.uid'
                . " SET s.team_legs_map = JSON_SET({$m}, {$p}, JSON_OBJECT('count', GREATEST({$cnt} + ({$delta}), 0), 'max_level', {$max})), s.dirty = 1"
                . " WHERE {$isObj}"
            );
        }
    }

    /**
     * map 类 JSON 列按 [键 => 增减] 原地加减的 SQL 表达式;减到 ≤0 删键(与 bumpTeamLevelMapIn 同一写法)。
     * 例:sqlMapShift('team_level_map', [3 => -2, 5 => -1])
     */
    private static function sqlMapShift(string $col, array $deltas): string
    {
        $m = self::sqlJsonObj($col);
        $patch = [];
        foreach ($deltas as $k => $d) {
            $k = (int)$k;
            $d = (int)$d;
            if ($k <= 0 || $d === 0) {
                continue;
            }
            $x = self::sqlJsonInt($m, "'$.\"{$k}\"'");
            $patch[] = "'{$k}', IF({$x} + ({$d}) <= 0, NULL, {$x} + ({$d}))";   // null → JSON_MERGE_PATCH 删键
        }
        return $patch ? "JSON_MERGE_PATCH({$m}, JSON_OBJECT(" . implode(', ', $patch) . '))' : $col;
    }

    /**
     * 订单支付时调用
     * 更新: 个人消费+订单数+已购商品 + 沿祖先链团队消费 + 月度
     */
    public static function onOrderPaid(Order $order): void
    {
        try {
            $userId = $order->user_id;
            $mallId = $order->mall_id;
            // 按商品实付记:这张单里该记的明细 pay_price 合计,运费不算(口径见 EffectiveSpend::accounted,与 fullRebuild 一致)。
            // 原来记整单实付,含运费;巡检按明细重算又不含,两边一来一回改一次数。
            // 老路径关掉的单(已关闭、没回滚)一条都不记,巡检也不算它;正常取消的照记,回滚落地再扣
            $details = OrderDetail::find()->alias('od')
                ->innerJoin(Order::tableName() . ' o', 'o.id = od.order_id')
                ->where(['od.order_id' => (int)$order->id])
                ->andWhere(EffectiveSpend::accounted((int)$mallId, false, 'o', 'od', (int)$order->id))
                ->all();
            if (!$details) {
                return;
            }
            $payPrice = round(array_sum(array_map(fn($d) => (float)$d->pay_price, $details)), 2);
            $currentMonth = date('Ym');
            $db = Yii::$app->db;

            // 确保记录存在
            UserTeamStat::getOrCreate($userId, $mallId);

            // 1. 更新本人（行锁保护 purchased_goods_map 的读改写）
            $transaction = $db->beginTransaction();
            try {
                $row = $db->createCommand(
                    "SELECT purchased_goods_map, month_period FROM {{%user_team_stat}} WHERE user_id = :uid FOR UPDATE"
                )->bindValue(':uid', $userId)->queryOne();

                // 已购商品数量（锁内读改写）
                $goodsMap = !empty($row['purchased_goods_map']) ? Json::decode($row['purchased_goods_map']) : [];
                foreach ($details as $detail) {
                    $gid = (string)$detail->goods_id;
                    $goodsMap[$gid] = ($goodsMap[$gid] ?? 0) + $detail->num;
                }

                // 月度处理
                $isNewMonth = ($row['month_period'] ?? '') !== $currentMonth;
                if ($isNewMonth) {
                    $monthSql = "month_period = :month, month_personal_spend = :pay, month_team_spend = :pay2, month_active_count = 0";
                } else {
                    $monthSql = "month_personal_spend = month_personal_spend + :pay, month_team_spend = month_team_spend + :pay2";
                }

                // team_spend_paid 也给本人 +：团队业绩口径【含本人消费】(与 fullRebuild 一致)。
                // 注意:PDO 真实预处理下同名占位符不可复用,故 team 用独立 :amount_t(与原 :pay/:pay2 同理)。
                $sql = "UPDATE {{%user_team_stat}} SET
                    personal_spend_paid = personal_spend_paid + :amount,
                    team_spend_paid = team_spend_paid + :amount_t,
                    personal_order_count = personal_order_count + 1,
                    purchased_goods_map = :gmap,
                    {$monthSql},
                    dirty = 1
                    WHERE user_id = :uid2";

                $params = [
                    ':amount' => $payPrice,
                    ':amount_t' => $payPrice,
                    ':gmap' => Json::encode($goodsMap),
                    ':pay' => $payPrice,
                    ':pay2' => $payPrice,
                    ':uid2' => $userId,
                ];
                if ($isNewMonth) $params[':month'] = $currentMonth;

                $db->createCommand($sql, $params)->execute();
                $transaction->commit();
            } catch (\Exception $e) {
                $transaction->rollBack();
                throw $e;
            }

            // 2. 沿祖先链更新团队消费（原子操作，无需锁）
            self::updateStatIn(
                self::ancestorsOf($userId),
                'team_spend_paid = team_spend_paid + :amount,
                 month_team_spend = CASE WHEN month_period = :month THEN month_team_spend + :amount2 ELSE :amount3 END,
                 month_period = :month2,
                 dirty = 1',
                [
                    ':amount' => $payPrice, ':amount2' => $payPrice, ':amount3' => $payPrice,
                    ':month' => $currentMonth, ':month2' => $currentMonth,
                ],
                $db
            );
        } catch (\Exception $e) {
            Yii::error("[TeamStat] onOrderPaid 失败: " . $e->getMessage());
        }
    }

    /**
     * 订单结算时调用
     */
    public static function onOrderSettled(Order $order): void
    {
        try {
            $userId = $order->user_id;
            // 只记结算这一刻还算数的明细(口径见 EffectiveSpend):结算前已经退掉的不算、运费不算。
            // 原来按整单实付加,先退一件再结算时退掉那件也记成了已结算,巡检重算又不算它
            $payPrice = round((float)(OrderDetail::find()->alias('od')
                ->innerJoin(Order::tableName() . ' o', 'o.id = od.order_id')
                ->where(['od.order_id' => (int)$order->id])
                ->andWhere(EffectiveSpend::accounted((int)$order->mall_id, true, 'o', 'od', (int)$order->id))
                ->sum('od.pay_price') ?: 0), 2);
            if ($payPrice <= 0) {
                return;
            }
            $payPrice = number_format($payPrice, 2, '.', '');

            // 本人结算消费;team_spend_settled 也给本人 +(团队业绩含本人)
            Yii::$app->db->createCommand()->update(
                UserTeamStat::tableName(),
                [
                    'personal_spend_settled' => new \yii\db\Expression("personal_spend_settled + {$payPrice}"),
                    'team_spend_settled' => new \yii\db\Expression("team_spend_settled + {$payPrice}"),
                ],
                ['user_id' => $userId]
            )->execute();

            // 祖先链团队结算消费
            self::updateStatIn(
                self::ancestorsOf($userId),
                'team_spend_settled = team_spend_settled + :paid',
                [':paid' => $payPrice]
            );
        } catch (\Exception $e) {
            Yii::error("[TeamStat] onOrderSettled 失败: " . $e->getMessage());
        }
    }

    /**
     * 用户等级变化时调用
     * 更新祖先链的 direct_level_map / team_level_map / team_legs_map + 标脏
     */
    public static function onLevelChanged(int $userId, int $mallId, $oldLevelId, $newLevelId): void
    {
        try {
            $user = User::findOne($userId);
            if (!$user || !$user->parent_id) return;

            $parentId = $user->parent_id;

            // 1. 直接上级: 更新 direct_level_map（行锁保护，防并发丢失）
            self::atomicUpdateMapField($parentId, 'direct_level_map', $oldLevelId, $newLevelId, true);

            // 2. 所有祖先: 更新 team_level_map + 标脏（全链,不封顶;每 IN_CHUNK 人一条 SQL,分批不截断）
            $ancestors = self::ancestorsOf($userId);
            self::bumpTeamLevelMapIn($ancestors, $oldLevelId, $newLevelId);

            // 3. 全部祖先: 传播"本用户所在直推线的最高等级"到各级 team_legs_map。
            //    （type10 多线并行考核读 team_legs_map[线].max_level；深层下级升级必须冒泡到上面每一级，
            //     否则高层祖先的"某条线含X等级"判定会漏。仅刷 max_level，不动 count（等级变化不改人数）。）
            $newWeight = self::levelWeight($newLevelId);
            $oldWeight = self::levelWeight($oldLevelId);

            $path = UserService::getVerifiedPath($userId);   // 同 ancestorsOf:读现成 path(核对过),不逐层上溯
            $ids = array_values(array_filter(array_map('intval', explode('-', trim((string)$path, '-')))));
            $n = count($ids);
            // 冒泡到每一级祖先,不封顶。原来是 max(0, $n - 1 - 50),方向虽对但同样砍到 50 级:
            // 链深 ≥101 时它与上面 team_level_map 那段(原先砍根侧 50)覆盖的祖先完全不相交,
            // 中间那一大段祖先两套口径都够不着,type10 多线并行考核长期漏判。
            $pairs = [];   // [祖先 => 该祖先名下、通往本用户的那条直推线根]
            for ($i = $n - 2; $i >= 0; $i--) {
                $pairs[$ids[$i]] = $ids[$i + 1];
            }
            if ($newWeight >= $oldWeight) {
                self::bumpLegMaxIn($pairs, $newWeight, $mallId);   // 升级:只升,批量
            } else {
                self::lowerLegMaxIn($ids, $mallId, [], [$userId => $newWeight]);   // 降级(少见):自底向上重算,线里还有人撑着这个等级就停
            }
        } catch (\Exception $e) {
            Yii::error("[TeamStat] onLevelChanged 失败: " . $e->getMessage());
        }
    }

    /** 每条 SQL 里放多少对「祖先 → 直推线根」 */
    private const LEG_CHUNK = 500;

    /**
     * 批量版 atomicUpdateMapField($id, 'team_level_map', $old, $new, true):所有祖先同一对 (旧等级 -1, 新等级 +1)。
     *
     * 原来每个祖先一个事务(锁行 → PHP 读改写 → 写回),千层链几千条 SQL。改成在 MySQL 里原地改 JSON,
     * 每 IN_CHUNK 人一条 UPDATE。单条语句本身原子,加锁方式与 updateStatIn 相同(IN 列表、按主键升序),
     * 不引入新的死锁模式;也不再长时间持有上千行锁。
     * 语义与原来逐个处理一致:没有 stat 记录的祖先跳过;旧等级减到 ≤0 就删掉该键;新旧相同时净不变(缺键时补成 1)。
     */
    private static function bumpTeamLevelMapIn(array $ids, $oldKey, $newKey): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return;
        }
        sort($ids);   // 按主键升序加锁,与其它批量更新同序

        $o = (int)$oldKey;
        $nk = (int)$newKey;
        $m = self::sqlJsonObj('team_level_map');
        $patch = [];
        if ($o > 0 && $nk > 0 && $o === $nk) {
            // 先减后加:≥1 的不变;缺键 / 0 → 1
            $patch[] = "'{$nk}', GREATEST(" . self::sqlJsonInt($m, "'$.\"{$nk}\"'") . ", 1)";
        } else {
            if ($o > 0) {
                $xo = self::sqlJsonInt($m, "'$.\"{$o}\"'");
                $patch[] = "'{$o}', IF({$xo} <= 1, NULL, {$xo} - 1)";   // null → JSON_MERGE_PATCH 删键
            }
            if ($nk > 0) {
                $patch[] = "'{$nk}', " . self::sqlJsonInt($m, "'$.\"{$nk}\"'") . " + 1";
            }
        }
        $set = $patch
            ? "team_level_map = JSON_MERGE_PATCH({$m}, JSON_OBJECT(" . implode(', ', $patch) . ")), dirty = 1"
            : 'dirty = 1';

        foreach (array_chunk($ids, self::IN_CHUNK) as $part) {
            self::execWithDeadlockRetry(
                'UPDATE ' . UserTeamStat::tableName() . " SET {$set} WHERE user_id IN (" . implode(',', $part) . ')'
            );
        }
    }

    /**
     * 批量版 bumpLegMax:每个祖先把「通往本用户那条直推线」的 max_level 抬到 $newWeight(只升不降)。
     *
     * 与原逐个处理一致:先给缺 stat 记录的祖先补建;该线条目不存在、或现有 max_level 低于新权重才写
     * (写成 {count: 原 count, max_level: 两者较大值} 并标脏),否则不动。
     * 每条线不同,用 (祖先, 线根) 派生表关联更新,每 LEG_CHUNK 对一条 SQL,分批不截断。
     */
    private static function bumpLegMaxIn(array $pairs, int $newWeight, int $mallId): void
    {
        if (!$pairs) {
            return;
        }
        ksort($pairs);   // 按祖先主键升序
        self::ensureStatRows(array_keys($pairs), $mallId);

        $m = self::sqlJsonObj('s.team_legs_map');
        $p = "CONCAT('$.\"', v.leg, '\"')";
        $cur = self::sqlJsonInt($m, "CONCAT('$.\"', v.leg, '\".max_level')");
        $isObj = "(JSON_TYPE(JSON_EXTRACT({$m}, {$p})) = 'OBJECT')";
        $cnt = "IF({$isObj}, IFNULL(JSON_EXTRACT({$m}, CONCAT('$.\"', v.leg, '\".count')), CAST(0 AS JSON)), CAST(0 AS JSON))";
        $w = (int)$newWeight;

        foreach (array_chunk($pairs, self::LEG_CHUNK, true) as $part) {
            $rows = [];
            foreach ($part as $aid => $leg) {
                $rows[] = 'SELECT ' . (int)$aid . ' AS uid, ' . (int)$leg . ' AS leg';
            }
            self::execWithDeadlockRetry(
                'UPDATE ' . UserTeamStat::tableName() . ' s JOIN (' . implode(' UNION ALL ', $rows) . ') v ON s.user_id = v.uid'
                . " SET s.team_legs_map = JSON_SET({$m}, {$p}, JSON_OBJECT('count', {$cnt}, 'max_level', GREATEST({$cur}, {$w}))), s.dirty = 1"
                . " WHERE ({$w} > {$cur} OR JSON_CONTAINS_PATH({$m}, 'one', {$p}) = 0)"
            );
        }
    }

    /**
     * 降级:按实际重算本用户各级祖先「通往本用户那条直推线」的 max_level。
     *
     * 自底向上一条线一条线算,算出来和库里存的一样就停 —— 这条线里还有别人撑着这个等级,
     * 更上面的线都包含这条线,最高等级也都不会变(线根已注销的线除外,它存的值不可信,见循环里)。原来是每个祖先都重算一遍、每遍都全表扫
     * (本地馨薇妍库 174 层链降一级 32 秒、1200 层 250 秒),现在通常算一两条线就停,每条线走 path 前缀索引。
     * 最坏情况(本用户是整条链上唯一的这个等级)也只是每条线一次索引范围查询。
     *
     * 线里的人按统计里记着的等级算(pendingLevelWeights):「算出来和库里一样就停」的前提是库里存的就是这个口径,
     * 按 user 表现在的等级算的话,改等级事件还在排队的人会让这里和库里差一截,停错地方。
     *
     * @param int[]    $ids          本用户的 path(根 → 本人),各祖先的 path 就是它的前缀
     * @param string[] $excludeLikes 重算时不算这些 path 前缀下的人(改上级时从旧链挪走的那一队)
     * @param array    $override     [uid => 权重]:正在落地的改等级事件,本人按事件改后的等级算
     */
    private static function lowerLegMaxIn(array $ids, int $mallId, array $excludeLikes = [], array $override = []): void
    {
        $n = count($ids);
        if ($n < 2) {
            return;
        }
        $pending = self::pendingLevelWeights($mallId, $override);
        // 链上每人自己的等级(线根本人要算进这条线;已注销 / 不在本商城的不算,与 legMaxLevel 同口径)
        $levels = User::find()->select(['level', 'id'])
            ->where(['id' => $ids, 'is_delete' => 0, 'mall_id' => $mallId])
            ->indexBy('id')->column();
        foreach ($levels as $id => $lv) {
            if (isset($pending[$id])) {
                $levels[$id] = $pending[$id]['w'];
            }
        }
        // 各祖先现在存的 team_legs_map,一次读出来比对
        $maps = UserTeamStat::find()->select(['team_legs_map', 'user_id'])
            ->where(['user_id' => array_slice($ids, 0, -1)])
            ->indexBy('user_id')->column();

        $changes = [];
        for ($i = $n - 2; $i >= 0; $i--) {
            $legRoot = $ids[$i + 1];
            $legLike = '-' . implode('-', array_slice($ids, 0, $i + 2)) . '-%';
            $max = self::legMaxLevel($legLike, $mallId, (int)($levels[$legRoot] ?? 0), $excludeLikes, $pending);
            $stored = self::storedLegMax($maps[$ids[$i]] ?? null, $legRoot);
            if ($stored === $max) {
                if (isset($levels[$legRoot])) {
                    break;
                }
                // 线根已注销:这条线全量重建不维护(只算未注销的直推),库里有也是下级升级时顺手补的、只升不降,
                // 存的值不可信,拿它断定「上面的线不变」会让上面的线停在旧的高等级上(2026-09 随机压测撞到)。接着往上算
                continue;
            }
            $changes[] = [$ids[$i], $legRoot, $max, $stored ?? -1];
        }
        if ($changes) {
            self::setLegMaxIn($changes, $mallId);
        }
    }

    /**
     * 批量把 (祖先, 线根) 的 max_level 写成给定值,count 保留原值、标脏。
     * 只在库里仍是当初读到的值时才写(-1 = 当时没有这条线):读完到写之间别人升级抬高了这条线,
     * 不会被这次按旧数据算出来的值压回去 —— 原来的逐个重算有这个窗口。
     *
     * @param array $changes [[祖先, 线根, 新值, 读到的旧值], ...]
     */
    private static function setLegMaxIn(array $changes, int $mallId): void
    {
        usort($changes, fn($a, $b) => $a[0] <=> $b[0]);   // 按祖先主键升序,与 bumpLegMaxIn 同序
        self::ensureStatRows(array_values(array_unique(array_column($changes, 0))), $mallId);

        $m = self::sqlJsonObj('s.team_legs_map');
        $p = "CONCAT('$.\"', v.leg, '\"')";
        $cur = self::sqlJsonInt($m, "CONCAT('$.\"', v.leg, '\".max_level')");
        $has = "JSON_CONTAINS_PATH({$m}, 'one', {$p})";
        $isObj = "(JSON_TYPE(JSON_EXTRACT({$m}, {$p})) = 'OBJECT')";
        $cnt = "IF({$isObj}, IFNULL(JSON_EXTRACT({$m}, CONCAT('$.\"', v.leg, '\".count')), CAST(0 AS JSON)), CAST(0 AS JSON))";

        foreach (array_chunk($changes, self::LEG_CHUNK) as $part) {
            $rows = [];
            foreach ($part as [$aid, $leg, $w, $expect]) {
                $rows[] = 'SELECT ' . (int)$aid . ' AS uid, ' . (int)$leg . ' AS leg, ' . (int)$w . ' AS w, ' . (int)$expect . ' AS expect';
            }
            self::execWithDeadlockRetry(
                'UPDATE ' . UserTeamStat::tableName() . ' s JOIN (' . implode(' UNION ALL ', $rows) . ') v ON s.user_id = v.uid'
                . " SET s.team_legs_map = JSON_SET({$m}, {$p}, JSON_OBJECT('count', {$cnt}, 'max_level', v.w)), s.dirty = 1"
                . " WHERE (v.expect = -1 AND {$has} = 0) OR (v.expect >= 0 AND {$has} = 1 AND {$cur} = v.expect)"
            );
        }
    }

    /**
     * 一条直推线(线根本人 + 全部子孙)里未注销用户的最高等级权重。
     *
     * 口径与原来完全一样(线根本人或 path 以线根 path 为前缀、is_delete=0、同商城、拉黑的照算),只换查法:
     * 原来 `u.id = 线根 OR p.path LIKE 前缀` 这个跨两张表的 OR 让 path 索引用不上,
     * 每条线都按商城把全部用户扫一遍(本地馨薇妍库 4.9 万行;直推 468 人的全量重建一次 87 秒)。
     * 现在子孙从 user_path 走 idx-user_path-path 前缀范围,线根本人的等级由调用方传入(他的 path 行缺失也算得上)。
     *
     * @param string   $legLike      线根自己的 path + '%'(UserService::subtreeLike 的格式;path 缺失时的包含匹配也照收)
     * @param int      $rootLevel    线根本人的等级权重(统计里记着的);已注销 / 不在本商城的传 0
     * @param string[] $excludeLikes 不算这些 path 前缀下的人
     * @param array    $pending      pendingLevelWeights():这些人按统计里记着的等级算,不按 user 表现在的
     */
    private static function legMaxLevel(string $legLike, int $mallId, int $rootLevel, array $excludeLikes = [], array $pending = []): int
    {
        $q = UserPath::find()->alias('p')
            ->innerJoin(User::tableName() . ' u', 'u.id = p.user_id')
            ->where(['like', 'p.path', $legLike, false])
            ->andWhere(['u.is_delete' => 0, 'u.mall_id' => $mallId]);
        foreach ($excludeLikes as $ex) {
            $q->andWhere(['not like', 'p.path', $ex, false]);
        }
        if ($pending) {
            $q->andWhere(['not in', 'u.id', array_keys($pending)]);
        }
        $max = max((int)($q->max('u.level') ?: 0), $rootLevel);
        foreach ($pending as $x) {
            if (self::pathLike($x['path'], $legLike) && !array_filter($excludeLikes, fn($ex) => self::pathLike($x['path'], $ex))) {
                $max = max($max, $x['w']);
            }
        }
        return $max;
    }

    /** PHP 里判断 path 是否满足 legMaxLevel 用的 LIKE 串:'-a-b-%' 前缀,或 '%-x-%' 包含 */
    private static function pathLike(string $path, string $like): bool
    {
        if ($path === '') {
            return false;
        }
        $core = trim($like, '%');
        return $like[0] === '%' ? strpos($path, $core) !== false : strpos($path, $core) === 0;
    }

    /**
     * 改等级事件还在排队的人 → 统计里记着的等级权重和 path(各线最高等级用,口径同 TeamStatOutbox::accountedLevelIds)。
     * $override = [uid => 权重]:正在落地的那条事件,本人按事件改后的等级算。只含未注销、本商城的人。
     *
     * @return array uid => ['w' => 权重, 'path' => path]
     */
    private static function pendingLevelWeights(int $mallId, array $override = []): array
    {
        $accounted = TeamStatOutbox::accountedLevelIds($mallId);
        if (!$accounted && !$override) {
            return [];
        }
        $weights = \app\models\Level::find()->select(['level', 'id'])->where(['mall_id' => $mallId])->indexBy('id')->column();
        $rows = User::find()->alias('u')
            ->leftJoin(UserPath::tableName() . ' p', 'p.user_id = u.id')
            ->select(['u.id', 'p.path'])
            ->where(['u.id' => array_keys($accounted + $override), 'u.is_delete' => 0, 'u.mall_id' => $mallId])
            ->asArray()->all();
        $out = [];
        foreach ($rows as $r) {
            $uid = (int)$r['id'];
            $w = array_key_exists($uid, $override) ? (int)$override[$uid] : (int)($weights[$accounted[$uid]] ?? 0);
            $out[$uid] = ['w' => $w, 'path' => (string)$r['path']];
        }
        return $out;
    }

    /**
     * 库里某祖先 team_legs_map 中这条线的 max_level,取值规则与 sqlJsonInt 一致(setLegMaxIn 按它比对)。
     * 没有这条线(含整行没有、空 / 坏 JSON、不是对象)返回 null;有这条线但 max_level 不是整数按 0。
     */
    private static function storedLegMax($json, int $legRoot): ?int
    {
        $map = json_decode((string)$json, true);
        if (!is_array($map) || !array_key_exists((string)$legRoot, $map)) {
            return null;
        }
        $v = is_array($map[(string)$legRoot]) ? ($map[(string)$legRoot]['max_level'] ?? null) : null;
        if (is_int($v)) {
            return $v;
        }
        if (is_float($v)) {
            return (int)round($v);   // SQL 的 CAST(... AS SIGNED) 是四舍五入
        }
        return is_string($v) && preg_match('/^-?[0-9]+$/', $v) ? (int)$v : 0;
    }

    /**
     * 批量版 UserTeamStat::getOrCreate:缺的补建,已有的不动。
     * 先查哪些缺、只对缺的 INSERT IGNORE:对已有行做 INSERT IGNORE 会给它加共享锁,调用方在外层事务里接着 UPDATE 这些行时,
     * 两个事务同时对同一祖先(比如根)共享锁升排他锁就互相死锁(emotion 核销在自己的事务里绑上级即如此)。
     */
    private static function ensureStatRows(array $ids, int $mallId): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return;
        }
        $have = [];
        foreach (array_chunk($ids, self::IN_CHUNK) as $part) {
            $have = array_merge($have, array_map('intval', UserTeamStat::find()->select('user_id')->where(['user_id' => $part])->column()));
        }
        $missing = array_values(array_diff($ids, $have));
        if (!$missing) {
            return;
        }
        sort($missing);
        $now = time();
        $nowStr = date('Y-m-d H:i:s');
        foreach (array_chunk($missing, self::LEG_CHUNK) as $part) {
            $values = [];
            foreach ($part as $uid) {
                $values[] = '(' . (int)$uid . ", {$mallId}, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, {$now}, {$now}, 0, '{$nowStr}', '{$nowStr}', NULL)";
            }
            self::execWithDeadlockRetry(
                "INSERT IGNORE INTO {{%user_team_stat}} (user_id, mall_id, direct_count, team_count, personal_spend_paid, personal_spend_settled, personal_order_count, team_spend_paid, team_spend_settled, month_personal_spend, month_team_spend, month_active_count, month_period, dirty, last_check_at, is_delete, created_at, updated_at, deleted_at, created_time, updated_time, deleted_time) VALUES "
                . implode(', ', $values)
            );
        }
    }

    /**
     * 把 JSON 文本列规整成对象:NULL / 空串 / "[]"(PHP 编码空数组的结果,库里绝大多数是它)/ 坏 JSON → '{}'。
     * ⛔ 不能省:对 "[]" 直接 JSON_SET 一个键,MySQL 原样返回、不报错,这次加减就静默丢了。
     * CASE 是惰性求值,坏 JSON 不会走到 JSON_TYPE(那里会报错)。
     */
    private static function sqlJsonObj(string $col): string
    {
        return "(CASE WHEN JSON_VALID({$col}) = 1 THEN (CASE WHEN JSON_TYPE({$col}) = 'OBJECT' THEN {$col} ELSE '{}' END) ELSE '{}' END)";
    }

    /**
     * JSON 文档 $doc 在 $path 处的整数;不存在 / 非数字按 0。
     * ⛔ 不能直接 JSON_EXTRACT(...) + 1:会写成 4.0;字符串 "abc" 在严格模式下 CAST 直接报错、整批失败。
     */
    private static function sqlJsonInt(string $doc, string $path): string
    {
        $v = "JSON_EXTRACT({$doc}, {$path})";
        return "COALESCE(CASE JSON_TYPE({$v})"
            . " WHEN 'INTEGER' THEN CAST({$v} AS SIGNED) WHEN 'UNSIGNED INTEGER' THEN CAST({$v} AS SIGNED)"
            . " WHEN 'DOUBLE' THEN CAST({$v} AS SIGNED) WHEN 'DECIMAL' THEN CAST({$v} AS SIGNED)"
            . " WHEN 'STRING' THEN IF(JSON_UNQUOTE({$v}) REGEXP '^-?[0-9]+$', CAST(JSON_UNQUOTE({$v}) AS SIGNED), 0)"
            . " END, 0)";
    }

    /**
     * 单条批量语句撞上死锁(1213)时重试,最多 3 次 —— 语句自身原子,重试安全。
     * ⛔ 只在事务外重试:死锁时 InnoDB 会回滚【整个】事务,在事务里重试等于把剩下的语句挪到事务外执行,
     *    外层再提交就只落了一半(EventOutboxTask 就是在事务里调 onLevelChanged 的,处理 + ack 同事务)。
     *    事务内直接抛出,交给外层回滚。
     */
    private static function execWithDeadlockRetry(string $sql): void
    {
        $db = Yii::$app->db;
        for ($try = 1; ; $try++) {
            try {
                $db->createCommand($sql)->execute();
                return;
            } catch (\yii\db\Exception $e) {
                $tx = $db->getTransaction();
                if ($try >= 3 || (int)($e->errorInfo[1] ?? 0) !== 1213 || ($tx && $tx->getIsActive())) {
                    throw $e;
                }
                usleep(20000 * $try);
            }
        }
    }

    /**
     * 取等级权重（level 字段）；0/不存在返回 0
     */
    private static function levelWeight($levelId): int
    {
        $levelId = (int)$levelId;
        if ($levelId <= 0) return 0;
        $lv = \app\models\Level::findOne($levelId);
        return $lv ? (int)$lv->level : 0;
    }

    /**
     * 升级传播：把某祖先 team_legs_map[$legRoot] 的 max_level 提升到 >= $newWeight（只升不降，不查子树）。
     * 行锁防并发；该线不存在则按 max_level=$newWeight 占位（count 待 bind/全量重算修正）。
     */
    public static function bumpLegMax(int $ancestorId, int $legRoot, int $newWeight, int $mallId): void
    {
        UserTeamStat::getOrCreate($ancestorId, $mallId);
        $db = Yii::$app->db;
        $tx = $db->beginTransaction();
        try {
            $row = $db->createCommand("SELECT team_legs_map FROM {{%user_team_stat}} WHERE user_id = :uid FOR UPDATE")
                ->bindValue(':uid', $ancestorId)->queryOne();
            if (!$row) { $tx->commit(); return; }
            $map = !empty($row['team_legs_map']) ? Json::decode($row['team_legs_map']) : [];
            $k = (string)$legRoot;
            $cur = isset($map[$k]['max_level']) ? (int)$map[$k]['max_level'] : 0;
            if ($newWeight > $cur || !isset($map[$k])) {
                $map[$k] = [
                    'count'     => $map[$k]['count'] ?? 0,
                    'max_level' => max($cur, $newWeight),
                ];
                $db->createCommand()->update(UserTeamStat::tableName(),
                    ['team_legs_map' => Json::encode($map), 'dirty' => 1], ['user_id' => $ancestorId])->execute();
            }
            $tx->commit();
        } catch (\Exception $e) {
            $tx->rollBack();
            throw $e;
        }
    }

    /**
     * 单线重算：重算某祖先名下 $legRoot 这条线的最高等级，行锁更新 team_legs_map[$legRoot]。
     * (onLevelChanged 的降级已改走 lowerLegMaxIn 批量处理,这里保留给单条线重算用)
     */
    public static function recomputeLegMax(int $ancestorId, int $legRoot, int $mallId): void
    {
        $pending = self::pendingLevelWeights($mallId);
        $rootLevel = (int)User::find()->select('level')
            ->where(['id' => $legRoot, 'is_delete' => 0, 'mall_id' => $mallId])->scalar();
        if (isset($pending[$legRoot])) {
            $rootLevel = $pending[$legRoot]['w'];
        }
        $maxLevel = self::legMaxLevel(self::subtreeLike($legRoot), $mallId, $rootLevel, [], $pending);

        UserTeamStat::getOrCreate($ancestorId, $mallId);
        $db = Yii::$app->db;
        $tx = $db->beginTransaction();
        try {
            $row = $db->createCommand("SELECT team_legs_map FROM {{%user_team_stat}} WHERE user_id = :uid FOR UPDATE")
                ->bindValue(':uid', $ancestorId)->queryOne();
            if (!$row) { $tx->commit(); return; }
            $map = !empty($row['team_legs_map']) ? Json::decode($row['team_legs_map']) : [];
            $k = (string)$legRoot;
            $map[$k] = ['count' => $map[$k]['count'] ?? 0, 'max_level' => $maxLevel];
            $db->createCommand()->update(UserTeamStat::tableName(),
                ['team_legs_map' => Json::encode($map), 'dirty' => 1], ['user_id' => $ancestorId])->execute();
            $tx->commit();
        } catch (\Exception $e) {
            $tx->rollBack();
            throw $e;
        }
    }

    /**
     * 原子更新 JSON Map 字段（SELECT FOR UPDATE 锁行，防止并发读改写丢失）
     */
    private static function atomicUpdateMapField(int $userId, string $field, $oldKey, $newKey, bool $markDirty = false): void
    {
        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();
        try {
            // 行锁读取
            $row = $db->createCommand(
                "SELECT {$field} FROM {{%user_team_stat}} WHERE user_id = :uid FOR UPDATE"
            )->bindValue(':uid', $userId)->queryOne();

            if (!$row) {
                $transaction->commit();
                return;
            }

            $map = !empty($row[$field]) ? Json::decode($row[$field]) : [];

            if ($oldKey) {
                $k = (string)$oldKey;
                $map[$k] = max(0, ($map[$k] ?? 0) - 1);
                if ($map[$k] <= 0) unset($map[$k]);
            }
            if ($newKey) {
                $k = (string)$newKey;
                $map[$k] = ($map[$k] ?? 0) + 1;
            }

            $updates = [$field => Json::encode($map)];
            if ($markDirty) $updates['dirty'] = 1;

            $db->createCommand()->update(UserTeamStat::tableName(), $updates, ['user_id' => $userId])->execute();
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * 订单退款时调用（扣减消费）
     *
     * @deprecated 已被 onCommissionRevert(大一统 order.commission.revert)取代:
     *   本方法只按整单 pay_price 扣 paid 口径,漏了售后部分退/settled 口径/已购商品回退/幂等。
     *   现 TeamStatListener 不再监听 order.canceled,改听 commission.revert。保留仅为向后兼容,勿在新代码调用。
     */
    public static function onOrderRefund(Order $order): void
    {
        try {
            $userId = $order->user_id;
            $payPrice = (float)$order->pay_price;

            // 本人
            Yii::$app->db->createCommand()->update(
                UserTeamStat::tableName(),
                [
                    'personal_spend_paid' => new \yii\db\Expression("GREATEST(0, personal_spend_paid - {$payPrice})"),
                    'personal_order_count' => new \yii\db\Expression("GREATEST(0, personal_order_count - 1)"),
                    'dirty' => 1,
                ],
                ['user_id' => $userId]
            )->execute();

            // 祖先链
            self::updateStatIn(
                self::ancestorsOf($userId),
                'team_spend_paid = GREATEST(0, team_spend_paid - :paid), dirty = 1',
                [':paid' => $payPrice]
            );
        } catch (\Exception $e) {
            Yii::error("[TeamStat] onOrderRefund 失败: " . $e->getMessage());
        }
    }

    /**
     * 分润统一回滚信号(order.commission.revert)对团队/个人业绩统计的回滚。
     *
     * 大一统:取消(detail=0 整单)与售后退款(detail>0 明细)归一到这里,替代原先只听 order.canceled
     * 的 onOrderRefund —— 后者漏了「结算后售后退款」「部分退」「settled 口径」「已购商品数」,
     * 导致退款后升级判定基数虚高、把不该升的人(本人/上级链)误升。
     *
     * 口径与 onOrderPaid/onOrderSettled 的累加严格镜像:
     *   · personal_spend_paid / team_spend_paid(祖先链)恒回滚(paid 在支付时已计提)
     *   · personal_spend_settled / team_spend_settled 仅订单已结算时回滚(看回滚落地那一刻的结算状态;
     *     结算前退掉的明细,结算时 onOrderSettled 本就不记)
     *   · purchased_goods_map 按明细 num 回退
     *   · personal_order_count:这张单最后一条算数的明细被回滚时 -1(整单取消、逐条退光都算),
     *     与 fullRebuild 的「有效订单数」同口径
     *   · 月度字段不回滚(周期性重置,避免跨月退款污染本月计数,与原 onOrderRefund 一致)
     *
     * 幂等:user_team_stat_revert_log 按 (order_id, order_detail_id) 唯一锚点,重复信号
     * (Go 重试/重复同意)直接跳过;锚点与扣减在同一事务,中途失败可安全重试。
     * 约定:一条售后单 = 该明细整体回滚(与佣金 revert 同口径),同一明细多次部分退按首次整体回滚处理。
     */
    public static function onCommissionRevert(int $mallId, int $orderId, int $orderDetailId, string $source): void
    {
        if ($orderId <= 0) return;
        try {
            $order = Order::findOne($orderId);
            if (!$order) return;
            $userId = (int)$order->user_id;
            if ($userId <= 0) return;

            $isWholeOrder = ($orderDetailId <= 0);

            // 只回滚"当初确实计入过统计"的桶,杜绝误扣同用户/上级链其它订单的业绩:
            //  - paid 桶(spend_paid / order_count / goods / team_spend_paid):仅当订单确实支付过才回滚。
            //    取消未支付单 → pay_status 仍 UNPAID → 从未经 onOrderPaid 计入 → 整笔跳过(下方 !$wasPaid 提前返回)。
            //  - settled 桶(spend_settled / team_spend_settled):仅当这张单的已结算已经记进统计才回滚。
            //    未结算订单的售后 → settled 从未经 onOrderSettled 计入(原先 source==='refund' 即恒扣是错的);
            //    订单已标结算、但结算事件还在 outbox 排队 → 也还没记,不能扣:结算落地时只记还算数的明细,本条已退就不会记
            //    (只看订单结算状态的话,售后同意紧挨着结算时会扣一笔从没加过的已结算)。
            $wasPaid = ((int)$order->pay_status !== Order::PAY_STATUS_UNPAID);
            $deductSettled = $wasPaid && (int)$order->settlement_status === Order::SETTLEMENT_STATUS_SETTLED
                && !EffectiveSpend::hasPending($mallId, 'order_settled', $orderId);

            // 1) 计算回滚金额 + 收集要回退的已购商品(明细 num),全在事务外完成
            $goodsDelta = []; // goods_id(string) => num
            $paidAmount = 0.0;
            if ($wasPaid) {
                if ($isWholeOrder) {
                    // 排除已被"明细级回滚"扣过的明细:整单锚点 (order,0) 与明细锚点 (order,detailX) key 不同,
                    // 不排除会对"先部分退某明细、再整单取消"场景里的该明细二次回滚。
                    $revertedIds = Yii::$app->db->createCommand(
                        "SELECT order_detail_id FROM {{%user_team_stat_revert_log}} WHERE order_id = :oid AND order_detail_id > 0",
                        [':oid' => $orderId]
                    )->queryColumn();
                    $revertedSet = array_flip(array_map('intval', $revertedIds));
                    foreach ($order->orderDetails as $detail) {
                        if (isset($revertedSet[(int)$detail->id]) || (int)$detail->is_delete !== 0) {
                            continue; // 该明细已单独回滚过 / 已删(付款时就没记),跳过
                        }
                        $paidAmount += (float)($detail->pay_price ?? $detail->total_price ?? 0);
                        $gid = (string)$detail->goods_id;
                        $goodsDelta[$gid] = ($goodsDelta[$gid] ?? 0) + (int)$detail->num;
                    }
                } else {
                    $detail = OrderDetail::findOne($orderDetailId);
                    if (!$detail || (int)$detail->order_id !== $orderId) return;
                    // 明细实付(扣完优惠);付款时按明细合计记的,这里扣同一笔。已删明细付款时就没记
                    if ((int)$detail->is_delete === 0) {
                        $paidAmount = (float)($detail->pay_price ?? $detail->total_price ?? 0);
                        $goodsDelta[(string)$detail->goods_id] = (int)$detail->num;
                    }
                }
            }
            $settledAmount = $deductSettled ? $paidAmount : 0.0;

            // 祖先链(事务外解析,避免 ensurePath 的潜在写与扣减事务交叉)
            $ancestors = self::ancestorsOf($userId);

            $db = Yii::$app->db;
            $tx = $db->beginTransaction();
            try {
                // 有效订单数用:这张单还有几条记着的明细。本条回滚按「还在排队」算 —— 取消是先关单、提交后才发回滚,
                // 没开 outbox 时回滚当场处理,不这么算的话这一刻订单已是已关闭,会以为它早就不算了
                $countedLeft = fn(): int => (int)OrderDetail::find()->alias('od')
                    ->innerJoin(Order::tableName() . ' o', 'o.id = od.order_id')
                    ->where(['od.order_id' => $orderId])
                    ->andWhere(EffectiveSpend::accounted($mallId, false, 'o', 'od', null, [$orderId, $orderDetailId]))
                    ->count();
                $countedBefore = $wasPaid ? $countedLeft() : 0;

                // 2) 幂等锚点:同一 (订单,明细) 只回滚一次(并发由唯一索引串行化)
                $inserted = $db->createCommand(
                    "INSERT IGNORE INTO {{%user_team_stat_revert_log}} (mall_id, order_id, order_detail_id, source, created_at) VALUES (:m, :o, :d, :s, :t)",
                    [':m' => $mallId, ':o' => $orderId, ':d' => $orderDetailId, ':s' => $source, ':t' => time()]
                )->execute();
                if ($inserted <= 0) {
                    $tx->commit(); // 已回滚过,直接放行
                    return;
                }

                // 未支付订单(典型:取消未付款单)从未计入任何统计,只占幂等锚点、不回滚任何桶
                if (!$wasPaid) {
                    $tx->commit();
                    return;
                }

                // 3) 回滚本人(行锁读改写 goods_map + 数值字段 GREATEST 扣减)
                $row = $db->createCommand(
                    "SELECT purchased_goods_map FROM {{%user_team_stat}} WHERE user_id = :uid FOR UPDATE"
                )->bindValue(':uid', $userId)->queryOne();
                if ($row !== false) {
                    $goodsMap = !empty($row['purchased_goods_map']) ? Json::decode($row['purchased_goods_map']) : [];
                    foreach ($goodsDelta as $gid => $num) {
                        if (!isset($goodsMap[$gid])) continue;
                        $goodsMap[$gid] = max(0, (int)$goodsMap[$gid] - (int)$num);
                        if ($goodsMap[$gid] <= 0) unset($goodsMap[$gid]);
                    }

                    // 团队业绩含本人 → 本人 team_spend_paid 也要随退款回滚。
                    // PDO 真实预处理同名占位符不可复用,team 用独立 :paid_t / :settled_t。
                    $set = [
                        'personal_spend_paid = GREATEST(0, personal_spend_paid - :paid)',
                        'team_spend_paid = GREATEST(0, team_spend_paid - :paid_t)',
                        'purchased_goods_map = :gmap',
                        'dirty = 1',
                    ];
                    $params = [':paid' => $paidAmount, ':paid_t' => $paidAmount, ':gmap' => Json::encode($goodsMap), ':uid2' => $userId];
                    if ($deductSettled) {
                        $set[] = 'personal_spend_settled = GREATEST(0, personal_spend_settled - :settled)';
                        $set[] = 'team_spend_settled = GREATEST(0, team_spend_settled - :settled_t)';
                        $params[':settled'] = $settledAmount;
                        $params[':settled_t'] = $settledAmount;
                    }
                    // 这次把这张单最后一条算数的明细也退了(整单取消、逐条退光都算):不再是有效订单。
                    // 原来只在整单取消时减,逐条退光的单一直算着,巡检重算又不算
                    if ($countedBefore > 0 && $countedLeft() === 0) {
                        $set[] = 'personal_order_count = GREATEST(0, personal_order_count - 1)';
                    }
                    $db->createCommand(
                        'UPDATE {{%user_team_stat}} SET ' . implode(', ', $set) . ' WHERE user_id = :uid2',
                        $params
                    )->execute();
                }

                // 4) 沿祖先链回滚团队业绩(原子扣减)
                if (!empty($ancestors)) {
                    $teamSet = ['team_spend_paid = GREATEST(0, team_spend_paid - :paid)', 'dirty = 1'];
                    $teamParams = [':paid' => $paidAmount];
                    if ($deductSettled) {
                        $teamSet[] = 'team_spend_settled = GREATEST(0, team_spend_settled - :settled)';
                        $teamParams[':settled'] = $settledAmount;
                    }
                    // 走同一个事务连接,分批也在这笔事务内
                    self::updateStatIn($ancestors, implode(', ', $teamSet), $teamParams, $db);
                }

                $tx->commit();
            } catch (\Throwable $e) {
                $tx->rollBack();
                throw $e;
            }
        } catch (\Throwable $e) {
            Yii::error("[TeamStat] onCommissionRevert 失败 order={$orderId} detail={$orderDetailId}: " . $e->getMessage());
        }
    }

    /**
     * 刷新某用户的 team_legs_map（各直推线的团队规模和最高等级）
     */
    public static function refreshParentLegsMap(int $parentId, int $mallId): void
    {
        // 一次带出直推孩子 + 各自 path(避免逐孩子再查一次);path 用于子树前缀匹配走索引
        $directChildren = User::find()->alias('u')
            ->leftJoin(UserPath::tableName() . ' cp', 'cp.user_id = u.id')
            ->select(['u.id', 'u.level_id', 'u.level', 'cp.path'])
            ->where(['u.parent_id' => $parentId, 'u.mall_id' => $mallId, 'u.is_delete' => 0])
            ->asArray()->all();

        $legsMap = [];
        // 改等级事件还在排队的人按统计里记着的等级算(见 lowerLegMaxIn):巡检重建的线不能「跑到事件前面」,
        // 否则事件落地时降级重算在这条线上就停了,上面几级的线卡在旧的高等级上
        $pending = self::pendingLevelWeights($mallId);
        foreach ($directChildren as $child) {
            $childId = (int)$child['id'];
            // 前缀匹配(走 idx-user_path-path 索引):孩子自身 path 存在→'-..-childId-%';缺失→回退包含匹配(不漏)
            $childLike = !empty($child['path']) ? $child['path'] . '%' : "%-{$childId}-%";

            // 这条线的团队人数(含孩子本人:其自身 path 亦匹配该前缀)
            $legCount = UserPath::find()
                ->where(['like', 'path', $childLike, false])
                ->count();

            // 这条线的最高等级（含直推本人;孩子已按未注销、本商城筛过,他自己的等级直接算上）
            $maxLevel = self::legMaxLevel($childLike, $mallId, (int)($pending[$childId]['w'] ?? $child['level']), [], $pending);

            $legsMap[(string)$childId] = [
                'count' => (int)$legCount,
                'max_level' => (int)$maxLevel,
            ];
        }

        $stat = UserTeamStat::getOrCreate($parentId, $mallId);
        $stat->setMapField('team_legs_map', $legsMap);
        $stat->save(false);
    }

    /**
     * 子树 LIKE 匹配值:取本人自身 path 作前缀('-a-b-id-%'),命中 idx-user_path-path(255) 索引,
     * 替代 '%-id-%' 前导通配符全表扫(单棵树里两者返回同一批人)。
     * 本人 path 缺失(极少数据不一致)→回退包含匹配,保证不漏。
     */
    private static function subtreeLike(int $userId): string
    {
        // 统一走 UserService(带 path 请求级缓存),避免每次 fullRebuild 再查一次本人 path
        return UserService::subtreeLike($userId);
    }

    /**
     * 统计里已经记上的消费明细(口径见 EffectiveSpend):个人消费、团队消费、有效订单数、已购商品都从这里取,
     * fullRebuild 巡检与改挂整队转移同用 —— 转移的金额就是巡检重算会从旧链拿走、给新链加上的那部分,
     * 口径一差,每次改挂都留一笔差额等巡检抹平。圈人条件由调用方加(o.user_id 或 tp 子树前缀)。
     */
    private static function spendQuery(int $mallId, bool $settled, bool $joinPath = false): \yii\db\ActiveQuery
    {
        $q = OrderDetail::find()->alias('od')
            ->innerJoin(Order::tableName() . ' o', 'o.id = od.order_id');
        if ($joinPath) {
            $q->innerJoin(UserPath::tableName() . ' tp', 'tp.user_id = o.user_id');
        }
        return $q->where(['o.mall_id' => $mallId, 'o.is_delete' => 0])
            ->andWhere(EffectiveSpend::accounted($mallId, $settled));
    }

    /**
     * 等级分布里把「现在的等级」换成「统计里记着的等级」:$now = [uid => 现在的 level_id],$accounted = [uid => 记着的]。
     * 与 useAccountedLevels 同一换法;0 = 没等级,不进分布。
     */
    private static function shiftToAccountedLevels(array $map, array $now, array $accounted): array
    {
        foreach ($now as $uid => $cur) {
            $cur = (int)$cur;
            $was = (int)($accounted[(int)$uid] ?? $cur);
            if ($cur === $was) {
                continue;
            }
            if ($cur > 0 && isset($map[(string)$cur]) && --$map[(string)$cur] <= 0) {
                unset($map[(string)$cur]);
            }
            if ($was > 0) {
                $map[(string)$was] = ($map[(string)$was] ?? 0) + 1;
            }
        }
        return $map;
    }

    /**
     * 全量初始化某用户的 stat（用于数据迁移或修复）
     */
    public static function fullRebuild(int $userId, int $mallId): void
    {
        $stat = UserTeamStat::getOrCreate($userId, $mallId);

        // 直推
        $stat->direct_count = (int)User::find()->where(['parent_id' => $userId, 'mall_id' => $mallId, 'is_delete' => 0])->count();

        // 直推等级分布
        $directLevels = User::find()
            ->select(['level_id', 'COUNT(*) as cnt'])
            ->where(['parent_id' => $userId, 'mall_id' => $mallId, 'is_delete' => 0])
            ->andWhere(['>', 'level_id', 0])
            ->groupBy('level_id')->asArray()->all();
        $dlm = [];
        foreach ($directLevels as $dl) $dlm[(string)$dl['level_id']] = (int)$dl['cnt'];
        // 改等级事件还在排队的人按统计里记着的等级算(见 TeamStatOutbox::accountedLevelIds),落地时不会再 -1 / +1 一遍
        $accounted = TeamStatOutbox::accountedLevelIds($mallId);
        if ($accounted) {
            $now = User::find()->select(['level_id', 'id'])
                ->where(['id' => array_keys($accounted), 'parent_id' => $userId, 'mall_id' => $mallId, 'is_delete' => 0])
                ->indexBy('id')->column();
            $dlm = self::shiftToAccountedLevels($dlm, $now, $accounted);
        }
        $stat->setMapField('direct_level_map', $dlm);

        // 团队:用本人 path 作前缀匹配子树('-a-b-id-%'),命中 idx-user_path-path 索引,替代 '%-id-%' 前导通配全表扫
        $subtreeLike = self::subtreeLike($userId);
        $stat->team_count = (int)UserPath::find()->where(['like', 'path', $subtreeLike, false])->andWhere(['!=', 'user_id', $userId])->count();

        // 团队等级分布
        $teamLevels = User::find()->alias('u')
            ->leftJoin(UserPath::tableName() . ' p', 'u.id = p.user_id')
            ->select(['u.level_id', 'COUNT(*) as cnt'])
            ->where(['like', 'p.path', $subtreeLike, false])
            ->andWhere(['!=', 'u.id', $userId])
            ->andWhere(['u.is_delete' => 0])
            ->andWhere(['>', 'u.level_id', 0])
            ->groupBy('u.level_id')->asArray()->all();
        $tlm = [];
        foreach ($teamLevels as $tl) $tlm[(string)$tl['level_id']] = (int)$tl['cnt'];
        if ($accounted) {
            $now = User::find()->alias('u')
                ->leftJoin(UserPath::tableName() . ' p', 'u.id = p.user_id')
                ->select(['u.level_id', 'u.id'])
                ->where(['like', 'p.path', $subtreeLike, false])
                ->andWhere(['u.id' => array_keys($accounted), 'u.is_delete' => 0])
                ->andWhere(['!=', 'u.id', $userId])
                ->indexBy('id')->column();
            $tlm = self::shiftToAccountedLevels($tlm, $now, $accounted);
        }
        $stat->setMapField('team_level_map', $tlm);

        // 消费类一律按「统计里应当记着的」口径算(EffectiveSpend::accounted),与增量逐笔对齐:
        //   · 退掉的明细:回滚台账 + 明细无效两道都认 —— 增量在售后同意那刻就扣了,明细要等退款到账才置无效,
        //     只看无效会把「同意后、到账前」的已退明细重新算回去;
        //   · 付款 / 结算 / 回滚事件还在 outbox 排队时,按「落地后正好等于真实值」倒推,事件落地时不会再加一次 / 扣一次。
        // 个人消费(明细实付,不含运费)
        $stat->personal_spend_paid = (float)(self::spendQuery($mallId, false)->andWhere(['o.user_id' => $userId])->sum('od.pay_price') ?: 0);
        $stat->personal_spend_settled = (float)(self::spendQuery($mallId, true)->andWhere(['o.user_id' => $userId])->sum('od.pay_price') ?: 0);
        // 有效订单数:还有算数的明细的单(整单取消、逐条退光的都不算)
        $stat->personal_order_count = (int)self::spendQuery($mallId, false)->andWhere(['o.user_id' => $userId])->count('DISTINCT od.order_id');

        // 购买商品数量
        $goodsRows = self::spendQuery($mallId, false)->andWhere(['o.user_id' => $userId])
            ->select(['od.goods_id', 'SUM(od.num) as total_num'])
            ->groupBy('od.goods_id')->asArray()->all();
        $gm = [];
        foreach ($goodsRows as $gr) $gm[(string)$gr['goods_id']] = (int)$gr['total_num'];
        $stat->setMapField('purchased_goods_map', $gm);

        // 团队消费(口径【含本人】)。
        // Order JOIN user_path 前缀直连,替代"把整棵子树 id 拉进 PHP 再 o.user_id IN(...)" ——
        // 顶层用户子树可达数万人,大 IN 会撞 MySQL 占位符上限(65535,错误 1390)且吃内存。
        // subtreeLike 前缀已【含本人】(本人 path 亦匹配自身前缀);tp.user_id 是主键,join 不放大行数。
        $stat->team_spend_paid = (float)(self::spendQuery($mallId, false, true)
            ->andWhere(['like', 'tp.path', $subtreeLike, false])
            ->sum('od.pay_price') ?: 0);
        $stat->team_spend_settled = (float)(self::spendQuery($mallId, true, true)
            ->andWhere(['like', 'tp.path', $subtreeLike, false])
            ->sum('od.pay_price') ?: 0);

        // Legs
        self::refreshParentLegsMap($userId, $mallId);

        $stat->dirty = 0;
        $stat->last_check_at = time();
        $stat->save(false);
    }
}
