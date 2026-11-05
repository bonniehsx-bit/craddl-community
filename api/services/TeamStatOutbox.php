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

use app\helpers\OptionHelper;
use Yii;
use yii\db\Query;
use yii\helpers\Json;

/**
 * 团队统计【可靠事件外发箱】—— transactional outbox 的生产 / 消费 API。
 *
 *  - 生产:业务事务里 emit() 记一条待办(与业务写同事务原子落库,崩溃不丢);
 *  - 消费:EventOutboxTask 用 claim() → 处理 → ack()/fail()。
 *
 * 逐商城:默认开启,个别商城可 `team_stat_outbox_disabled=1` 单独关闭:
 *   关 → emit() 空转不写、老 TeamStatListener 直接增量;
 *   开(默认) → emit() 落待办、listener/直接调用方改为 emit、由 worker 可靠消费。
 *
 * 幂等:worker「处理 + ack」在同一事务 → exactly-once effect,无需去重表。
 */
class TeamStatOutbox
{
    const TABLE = '{{%event_outbox}}';

    const STATUS_PENDING = 0;
    const STATUS_DONE = 1;
    const STATUS_DEAD = 2;

    const LOCK_TTL = 300;   // 领取后 300s 未完成 → 视为超时,可被重新领取(免单独 reaper)
    const MAX_ATTEMPTS = 6; // 超过 → 死信,待人工

    /** 逐商城是否启用 outbox。默认【开】;个别商城可 team_stat_outbox_disabled=1 单独关闭。
     *  (不判 team_stat_outbox_enabled:OptionHelper::get 会把存的 '0' 当假值 = 等于没配,无法表达「显式关」) */
    public static function enabled(int $mallId): bool
    {
        return (int)OptionHelper::get('team_stat_outbox_disabled', $mallId) !== 1;
    }

    /**
     * 改等级事件还在排队的人 → 统计里记着的等级:最早一条还没落地的 level_changed 的 before_level_id。
     *
     * 改等级是先改 user 表、事件排队,落地时才按那一刻的链改统计(onLevelChanged:旧等级 -1、新等级 +1)。
     * 重算统计(巡检、批量重建)和改上级整队转移都要按它算,不能按 user 表现在的等级 ——
     * 否则事件落地时又做一次 -1 / +1,等级分布里同一个人算了两遍。
     * 只看 PENDING:进了死信的不会再落地,按现等级算正好收敛。没开 outbox 返回 []。
     *
     * @param int|null $userId 只要这一个人的(不把整个商城排队中的都读出来)
     * @return array user_id => 统计里记着的 level_id
     */
    public static function accountedLevelIds(int $mallId, ?int $userId = null): array
    {
        if (!self::enabled($mallId)) {
            return [];
        }
        $q = (new Query())->select('payload')->from(self::TABLE)
            ->where(['mall_id' => $mallId, 'type' => 'level_changed', 'status' => self::STATUS_PENDING])
            ->orderBy(['id' => SORT_ASC]);
        if ($userId !== null) {
            $q->andWhere(new \yii\db\Expression("JSON_EXTRACT(payload, '$.user_id') = :u", [':u' => $userId]));
        }
        $out = [];
        foreach ($q->column() as $json) {
            $p = json_decode((string)$json, true) ?: [];
            $uid = (int)($p['user_id'] ?? 0);
            if ($uid > 0 && !array_key_exists($uid, $out)) {
                $out[$uid] = (int)($p['before_level_id'] ?? 0);
            }
        }
        return $out;
    }

    /**
     * 在【当前业务事务内】记一条待办。绝不自开事务 → 跟随外层业务事务同生共死。
     * 未启用则空转,不写。
     */
    public static function emit(int $mallId, string $type, array $payload): void
    {
        if (!self::enabled($mallId)) {
            return;
        }
        $now = time();
        Yii::$app->db->createCommand()->insert(self::TABLE, [
            'mall_id'      => $mallId,
            'type'         => $type,
            'payload'      => Json::encode($payload),
            'status'       => self::STATUS_PENDING,
            'attempts'     => 0,
            'available_at' => $now,
            'locked_at'    => 0,
            'is_delete'    => 0,
            'created_at'   => $now,
            'updated_at'   => $now,
        ])->execute();
    }

    /** 领取一批待办(打令牌;仅「从未锁 / 锁超时」的可领),返回行数组(按 id 升序 = 处理顺序) */
    public static function claim(int $mallId, int $limit, string $token): array
    {
        $now = time();
        $stale = $now - self::LOCK_TTL;
        $lim = max(1, (int)$limit);
        // Yii update 不支持 ORDER BY/LIMIT,这里用原生 SQL;LIMIT 用 int 内联(无注入)
        // 注:同一命名占位符不能复用(原生预处理下 PDO 会按出现次数要求绑定),故 :now 拆成三个
        Yii::$app->db->createCommand(
            'UPDATE ' . self::TABLE . ' SET locked_token=:tok, locked_at=:now1, updated_at=:now2'
            . ' WHERE mall_id=:mall AND status=:pending AND available_at<=:now3 AND locked_at < :stale'
            . " ORDER BY id ASC LIMIT {$lim}",
            [
                ':tok' => $token, ':now1' => $now, ':now2' => $now, ':now3' => $now,
                ':mall' => $mallId, ':pending' => self::STATUS_PENDING, ':stale' => $stale,
            ]
        )->execute();

        return (new Query())->from(self::TABLE)
            ->where(['locked_token' => $token, 'status' => self::STATUS_PENDING])
            ->orderBy(['id' => SORT_ASC])->all();
    }

    /** 处理成功:标完成、释放锁 */
    public static function ack(int $id): void
    {
        Yii::$app->db->createCommand()->update(self::TABLE,
            ['status' => self::STATUS_DONE, 'locked_token' => null, 'updated_at' => time()],
            ['id' => $id]
        )->execute();
    }

    /** 处理失败:指数退避重试;超上限转死信 */
    public static function fail(int $id, string $error): void
    {
        $row = (new Query())->select(['attempts'])->from(self::TABLE)->where(['id' => $id])->one();
        $attempts = ((int)($row['attempts'] ?? 0)) + 1;
        $now = time();
        $dead = $attempts >= self::MAX_ATTEMPTS;
        $backoff = $now + (int)(30 * pow(2, min($attempts, 8))); // 60→120→240→…s
        Yii::$app->db->createCommand()->update(self::TABLE, [
            'status'       => $dead ? self::STATUS_DEAD : self::STATUS_PENDING,
            'attempts'     => $attempts,
            'available_at' => $backoff,
            'locked_token' => null,
            'last_error'   => mb_substr((string)$error, 0, 490),
            'updated_at'   => $now,
        ], ['id' => $id])->execute();
    }

    /** 清理:已完成且超 3 天的行删掉,防表膨胀 */
    public static function cleanup(int $mallId): void
    {
        Yii::$app->db->createCommand()->delete(self::TABLE, [
            'and',
            ['mall_id' => $mallId, 'status' => self::STATUS_DONE],
            ['<', 'updated_at', time() - 3 * 86400],
        ])->execute();
    }
}
