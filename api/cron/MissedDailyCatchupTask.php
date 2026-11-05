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

use app\core\Manager;
use app\core\Task;
use Yii;

/**
 * 【漏跑补偿·轻量版】关键日任务(catchUpIfMissed=true)错过每日窗口时(调度器宕机等),恢复后补跑一次。
 *
 * 单机场景,不搞分布式:
 *   · 每小时巡检一次;调度器恢复后 1 小时内把漏掉的日任务补齐。
 *   · 判据:Manager 记录的「上次成功完成时间」距今超过任务的 catchUpMaxAgeHours(日任务默认 25h)= 今日窗口已错过。
 *   · 去重①:逐商场会调用本 run 多次 → 每小时只让第一个轮次干活(任务级,与商场无关)。
 *   · 去重②:每个漏跑任务当天只补一次(即便补跑失败也不当天反复触发,靠次日原调度重试)。
 *   · 补跑复用 Manager::run(自带逐商场 + flock),成功后会刷新「上次成功时间」,下轮不再重复。
 */
class MissedDailyCatchupTask extends Task
{
    public $allowMultiInstance = false;

    public function run($params = null)
    {
        // 本任务是【任务级】的,与商场无关;逐商场会调多次 → 每小时只让一个轮次干活
        $tickKey = 'cron:catchup:tick:' . date('YmdH');
        if (!Yii::$app->redis->executeCommand('SET', [$tickKey, 1, 'NX', 'EX', 3300])) {
            return;
        }

        $manager = new Manager();
        $now = time();

        foreach ($manager->getJobs() as $task) {
            /** @var Task $task */
            if (empty($task->catchUpIfMissed)) {
                continue;
            }

            $jobId = $manager->generateJobId(get_class($task), 'run');
            $lastOk = $manager->getLastRun($jobId);
            $maxAge = ((int)($task->catchUpMaxAgeHours ?: 25)) * 3600;

            // 近期成功过 = 没漏
            if ($lastOk > 0 && ($now - $lastOk) < $maxAge) {
                continue;
            }

            // 漏跑:当天只补一次
            $dayKey = 'cron:catchup:' . $jobId . ':' . date('Ymd');
            if (!Yii::$app->redis->executeCommand('SET', [$dayKey, 1, 'NX', 'EX', 86400])) {
                continue;
            }

            $ageDesc = $lastOk > 0 ? round(($now - $lastOk) / 3600, 1) . 'h前' : '无记录';
            Yii::warning("[漏跑补偿] {$jobId} 上次成功={$ageDesc}(阈值{$task->catchUpMaxAgeHours}h)→ 补跑一次", 'crontab');

            // 补跑复用 Manager::run(逐商场 + flock);它会在内部把 Yii::$app->mall 置空,补跑后还原,避免影响本任务后续逻辑
            $savedMall = Yii::$app->mall;
            try {
                $manager->run($jobId);
            } catch (\Throwable $e) {
                Yii::error("[漏跑补偿] {$jobId} 补跑异常: " . $e->getMessage(), 'crontab');
            } finally {
                Yii::$app->mall = $savedMall;
            }
        }
    }

    public function rule()
    {
        return '15 * * * *'; // 每小时第 15 分钟巡检
    }

    public function description()
    {
        return '【漏跑补偿】关键日任务错过每日窗口(调度器宕机等)时恢复后补跑一次';
    }
}
