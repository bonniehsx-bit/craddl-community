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
/**
 * Created by PhpStorm.
 * User: dujxmall
 * Date: 2022/8/31
 * Time: 10:19
 */

namespace app\cron;


use app\core\Task;

class LogFileTask extends Task
{
    /** 日志保留天数:超过该天数的历史日志文件自动清理 */
    const RETAIN_DAYS = 10;

    /**
     * 运行定时任务
     * @param mixed $params
     * @return mixed
     */
    public function run($params=null)
    {
        // TODO: Implement run() method.

        $file = \Yii::$app->runtimePath . '/logs/info/' . date('Y-m-d', time() + (1 * 24 * 60 * 60)) . '.log';
        if (!file_exists($file)) {
            $stream = fopen($file, "w");
            if (file_exists($file)) {
                if (chown($file, 'www')) {
                    chmod($file, 0755);
                }
            }
            fclose($stream);
        }
        $file = \Yii::$app->runtimePath . '/logs/' . date('Y-m-d', time() + (1 * 24 * 60 * 60)) . '.log';
        if (!file_exists($file)) {
            $stream = fopen($file, "w");
            if (file_exists($file)) {
                if (chown($file, 'www')) {
                    chmod($file, 0755);
                }
            }
            fclose($stream);
        }
        $file = \Yii::$app->runtimePath . '/logs/errors/' . date('Y-m-d', time() + (1 * 24 * 60 * 60)) . '.log';
        if (!file_exists($file)) {
            $stream = fopen($file, "w");
            if (file_exists($file)) {
                if (chown($file, 'www')) {
                    chmod($file, 0755);
                }
            }
            fclose($stream);
        }

        // 生成完当天/次日日志后,清理超过保留天数的历史日志,避免磁盘无限增长
        $this->cleanupOldLogs();
    }

    /**
     * 清理超过 RETAIN_DAYS 天的历史日志文件。
     * 仅删除按日期命名的日志(YYYY-MM-DD.log 及其轮转文件 .log.N),
     * 按【文件名里的日期】判断,天然跳过 migrate.log / .DS_Store 等非日期文件。
     * 清理失败只告警,不影响日志生成主流程。
     */
    private function cleanupOldLogs(): void
    {
        try {
            $baseDir = \Yii::$app->runtimePath . '/logs';
            $dirs = [$baseDir, $baseDir . '/info', $baseDir . '/errors'];
            // 保留今天往前 RETAIN_DAYS 天,更早的删除
            $cutoff = date('Y-m-d', time() - self::RETAIN_DAYS * 24 * 60 * 60);
            $deleted = 0;
            foreach ($dirs as $dir) {
                if (!is_dir($dir)) {
                    continue;
                }
                $files = @scandir($dir);
                if (!is_array($files)) {
                    continue;
                }
                foreach ($files as $name) {
                    // 只认「日期.log」及其轮转文件,其余(migrate.log/.DS_Store 等)一律不动
                    if (!preg_match('/^(\d{4}-\d{2}-\d{2})\.log(\.\d+)?$/', $name, $m)) {
                        continue;
                    }
                    // YYYY-MM-DD 字符串比较即时间比较,保留期内跳过
                    if ($m[1] >= $cutoff) {
                        continue;
                    }
                    $path = $dir . '/' . $name;
                    if (is_file($path) && @unlink($path)) {
                        $deleted++;
                    }
                }
            }
            if ($deleted > 0) {
                \Yii::info('[LogFileTask] 已清理超过 ' . self::RETAIN_DAYS . ' 天的日志文件 ' . $deleted . ' 个', 'crontab');
            }
        } catch (\Throwable $e) {
            \Yii::warning('[LogFileTask] 清理历史日志失败: ' . $e->getMessage(), 'crontab');
        }
    }

    /**
     * 定时任务规则
     *
     * 单条规则格式
     * ```
     * return 1 1 * * *
     * ```
     * 多条规则格式
     * ```
     * return [
     *      'method1'=>'* * * * *',
     *      'method2'=>'* * * * *',
     * ];
     *
     * return [
     *     'method1'=>[
     *          'spec'=>'* * * * *',
     *          'single'=>true          //这个方法同一时间只允许一个进程存在
     *      ]
     * ]
     * ```
     * @return mixed
     */
    public function rule()
    {
        // TODO: Implement rule() method.
        return "30 04 */1 * *";
    }

    /**
     * 任务描述
     * @return mixed
     */
    public function description()
    {
        // TODO: Implement description() method.
        return "日志文件生成 + 历史日志清理任务";
    }
}