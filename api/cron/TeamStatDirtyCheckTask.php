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
use app\events\UserLevelCheckNeededEvent;
use app\models\UserTeamStat;
use Yii;

/**
 * 团队统计脏数据重检任务
 * 扫描 dirty=1 的用户，广播升级检查事件
 * 主框架 + 各插件(dealer/chain等)都能监听并执行自己的升级逻辑
 */
class TeamStatDirtyCheckTask extends Task
{

    public function run($params = null)
    {
        $mallId = Yii::$app->mall->id;
        $now = time();
        $batchSize = 100;
        $processed = 0;

        $dirtyStats = UserTeamStat::find()
            ->where(['mall_id' => $mallId, 'dirty' => 1])
            ->andWhere(['<', 'last_check_at', $now - 300])
            ->orderBy(['last_check_at' => SORT_ASC])
            ->limit($batchSize)
            ->all();

        foreach ($dirtyStats as $stat) {
            try {
                // 统一广播：会员/分销/经销 Listener 自行监听推 Job
                Yii::$app->trigger(UserLevelCheckNeededEvent::NAME, new UserLevelCheckNeededEvent([
                    'user_id' => $stat->user_id,
                    'trigger' => 'dirty_check',
                ]));

                $stat->dirty = 0;
                $stat->last_check_at = $now;
                $stat->save(false, ['dirty', 'last_check_at']);
                $processed++;
            } catch (\Exception $e) {
                Yii::error("[TeamStatDirtyCheck] 用户 {$stat->user_id} 处理失败: " . $e->getMessage());
            }
        }

        if ($processed > 0) {
            Yii::warning("[TeamStatDirtyCheck][Mall:{$mallId}] 处理 {$processed} 条脏记录");
        }
    }

    public function rule()
    {
        return "*/5 * * * *";
    }

    public function description()
    {
        return "【团队统计】脏数据升级重检 (每5分钟，广播至全插件)";
    }
}
