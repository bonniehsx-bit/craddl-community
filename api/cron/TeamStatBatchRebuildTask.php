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
use app\services\TeamStatBatchRebuilder;
use Yii;

/**
 * 团队统计【自底向上批量对账】—— 方案 A(每天一次)。
 *
 * 用一趟 O(N + 订单数) 的批量重算,替代 per-user 逐个重扫子树的反熵扫描(TeamStatScrubTask)。
 * 增量实时链路不动;本任务只做周期性对账,纠正增量丢事件造成的漂移。
 *
 * 上线建议:先与老 Scrub 并行跑几天、对拍 user_team_stat 一致后,再把 TeamStatScrubTask 降频/退役。
 */
class TeamStatBatchRebuildTask extends Task
{
    public function run($params = null)
    {
        // 批量在内存里持有单商城全量累加器,放宽内存上限(逐商城,千万级再按分片切)
        @ini_set('memory_limit', '512M');


        $mallId = (int)Yii::$app->mall->id;
        Yii::warning("[TeamStatBatchRebuild][Mall:{$mallId}] 开始自底向上批量对账...");
        try {
            $r = TeamStatBatchRebuilder::run($mallId);
            Yii::warning("[TeamStatBatchRebuild][Mall:{$mallId}] 完成: 用户 {$r['users']} 人, 纠正漂移 {$r['changed']} 人, 耗时 {$r['elapsed']}s");
        } catch (\Throwable $e) {
            Yii::error("[TeamStatBatchRebuild][Mall:{$mallId}] 失败: " . $e->getMessage());
        }
    }

    /** 重批处理:走 CLI(命令行 php yii),绕开调度器 30s FastCGI 硬超时。
     *  一趟自底向上滚算无法按游标分片(父节点需整棵下级算完),只能整趟跑完,故必须 cli。 */
    public function runMode()
    {
        return 'cli';
    }

    public function rule()
    {
         return "20 3 * * *"; // 每天凌晨 03:20
    }

    public function description()
    {
        return "【团队统计】自底向上一趟批量对账(方案A,替代 per-user 反熵扫描)";
    }
}
