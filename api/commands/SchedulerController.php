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

use Yii;
use yii\console\Controller;
use yii\helpers\Console;

class SchedulerController extends Controller
{
    /**
     * [Go 调度器专用] 以 CLI 执行一个 cron 任务。mode=cli 的任务由 Go 调度器 exec 本命令运行,
     * 绕开 FastCGI 30s 硬超时、且不占 fpm worker。与 FastCGI 入口 ScheduleController::handleCronTask
     * 同一套:构 jobId → Manager::run(逐商城、flock 防重入)。
     *   用法: php yii scheduler/run-cron '<Class>' '<method>'
     * 退出码:0=成功,1=失败(供 Go 判定成败并写日志)。
     *
     * @param string $class  任务类全名(如 app\cron\TeamStatBatchRebuildTask)
     * @param string $method 方法名(默认 run)
     */
    public function actionRunCron($class, $method = 'run')
    {
        $jobId = str_replace('\\', '.', $class) . '@' . $method;
        try {
            $ok = (new \app\core\Manager())->run($jobId);
        } catch (\Throwable $e) {
            $this->stderr("EXCEPTION {$jobId}: " . $e->getMessage() . "\n", Console::FG_RED);
            return 1;
        }
        $this->stdout(($ok ? '✅ OK ' : '❌ FAILED ') . $jobId . "\n");
        return $ok ? 0 : 1;
    }

    /**
     * [Reload] 重载调度器
     * 顺序：1. 同步Cron配置 -> 2. 发送信号给所有Go进程
     */
    public function actionReload()
    {
        // 1. 调用本地的 crontab/sync
        $this->stdout("🔄 步骤 1: 正在同步 Cron 配置到 Redis...\n", Console::FG_YELLOW);
        try {
            // 参数为空数组表示不传参，runAction 会返回执行的状态码（通常 0 为成功）
            $result = Yii::$app->runAction('crontab/sync', []);
            if ($result !== 0) {
                $this->stderr("❌ Cron 同步失败，停止重载进程。\n", Console::FG_RED);
                return Controller::EXIT_CODE_ERROR;
            }
            $this->stdout("✅ Cron 配置同步完成。\n", Console::FG_GREEN);
        } catch (\Exception $e) {
            $this->stderr("❌ 调用 crontab/sync 出错: " . $e->getMessage() . "\n", Console::FG_RED);
            return Controller::EXIT_CODE_ERROR;
        }

        // 2. 查找并重启 Go 进程
        $this->stdout("🔄 步骤 2: 正在通知 Go 进程重启...\n", Console::FG_YELLOW);
        $runtimePath = Yii::getAlias('@app/runtime');
        $pidFiles = glob($runtimePath . DIRECTORY_SEPARATOR . 'scheduler_*_db*.pid');

        if (empty($pidFiles)) {
            $this->stdout("⚪ 未发现运行中的 PID 文件，无需重启进程。\n", Console::FG_CYAN);
            return Controller::EXIT_CODE_NORMAL;
        }

        $count = 0;
        foreach ($pidFiles as $file) {
            $pid = (int)trim(file_get_contents($file));
            if ($pid > 0 && posix_kill($pid, 0)) {
                if (posix_kill($pid, SIGTERM)) {
                    $this->stdout("👉 已向 PID {$pid} 发送 SIGTERM 信号。\n");
                    $count++;
                }
            } else {
                // 如果进程已经不在了，顺手清理掉无效的 PID 文件
                @unlink($file);
            }
        }

        if ($count > 0) {
            $this->stdout("✅ 成功重载 {$count} 个实例。Supervisor 会自动将其拉起。\n", Console::FG_GREEN);
        } else {
            $this->stdout("⚪ 没有存活的进程需要重载。\n", Console::FG_CYAN);
        }

        return Controller::EXIT_CODE_NORMAL;
    }

    /**
     * [Status] 查看状态 (复用之前的逻辑)
     */
    public function actionStatus()
    {
        $runtimePath = Yii::getAlias('@app/runtime');
        $pidFiles = glob($runtimePath . DIRECTORY_SEPARATOR . 'scheduler_*_db*.pid');

        if (empty($pidFiles)) {
            $this->stdout("⚪ Scheduler 未运行。\n", Console::FG_RED);
            return;
        }

        $this->stdout(str_pad("PID 文件", 40) . str_pad("进程号", 10) . "状态\n", Console::FG_YELLOW);
        $this->stdout(str_repeat("-", 60) . "\n");

        foreach ($pidFiles as $file) {
            $pid = trim(file_get_contents($file));
            if ($pid && posix_kill((int)$pid, 0)) {
                $status = Console::ansiFormat("🟢 运行中", [Console::FG_GREEN]);
                $this->stdout(str_pad(basename($file), 40) . str_pad($pid, 10) . "{$status}\n");
            } else {
                $status = Console::ansiFormat("🔴 已停止", [Console::FG_RED]);
                $this->stdout(str_pad(basename($file), 40) . str_pad($pid, 10) . "{$status}\n");
            }
        }
    }
}