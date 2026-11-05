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

namespace app\commands;

use app\helpers\OptionHelper;
use app\services\TeamStatOutbox;
use yii\console\Controller;
use yii\db\Query;

/**
 * 团队统计运维命令。
 *
 * Outbox 灰度阀门 + 观察(平台侧逐商场放量用,不做后台页):
 *   php yii team-stat/outbox status <mallId>   查看开关 + 积压/死信
 *   php yii team-stat/outbox on <mallId>       开启(改走 outbox 可靠增量)
 *   php yii team-stat/outbox off <mallId>      关闭(一键回退到直连增量)
 */
class TeamStatController extends Controller
{
    public function actionOutbox($op = 'status', $mallId = 0)
    {
        $mallId = (int)$mallId;
        if ($mallId <= 0) {
            $this->stderr("用法: php yii team-stat/outbox <status|on|off> <mallId>\n");
            return;
        }

        switch ($op) {
            case 'on':
                OptionHelper::set('team_stat_outbox_disabled', 0, $mallId); // 清除禁用标志(默认即开)
                $this->stdout("✓ Mall {$mallId}: outbox 已【开启】—— 订单/等级变更改走 outbox,EventOutboxTask 可靠消费。\n");
                $this->showStatus($mallId);
                break;
            case 'off':
                OptionHelper::set('team_stat_outbox_disabled', 1, $mallId); // 置禁用标志,单独关闭该商城
                $this->stdout("✓ Mall {$mallId}: outbox 已【关闭】—— 回退到直连增量。\n");
                $this->showStatus($mallId);
                break;
            case 'status':
            default:
                $this->showStatus($mallId);
        }
    }

    private function showStatus(int $mallId)
    {
        $enabled = TeamStatOutbox::enabled($mallId);
        $t = TeamStatOutbox::TABLE;
        $cnt = function (int $status) use ($t, $mallId) {
            return (int)(new Query())->from($t)->where(['mall_id' => $mallId, 'status' => $status])->count();
        };
        $pending = $cnt(TeamStatOutbox::STATUS_PENDING);
        $done    = $cnt(TeamStatOutbox::STATUS_DONE);
        $dead    = $cnt(TeamStatOutbox::STATUS_DEAD);
        $oldest = (new Query())->select('created_at')->from($t)
            ->where(['mall_id' => $mallId, 'status' => TeamStatOutbox::STATUS_PENDING])
            ->orderBy(['id' => SORT_ASC])->scalar();

        $this->stdout("\n── Mall {$mallId} · Outbox 状态 ──\n");
        $this->stdout("  开关     : " . ($enabled ? '✅ 开' : '⚪ 关') . "\n");
        $this->stdout("  待处理   : {$pending}" . ($oldest ? "  (最老 " . date('m-d H:i:s', (int)$oldest) . ")" : "") . "\n");
        $this->stdout("  已完成   : {$done}\n");
        $this->stdout("  死信     : {$dead}" . ($dead > 0 ? "  ⚠ 需人工排查" : "") . "\n");

        if ($dead > 0) {
            $this->stdout("\n  最近死信(前 5 条):\n");
            $rows = (new Query())->select(['id', 'type', 'attempts', 'last_error'])->from($t)
                ->where(['mall_id' => $mallId, 'status' => TeamStatOutbox::STATUS_DEAD])
                ->orderBy(['id' => SORT_DESC])->limit(5)->all();
            foreach ($rows as $r) {
                $this->stdout("    #{$r['id']} {$r['type']} attempts={$r['attempts']}: " . mb_substr((string)$r['last_error'], 0, 80) . "\n");
            }
        }

        if (!$enabled && $pending > 0) {
            $this->stdout("\n  ⚠ 开关关着但有待处理积压:worker 空转不消费,先 `on` 或人工清理。\n");
        }
        $this->stdout("\n");
    }
}
