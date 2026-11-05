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

use app\models\Level;
use app\models\UserTeamStat;

/**
 * 团队数据统一查询服务
 * 所有插件的升级条件检查通过这里读取，O(1) 查询
 */
class TeamQueryService
{
    private $stat;

    public function __construct(int $userId, int $mallId)
    {
        $this->stat = UserTeamStat::getOrCreate($userId, $mallId);
    }

    public function getStat(): UserTeamStat
    {
        return $this->stat;
    }

    // ===== 直推维度 =====

    public function getDirectCount(): int
    {
        return (int)$this->stat->direct_count;
    }

    /**
     * 直推中指定等级（及以上权重）的人数
     */
    public function getDirectCountByLevel(int $targetLevelId): int
    {
        $targetWeight = $this->getLevelWeight($targetLevelId);
        if ($targetWeight <= 0) return 0;

        $dlm = $this->stat->getMapField('direct_level_map');
        $count = 0;
        foreach ($dlm as $levelId => $num) {
            $w = $this->getLevelWeight((int)$levelId);
            if ($w >= $targetWeight) $count += $num;
        }
        return $count;
    }

    // ===== 团队维度 =====

    public function getTeamCount(): int
    {
        return (int)$this->stat->team_count;
    }

    /**
     * 团队中指定等级（及以上权重）的人数
     */
    public function getTeamCountByLevel(int $targetLevelId): int
    {
        $targetWeight = $this->getLevelWeight($targetLevelId);
        if ($targetWeight <= 0) return 0;

        $tlm = $this->stat->getMapField('team_level_map');
        $count = 0;
        foreach ($tlm as $levelId => $num) {
            $w = $this->getLevelWeight((int)$levelId);
            if ($w >= $targetWeight) $count += $num;
        }
        return $count;
    }

    /**
     * 多线并行考核：有几条线包含指定等级及以上的成员
     */
    public function getQualifiedLegs(int $targetLevelId): int
    {
        $targetWeight = $this->getLevelWeight($targetLevelId);
        if ($targetWeight <= 0) return 0;

        $legs = $this->stat->getMapField('team_legs_map');
        $count = 0;
        foreach ($legs as $uid => $info) {
            if (isset($info['max_level']) && (int)$info['max_level'] >= $targetWeight) {
                $count++;
            }
        }
        return $count;
    }

    // ===== 个人消费维度 =====

    public function getPersonalSpend(bool $settled = false): float
    {
        return $settled ? (float)$this->stat->personal_spend_settled : (float)$this->stat->personal_spend_paid;
    }

    public function getPersonalOrderCount(): int
    {
        return (int)$this->stat->personal_order_count;
    }

    /**
     * 是否购买过指定商品
     */
    public function hasPurchasedGoods(array $goodsIds): bool
    {
        $gm = $this->stat->getMapField('purchased_goods_map');
        foreach ($goodsIds as $gid) {
            if (isset($gm[(string)$gid]) && $gm[(string)$gid] > 0) return true;
        }
        return false;
    }

    /**
     * 指定商品的累计购买数量
     */
    public function getGoodsPurchaseQty(int $goodsId): int
    {
        $gm = $this->stat->getMapField('purchased_goods_map');
        return (int)($gm[(string)$goodsId] ?? 0);
    }

    // ===== 团队消费维度 =====

    public function getTeamSpend(bool $settled = false): float
    {
        return $settled ? (float)$this->stat->team_spend_settled : (float)$this->stat->team_spend_paid;
    }

    // ===== 月度维度 =====

    public function getMonthPersonalSpend(): float
    {
        return (float)$this->stat->getMonthValue('month_personal_spend');
    }

    public function getMonthTeamSpend(): float
    {
        return (float)$this->stat->getMonthValue('month_team_spend');
    }

    public function getMonthActiveCount(): int
    {
        return (int)$this->stat->getMonthValue('month_active_count');
    }

    // ===== 辅助 =====

    private static $levelWeightCache = [];

    private function getLevelWeight(int $levelId): int
    {
        if (!isset(self::$levelWeightCache[$levelId])) {
            $level = Level::findOne($levelId);
            self::$levelWeightCache[$levelId] = $level ? (int)$level->level : 0;
        }
        return self::$levelWeightCache[$levelId];
    }
}
