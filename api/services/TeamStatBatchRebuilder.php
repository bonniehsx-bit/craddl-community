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
use app\models\UserTeamStat;
use Yii;
use yii\db\Query;
use yii\helpers\Json;

/**
 * 团队统计【自底向上批量对账】—— 方案 A(替代 per-user 逐个重扫子树的反熵扫描)。
 *
 * 思路:一趟遍历算完所有人,不再"每个用户单独重扫自己的子树"。
 *   1. 个人维度(消费/订单/直推):各做一次【全表 GROUP BY】一把拿到所有人的值;
 *   2. 团队维度(team_count / team_spend / team_level_map / team_legs_map):按深度【从叶到根】
 *      把已算好的直推孩子汇总进父亲(滚加);
 *   3. 【分块流式】写回:每 500 人一块,单独取该块 goodsMap + 旧值,建行 → 比 sig → upsert,
 *      不把 goodsMap / writeRows / 旧值全量驻留内存,削峰值防 OOM。
 *
 * 复杂度 O(N + 订单数),与树形状无关。口径与 TeamStatUpdater::fullRebuild 对齐(含其 is_delete 处理)。
 * 只重算/写「值」字段,不动月度字段 month_*;值变了(签名变)的用户标 dirty=1 交 DirtyCheckTask 重判升级。
 * 增量实时链路(onOrderPaid / onUserBind)完全不受影响。
 *
 * 内存:滚算累加器(scalar + 小 map)按单商城用户量常驻(171 万约百 MB 级),写回分块;
 *      run() 内已放宽 memory_limit。真到千万级用户需按子树分片,当前逐商城足够。
 */
