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
use app\helpers\OptionHelper;
use app\models\UserTeamStat;
use app\services\TeamStatOutbox;
use Yii;
use yii\db\Query;

/**
 * 团队统计【健康巡检】——每 10 分钟盯"处理链路是否在正常消费",异常才告警。
 *
 * 只报真问题(不把正常的"待重检"过渡态当异常):
 *   1. dirty 积压超阈值 → DirtyCheckTask 没在跑 / 跟不上(阈值 team_stat_dirty_alert_max,默认 1000);
 *   2. outbox 死信 > 0 → 有事件永久处理失败,需人工;
 *   3. outbox 最老待处理超 N 分钟没消费 → EventOutboxTask 没在跑(仅 outbox 启用时查)。
 *
 * 告警走 Yii::error(tag=team_stat_health),接入现有日志告警即可;
 * 需要站内信/群通知可在 alert() 里补(留了统一出口)。
 */
class TeamStatHealthTask extends Task
{
    const DIRTY_BACKLOG_DEFAULT = 1000;        // dirty 积压默认阈值(健康时 DirtyCheck 每5分钟清100,远低于此)
    const OUTBOX_PENDING_STUCK_MIN = 15;       // outbox 最老待处理超此分钟数 = worker 没消费

    public function run($params = null)
    {
        $mallId = (int)Yii::$app->mall->id;
        $now = time();
        $problems = [];

        // 1. dirty 积压(DirtyCheckTask 消费不过来 / 没在跑)
        $dirtyMax = (int)(OptionHelper::get('team_stat_dirty_alert_max', $mallId) ?: self::DIRTY_BACKLOG_DEFAULT);
        $dirtyTotal = (int)UserTeamStat::find()->where(['mall_id' => $mallId, 'dirty' => 1])->count();
        if ($dirtyTotal >= $dirtyMax) {
            $problems[] = "dirty 积压 {$dirtyTotal} 条(阈值 {$dirtyMax})—— TeamStatDirtyCheckTask 没在跑或消费跟不上";
        }

        // 2 & 3. outbox(仅启用时查)
        if (TeamStatOutbox::enabled($mallId)) {
            $dead = (int)(new Query())->from(TeamStatOutbox::TABLE)
                ->where(['mall_id' => $mallId, 'status' => TeamStatOutbox::STATUS_DEAD])->count();
            if ($dead > 0) {
                $problems[] = "outbox 死信 {$dead} 条 —— 需人工排查(php yii team-stat/outbox status {$mallId})";
            }

            $oldestPending = (int)(new Query())->select('created_at')->from(TeamStatOutbox::TABLE)
                ->where(['mall_id' => $mallId, 'status' => TeamStatOutbox::STATUS_PENDING])
                ->orderBy(['id' => SORT_ASC])->scalar();
            if ($oldestPending > 0) {
                $ageMin = intdiv($now - $oldestPending, 60);
                if ($ageMin >= self::OUTBOX_PENDING_STUCK_MIN) {
                    $problems[] = "outbox 最老待处理已 {$ageMin} 分钟未消费 —— EventOutboxTask 没在跑";
                }
            }
        }

        if ($problems) {
            $this->alert($mallId, $problems);
        }
    }

    /** 告警统一出口(默认写错误日志;需站内信/群通知在此扩展) */
    private function alert(int $mallId, array $problems): void
    {
        Yii::error(
            "[TeamStatHealth][Mall:{$mallId}] 团队统计健康告警:\n  - " . implode("\n  - ", $problems),
            'team_stat_health'
        );
    }

    public function rule()
    {
        return "*/10 * * * *"; // 每 10 分钟巡检
    }

    public function description()
    {
        return "【团队统计】健康巡检(dirty 积压 / outbox 死信 & 卡住 → 告警)";
    }
}
