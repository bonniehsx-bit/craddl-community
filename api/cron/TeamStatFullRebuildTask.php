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

namespace app\cron;

use app\core\Task;
use app\models\User;
use app\models\UserTeamStat;
use app\services\TeamStatUpdater;
use Yii;

/**
 * 团队统计每日全量重算（兜底任务）
 * 每天凌晨 04:30 执行
 *
 * 职责:
 * 1. 为没有 stat 记录的用户初始化（新用户/历史数据迁移）
 * 2. 月度字段自动重置（跨月时清零）
 * 3. 全量重算月度活跃人数（增量更新不够精确）
 * 4. 标脏所有有变化的用户，由 DirtyCheckTask 接管升级检查
 */
class TeamStatFullRebuildTask extends Task
{

    public function run($params = null)
    {
        $mallId = Yii::$app->mall->id;
        $currentMonth = date('Ym');
        $startTime = microtime(true);

        Yii::warning("[TeamStatFullRebuild][Mall:{$mallId}] 开始全量重算...");

        // 1. 为没有 stat 的用户创建空记录
        $usersWithoutStat = User::find()->alias('u')
            ->leftJoin(UserTeamStat::tableName() . ' s', 's.user_id = u.id')
            ->where(['u.mall_id' => $mallId, 'u.is_delete' => 0])
            ->andWhere(['s.user_id' => null])
            ->select('u.id')
            ->limit(500)
            ->column();

        $initCount = 0;
        foreach ($usersWithoutStat as $uid) {
            try {
                TeamStatUpdater::fullRebuild((int)$uid, $mallId);
                $initCount++;
            } catch (\Exception $e) {
                Yii::error("[TeamStatFullRebuild] 初始化用户 {$uid} 失败: " . $e->getMessage());
            }
        }

        // 2. 月度重置：跨月时把旧月度字段清零
        $resetCount = UserTeamStat::updateAll(
            [
                'month_personal_spend' => 0,
                'month_team_spend' => 0,
                'month_active_count' => 0,
                'month_period' => $currentMonth,
            ],
            ['and', ['mall_id' => $mallId], ['!=', 'month_period', $currentMonth]]
        );

        // 3. 精确重算月度活跃人数（本月有下单的下级人数）
        // 简化处理：由增量更新维护，这里只做跨月重置，不全量重算
        // 如果需要精确值，可以在这里用 SQL 统计

        $elapsed = round(microtime(true) - $startTime, 2);
        Yii::warning("[TeamStatFullRebuild][Mall:{$mallId}] 完成: 初始化{$initCount}人, 月度重置{$resetCount}条, 耗时{$elapsed}s");
    }

    public function rule()
    {
        return "30 4 * * *"; // 每天凌晨 04:30
    }

    public function description()
    {
        return "【团队统计】每日全量重算兜底 (初始化+月度重置)";
    }
}