class TeamStatBatchRebuilder
{
    /** 单商城全量对账。返回 ['users'=>写入人数, 'changed'=>纠正漂移人数, 'elapsed'=>秒] */
    public static function run(int $mallId): array
    {
        // 滚算累加器在内存;放宽上限(逐商城;真到千万级用户需按子树分片,见类头注释)
        @ini_set('memory_limit', '1024M');
        $t0 = microtime(true);
        $db = Yii::$app->db;
        $now = time();

        // ---------- 1. 个人维度:各一次全表聚合。goodsMap 体量最大,改为写回时【分块取】,不全量驻留 ----------
        // 消费类口径与 fullRebuild 完全一致:统计里应当记着的有效消费(EffectiveSpend::accounted)
        $personalPaid    = self::spendByUser($mallId, false);
        $personalSettled = self::spendByUser($mallId, true);
        $orderCount      = self::orderCountByUser($mallId);
        [$directCount, $directLevelMap] = self::directByParent($mallId);

        // ---------- 2. 载入全部用户(含 is_delete),按深度降序:叶在前、根在后 ----------
        // 注:path 在独立表 user_path,不在 user 表;深度直接按 parent_id 链算,不依赖 user_path。
        $users = (new Query())->select(['id', 'parent_id', 'level', 'level_id', 'is_delete'])
            ->from(User::tableName())->where(['mall_id' => $mallId])->all();

        $deleted = []; $levelOf = []; $levelIdOf = []; $children = []; $parentOf = [];
        foreach ($users as $u) {
            $id = (int)$u['id'];
            $deleted[$id]   = ((int)$u['is_delete'] === 1);
            $levelOf[$id]   = (int)$u['level'];
            $levelIdOf[$id] = (int)$u['level_id'];
            $pid = (int)$u['parent_id'];
            $parentOf[$id]  = $pid;
            if ($pid > 0) {
                $children[$pid][] = $id;
            }
        }
        // 改等级事件还在排队的人,等级分布和各线最高等级都按统计里记着的等级算(同 fullRebuild,见 TeamStatOutbox::accountedLevelIds)
        $weightOf = \app\models\Level::find()->select(['level', 'id'])->where(['mall_id' => $mallId])->indexBy('id')->column();
        foreach (TeamStatOutbox::accountedLevelIds($mallId) as $id => $was) {
            if (isset($levelOf[$id])) {
                $levelOf[$id] = (int)($weightOf[$was] ?? 0);
            }
            if (!isset($levelIdOf[$id]) || $levelIdOf[$id] === $was) {
                continue;
            }
            $pid = $parentOf[$id];
            if ($pid > 0 && !$deleted[$id]) {
                $cur = (string)$levelIdOf[$id];
                if ($cur !== '0' && isset($directLevelMap[$pid][$cur]) && --$directLevelMap[$pid][$cur] <= 0) {
                    unset($directLevelMap[$pid][$cur]);
                }
                if ($was > 0) {
                    $directLevelMap[$pid][(string)$was] = ($directLevelMap[$pid][(string)$was] ?? 0) + 1;
                }
            }
            $levelIdOf[$id] = $was;
        }
        // 深度 = parent_id 链长度(占位防环;树深受 ensurePath 限 ~100 层,递归安全)
        $depth = [];
        $calcDepth = function ($id) use (&$calcDepth, &$depth, $parentOf) {
            if (isset($depth[$id])) {
                return $depth[$id];
            }
            $depth[$id] = 0; // 先占位:遇环直接返回 0,不无限递归
            $pid = $parentOf[$id] ?? 0;
            if ($pid > 0 && isset($parentOf[$pid])) {
                $depth[$id] = $calcDepth($pid) + 1;
            }
            return $depth[$id];
        };
        foreach ($users as $u) {
            $calcDepth((int)$u['id']);
        }
        usort($users, function ($a, $b) use ($depth) {
            return $depth[(int)$b['id']] <=> $depth[(int)$a['id']];
        });

        // ---------- 3. 自底向上滚算(只留累加器,不建全量 writeRows) ----------
        $teamCount = []; $teamPaid = []; $teamSettled = []; $teamLevelMap = []; $subtreeMax = []; $legsMap = [];
        $writeIds = [];
        foreach ($users as $u) {
            $uid = (int)$u['id'];
            $tc  = 0;
            $tp  = $personalPaid[$uid]    ?? 0.0;   // team_spend 含本人 → 从个人起算
            $ts  = $personalSettled[$uid] ?? 0.0;
            $tlm = [];
            $legs = [];
            $smax = $deleted[$uid] ? 0 : $levelOf[$uid];

            foreach ($children[$uid] ?? [] as $cid) {
                // team_count 含已删成员(对齐 fullRebuild 的 UserPath 计数口径)
                $tc += 1 + ($teamCount[$cid] ?? 0);
                $tp += $teamPaid[$cid] ?? 0.0;
                $ts += $teamSettled[$cid] ?? 0.0;
                // team_level_map 排除已删(对齐 fullRebuild 的 u.is_delete=0)
                if (!$deleted[$cid] && ($levelIdOf[$cid] ?? 0) > 0) {
                    $k = (string)$levelIdOf[$cid];
                    $tlm[$k] = ($tlm[$k] ?? 0) + 1;
                }
                foreach ($teamLevelMap[$cid] ?? [] as $lid => $cnt) {
                    $tlm[$lid] = ($tlm[$lid] ?? 0) + $cnt;
                }
                $childMax = $subtreeMax[$cid] ?? 0;
                if ($childMax > $smax) {
                    $smax = $childMax;
                }
                // legs 只列【非删】直推;人数含孩子本人(1 + 孩子后代,含已删);最高等级=该线子树最高(排除已删)
                if (!$deleted[$cid]) {
                    $legs[(string)$cid] = ['count' => 1 + ($teamCount[$cid] ?? 0), 'max_level' => $childMax];
                }
            }

            $teamCount[$uid]    = $tc;
            $teamPaid[$uid]     = $tp;
            $teamSettled[$uid]  = $ts;
            $teamLevelMap[$uid] = $tlm;
            $subtreeMax[$uid]   = $smax;
            $legsMap[$uid]      = $legs;
            if (!$deleted[$uid]) {
                $writeIds[] = $uid; // 已删用户不写自己的 stat(但其值已滚入祖先)
            }
        }
        // 树/深度/仅滚算用的结构不再需要,释放以降峰值
        unset($users, $children, $parentOf, $depth, $subtreeMax, $levelOf, $levelIdOf, $deleted);

        // ---------- 4. 分块流式写回:每块单独取 goodsMap + 旧值 → 建行 → 比 sig → upsert ----------
        $cols = ['user_id', 'mall_id', 'direct_count', 'direct_level_map', 'team_count', 'team_level_map', 'team_legs_map',
            'personal_spend_paid', 'personal_spend_settled', 'personal_order_count', 'purchased_goods_map',
            'team_spend_paid', 'team_spend_settled', 'dirty', 'last_check_at', 'created_at', 'updated_at', 'is_delete'];
        $updateCols = ['direct_count', 'direct_level_map', 'team_count', 'team_level_map', 'team_legs_map',
            'personal_spend_paid', 'personal_spend_settled', 'personal_order_count', 'purchased_goods_map',
            'team_spend_paid', 'team_spend_settled', 'dirty', 'last_check_at', 'updated_at'];

        $changed = 0;
        foreach (array_chunk($writeIds, 500) as $chunk) {
            $goodsChunk = self::goodsMapByUser($mallId, $chunk); // 仅本块商品明细
            $oldChunk = (new Query())->select([
                    'user_id', 'direct_count', 'direct_level_map', 'team_count', 'team_level_map', 'team_legs_map',
                    'purchased_goods_map', 'team_spend_paid', 'team_spend_settled',
                    'personal_spend_paid', 'personal_spend_settled', 'dirty',
                ])
                ->from(UserTeamStat::tableName())
                ->where(['mall_id' => $mallId, 'user_id' => $chunk])
                ->indexBy('user_id')->all();

            $buffer = [];
            foreach ($chunk as $uid) {
                $r = [
                    'direct_count'           => (int)($directCount[$uid] ?? 0),
                    'direct_level_map'       => Json::encode($directLevelMap[$uid] ?? []),
                    'team_count'             => (int)($teamCount[$uid] ?? 0),
                    'team_level_map'         => Json::encode($teamLevelMap[$uid] ?? []),
                    'team_legs_map'          => Json::encode($legsMap[$uid] ?? []),
                    'personal_spend_paid'    => number_format($personalPaid[$uid] ?? 0, 2, '.', ''),
                    'personal_spend_settled' => number_format($personalSettled[$uid] ?? 0, 2, '.', ''),
                    'personal_order_count'   => (int)($orderCount[$uid] ?? 0),
                    'purchased_goods_map'    => Json::encode($goodsChunk[$uid] ?? []),
                    'team_spend_paid'        => number_format($teamPaid[$uid] ?? 0, 2, '.', ''),
                    'team_spend_settled'     => number_format($teamSettled[$uid] ?? 0, 2, '.', ''),
                ];
                $oldRow = $oldChunk[$uid] ?? null;
                if ($oldRow === null) {
                    $dirty = 0;                                 // 新建行,值即正确
                } elseif (self::sig($oldRow) !== self::sig($r)) {
                    $dirty = 1; $changed++;                      // 漂移被纠正 → 交 DirtyCheckTask 重判升级
                } else {
                    $dirty = (int)$oldRow['dirty'];              // 没变,保留原状态
                }
                $buffer[] = [
                    (int)$uid, $mallId,
                    $r['direct_count'], $r['direct_level_map'],
                    $r['team_count'], $r['team_level_map'], $r['team_legs_map'],
                    $r['personal_spend_paid'], $r['personal_spend_settled'], $r['personal_order_count'], $r['purchased_goods_map'],
                    $r['team_spend_paid'], $r['team_spend_settled'],
                    $dirty, $now, $now, $now, 0,
                ];
            }
            self::upsertChunk($db, $cols, $updateCols, $buffer);
        }

        return ['users' => count($writeIds), 'changed' => $changed, 'elapsed' => round(microtime(true) - $t0, 2)];
    }

    /** 统计里已经记上的有效消费明细(已付 / 已结算口径),与 TeamStatUpdater::fullRebuild 同一判据 */
    private static function spendDetails(int $mallId, bool $settled): Query
    {
        return (new Query())
            ->from(['od' => OrderDetail::tableName()])
            ->innerJoin(['o' => Order::tableName()], 'o.id = od.order_id')
            ->where(['o.mall_id' => $mallId, 'o.is_delete' => 0])
            ->andWhere(EffectiveSpend::accounted($mallId, $settled));
    }

    /** 个人消费(明细实付,不含运费)=> [user_id => sum] */
    private static function spendByUser(int $mallId, bool $settled): array
    {
        $rows = self::spendDetails($mallId, $settled)
            ->select(['uid' => 'o.user_id', 's' => 'SUM(od.pay_price)'])
            ->groupBy('o.user_id')
            ->all();
        $m = [];
        foreach ($rows as $r) {
            $m[(int)$r['uid']] = (float)$r['s'];
        }
        return $m;
    }

    /** 个人有效订单数(还有算数明细的单)=> [user_id => count] */
    private static function orderCountByUser(int $mallId): array
    {
        $rows = self::spendDetails($mallId, false)
            ->select(['uid' => 'o.user_id', 'c' => 'COUNT(DISTINCT od.order_id)'])
            ->groupBy('o.user_id')->all();
        $m = [];
        foreach ($rows as $r) {
            $m[(int)$r['uid']] = (int)$r['c'];
        }
        return $m;
    }

    /** 个人购买商品数量 => [user_id => [goods_id => qty]]。$userIds 非空则只取这批用户(分块流式写用) */
    private static function goodsMapByUser(int $mallId, ?array $userIds = null): array
    {
        $q = self::spendDetails($mallId, false)->select(['uid' => 'o.user_id', 'gid' => 'od.goods_id', 'n' => 'SUM(od.num)']);
        if ($userIds !== null) {
            $q->andWhere(['o.user_id' => $userIds]);
        }
        $rows = $q->groupBy(['o.user_id', 'od.goods_id'])->all();
        $m = [];
        foreach ($rows as $r) {
            $m[(int)$r['uid']][(string)$r['gid']] = (int)$r['n'];
        }
        return $m;
    }

    /** 直推数 + 直推等级分布 => [ [user_id=>cnt], [user_id=>[level_id=>cnt]] ](均只算 is_delete=0) */
    private static function directByParent(int $mallId): array
    {
        $rows = (new Query())->select(['pid' => 'parent_id', 'lid' => 'level_id', 'c' => 'COUNT(*)'])
            ->from(User::tableName())
            ->where(['mall_id' => $mallId, 'is_delete' => 0])
            ->andWhere(['>', 'parent_id', 0])
            ->groupBy(['parent_id', 'level_id'])->all();
        $cnt = []; $lm = [];
        foreach ($rows as $r) {
            $pid = (int)$r['pid']; $lid = (int)$r['lid']; $c = (int)$r['c'];
            $cnt[$pid] = ($cnt[$pid] ?? 0) + $c;      // direct_count 含 level_id=0
            if ($lid > 0) {
                $lm[$pid][(string)$lid] = $c;         // direct_level_map 仅 level_id>0
            }
        }
        return [$cnt, $lm];
    }

    /** 一条多值 INSERT ... ON DUPLICATE KEY UPDATE(500 行 × 18 列 = 9000 占位符,远低于上限) */
    private static function upsertChunk($db, array $cols, array $updateCols, array $rows): void
    {
        $ph = []; $params = []; $i = 0;
        foreach ($rows as $row) {
            $marks = [];
            foreach ($row as $v) {
                $key = ':p' . $i++;
                $marks[] = $key;
                $params[$key] = $v;
            }
            $ph[] = '(' . implode(',', $marks) . ')';
        }
        $colSql = '`' . implode('`,`', $cols) . '`';
        $updSql = implode(',', array_map(function ($c) { return "`$c`=VALUES(`$c`)"; }, $updateCols));
        $sql = 'INSERT INTO ' . UserTeamStat::tableName() . " ($colSql) VALUES " . implode(',', $ph)
            . " ON DUPLICATE KEY UPDATE $updSql";
        $db->createCommand($sql, $params)->execute();
    }

    /**
     * 值签名(与 TeamStatScrubTask::levelSig 对齐):map 按 key 归一化,消除顺序差异。
     * 新行(map 为 JSON 串)与旧行(DB,map 为 JSON 串)统一比较。
     */
    private static function sig(array $r): string
    {
        $norm = function ($json) {
            if (empty($json)) {
                return '{}';
            }
            try {
                $a = (array)Json::decode($json);
            } catch (\Throwable $e) {
                return '{}';
            }
            ksort($a);
            return Json::encode($a);
        };
        return implode('#', [
            $norm($r['direct_level_map'] ?? ''),
            $norm($r['team_level_map'] ?? ''),
            $norm($r['team_legs_map'] ?? ''),
            $norm($r['purchased_goods_map'] ?? ''),
            (string)(float)($r['team_spend_paid'] ?? 0),
            (string)(float)($r['team_spend_settled'] ?? 0),
            (string)(float)($r['personal_spend_paid'] ?? 0),
            (string)(float)($r['personal_spend_settled'] ?? 0),
            (string)(int)($r['team_count'] ?? 0),
            (string)(int)($r['direct_count'] ?? 0),
        ]);
    }
}
