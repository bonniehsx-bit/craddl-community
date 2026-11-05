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

use app\core\Manager;
use app\core\Task;
use Yii;
use yii\console\Controller;
use yii\helpers\ArrayHelper;
use yii\helpers\Console;

class  CrontabController extends Controller
{
    // Redis Key 必须与 Go config.yaml 中的 cron_key 一致
    const REDIS_KEY = 'jxmall_cron_config';

    protected function getManager()
    {
        return new Manager();
    }

    /**
     * [Sync] 同步定时任务配置到 Redis (供 Go 调度器使用)
     * 命令: php yii crontab/sync
     */
    public function actionSync()
    {
        $this->stdout("正在扫描定时任务并合并后台覆盖配置...\n", Console::FG_YELLOW);

        // 扫码发现 + 合并 jxmall_cron_setting 覆盖 + 写 Redis，逻辑统一在 CronTaskService
        $count = \app\services\CronTaskService::syncToRedis();

        $this->stdout("\n✅ 同步成功!\n", Console::FG_GREEN);
        $this->stdout("----------------------------------------------\n");
        $this->stdout(sprintf(" Redis Key : %s\n", self::REDIS_KEY));
        $this->stdout(sprintf(" 生效任务  : %d\n", $count));
        $this->stdout("----------------------------------------------\n");
        $this->stdout("👉 Go Scheduler 的 Watcher 将在 10s 内自动热加载。\n\n");
    }


    /**
     * [List] 列出所有定时任务
     */
    public function actionList($keyword = '')
    {
        $manager = $this->getManager();
        $jobs = $manager->getJobs();

        // 1. 收集所有任务数据
        $rows = [];
        foreach ($jobs as $job) {
            /** @var Task $job */
            $fullClass = get_class($job);
            $desc = $job->description();
            $rules = $job->rule();
            $enabled = $job->enable();
            // 展示是否允许多实例并发 + 启用状态
            $multi = $job->allowMultiInstance ? 'Yes' : 'No';
            if (!$enabled) $multi .= " (OFF)";

            // 调度方式(类级 runMode(),与 CronTaskService::syncToRedis 同源)。
            // 后台 jxmall_cron_setting 只覆盖 enabled/expression,不覆盖 mode,故此处即为生效值。
            $mode = method_exists($job, 'runMode') ? strtolower((string)$job->runMode()) : 'fpm';

            // 关键词过滤
            if ($keyword && stripos($fullClass, $keyword) === false && stripos(json_encode($desc), $keyword) === false) {
                continue;
            }

            if (is_string($rules)) {
                $jobId = $manager->generateJobId($fullClass, 'run');
                $rows[] = [
                    'id' => $jobId,
                    'rule' => $rules,
                    'mode' => $mode,
                    'multi' => $multi,
                    'desc' => $this->parseDescription($desc)
                ];
            } elseif (is_array($rules)) {
                foreach ($rules as $method => $rule) {
                    $spec = is_string($rule) ? $rule : ArrayHelper::getValue($rule, 'spec');
                    $jobId = $manager->generateJobId($fullClass, $method);
                    $rows[] = [
                        'id' => $jobId,
                        'rule' => $spec,
                        'mode' => $mode,
                        'multi' => $multi,
                        'desc' => $this->parseDescription($desc, $method)
                    ];
                }
            }
        }

        if (empty($rows)) {
            $this->stdout("No tasks found.\n", Console::FG_YELLOW);
            return;
        }

        // 2. 计算各列最大宽度
        // 注意:此数组顺序即为列顺序(分隔线/表头/内容都按它遍历)
        $colWidths = [
            'id' => 10, // 最小宽度
            'rule' => 10,
            'mode' => 9,
            'multi' => 10,
            'desc' => 20
        ];

        foreach ($rows as $row) {
            $colWidths['id'] = max($colWidths['id'], strlen($row['id']));
            $colWidths['rule'] = max($colWidths['rule'], strlen($row['rule']));
            $colWidths['mode'] = max($colWidths['mode'], strlen($row['mode']));
            $colWidths['multi'] = max($colWidths['multi'], strlen($row['multi']));
            // 处理多字节字符宽度 (中文描述)
            $descLen = mb_strwidth($row['desc'], 'UTF-8');
            $colWidths['desc'] = max($colWidths['desc'], $descLen);
        }

        // 增加一点 padding
        foreach ($colWidths as &$w) {
            $w += 2;
        }
        // 必须断开引用:否则下面 `foreach ($colWidths as $w)` 每次迭代都会把当前列宽写回
        // $colWidths['desc'](PHP foreach 按引用的经典坑),导致 desc 列宽被最后一列覆盖、表格错位。
        unset($w);


        // 3. 构建表格分割线
        $separator = "+";
        foreach ($colWidths as $w) {
            $separator .= str_repeat("-", $w) . "+";
        }
        $separator .= "\n";


        // 4. 输出表头
        $this->stdout($separator);
        $header = "|";
        $header .= str_pad(" Task ID (Class@Method) ", $colWidths['id'], " ", STR_PAD_BOTH) . "|";
        $header .= str_pad(" Rule ", $colWidths['rule'], " ", STR_PAD_BOTH) . "|";
        $header .= str_pad(" RunMode ", $colWidths['mode'], " ", STR_PAD_BOTH) . "|";
        $header .= str_pad(" MultiInst ", $colWidths['multi'], " ", STR_PAD_BOTH) . "|";
        $header .= str_pad(" Description ", $colWidths['desc'], " ", STR_PAD_BOTH) . "|";
        $this->stdout($header . "\n");
        $this->stdout($separator);

        // 5. 输出内容
        foreach ($rows as $row) {
            $line = "|";
            // ID 左对齐
            $line .= " " . str_pad($row['id'], $colWidths['id'] - 1) . "|";
            // Rule 居中
            $line .= str_pad(" " . $row['rule'] . " ", $colWidths['rule'], " ", STR_PAD_BOTH) . "|";
            // RunMode 居中
            $line .= str_pad(" " . $row['mode'] . " ", $colWidths['mode'], " ", STR_PAD_BOTH) . "|";
            // Multi 居中
            $line .= str_pad(" " . $row['multi'] . " ", $colWidths['multi'], " ", STR_PAD_BOTH) . "|";

            // Desc 左对齐 (注意处理中文字符填充)
            $descStr = " " . $row['desc'];
            // 计算需要填充的空格数 = 总宽度 - 实际显示宽度
            $paddingLen = $colWidths['desc'] - mb_strwidth($descStr, 'UTF-8') - 1;
            if ($paddingLen < 0) $paddingLen = 0;
            $line .= $descStr . str_repeat(" ", $paddingLen) . " |";

            $this->stdout($line . "\n");
        }
        $this->stdout($separator);

        $cliCount = count(array_filter($rows, function ($r) {
            return $r['mode'] === 'cli';
        }));
        $fpmCount = count($rows) - $cliCount;

        $this->stdout("Total found: " . count($rows) . " tasks.  (fpm: {$fpmCount}, cli: {$cliCount})\n");
        $this->stdout("RunMode: fpm = Go 调度器经 FastCGI 调用,受 30s 硬超时;cli = 命令行 php yii,无超时、不占 fpm worker。\n", Console::FG_GREY);
        $this->stdout("         在 Task 子类覆盖 runMode() 返回 'cli' 即可切换(后台 cron_setting 不覆盖该项)。\n", Console::FG_GREY);
    }

    /**
     * [Run] 手动执行任务 (支持交互式上下键选择)
     * 模式1: php yii crontab/run
     * 模式2: php yii crontab/run ClearFdTask
     */
    public function actionRun()
    {
        $manager = $this->getManager();
        $args = func_get_args();
        $jobId = '';

        // --- 场景 A: 传入了参数，尝试直接解析 ---
        if (count($args) > 0) {
            $inputName = array_shift($args);
            $jobId = $this->resolveJobId($manager, $inputName);

            if (!$jobId) {
                $this->stderr("❌ 未找到匹配的任务: {$inputName}\n", Console::FG_RED);
                return;
            }
        } // --- 场景 B: 未传参数，进入交互式菜单 (Up/Down Selection) ---
        else {
            // 检查环境是否支持 (Windows CMD 可能不支持 stty)
            if (DIRECTORY_SEPARATOR === '\\') {
                $this->stderr("⚠️ Windows 环境暂不支持交互式选择，请输入参数运行。\n例如: php yii crontab/run TaskName\n", Console::FG_YELLOW);
                return;
            }

            $runnableTasks = $this->getRunnableTasks($manager);
            if (empty($runnableTasks)) {
                $this->stderr("⚠️  没有可用的定时任务。\n", Console::FG_YELLOW);
                return;
            }

            // 启动交互式选择
            $selectedIndex = $this->renderInteractiveMenu($runnableTasks);

            // 如果返回 -1 表示用户取消 (Ctrl+C)
            if ($selectedIndex === -1) {
                $this->stdout("\n已取消。\n");
                return;
            }

            $jobId = $runnableTasks[$selectedIndex]['id'];
        }

        // --- 开始执行 ---
        $this->stdout("\n🚀 正在执行: {$jobId} ...\n", Console::FG_YELLOW);

        $start = microtime(true);
        $result = $manager->run($jobId, $args);
        $time = round(microtime(true) - $start, 4);

        if ($result) {
            $this->stdout("✅ 执行成功! (耗时: {$time}s)\n", Console::FG_GREEN);
        } else {
            $this->stderr("⚠️ 执行失败或被跳过 (请检查日志或 PID 锁)。\n", Console::FG_RED);
        }
    }

    // ----------------------------------------------------
    // 交互式菜单核心逻辑 (Up/Down Selection)
    // ----------------------------------------------------

    /**
     * 渲染交互式菜单并监听按键
     * @param array $tasks
     * @return int 选中的索引，取消返回 -1
     */
    private function renderInteractiveMenu(array $tasks)
    {
        // 1. 设置终端为 Raw 模式 (禁用回显，直接读取输入)
        shell_exec('stty -icanon -echo < /dev/tty');

        $count = count($tasks);
        $current = 0;

        // 隐藏光标
        $this->stdout("\033[?25l");

        // 初始渲染
        $this->drawMenu($tasks, $current);

        while (true) {
            // 读取按键 (3字节序列)
            $c = fread(STDIN, 1);

            // 检查 ANSI 转义序列
            if ($c === "\033") {
                $next = fread(STDIN, 2);
                if ($next === '[A') { // Up Arrow
                    $current--;
                    if ($current < 0) $current = $count - 1;
                } elseif ($next === '[B') { // Down Arrow
                    $current++;
                    if ($current >= $count) $current = 0;
                }
            } elseif ($c === "\n") { // Enter
                break;
            } elseif (ord($c) === 3) { // Ctrl+C
                // 恢复终端并退出
                $this->resetTerminal();
                return -1;
            }

            // 清除之前的菜单并重绘
            // 移动光标回到菜单顶部 (任务数 + 2行标题)
            $lines = $count + 2;
            $this->stdout("\033[{$lines}A");
            $this->drawMenu($tasks, $current);
        }

        // 恢复终端状态
        $this->resetTerminal();
        return $current;
    }

    /**
     * 绘制菜单列表
     */
    private function drawMenu($tasks, $current)
    {
        $this->stdout("📋 请使用 ↑ ↓ 键选择任务，按 Enter 确认:\n\n", Console::FG_CYAN);

        foreach ($tasks as $index => $task) {
            $isCurrent = ($index === $current);

            // 选中项加粗变色，前面加箭头
            if ($isCurrent) {
                $prefix = "👉 ";
                $style = [Console::FG_GREEN, Console::BOLD];
            } else {
                $prefix = "   ";
                $style = [];
            }

            $line = sprintf(
                "%s %-50s %s",
                $prefix,
                $task['id'],
                Console::ansiFormat("(" . $task['desc'] . ")", [Console::FG_GREY])
            );

            if ($isCurrent) {
                $this->stdout($line . "\n", ...$style);
            } else {
                $this->stdout($line . "\n");
            }
        }
    }

    /**
     * 恢复终端显示 (显示光标，恢复回显)
     */
    private function resetTerminal()
    {
        $this->stdout("\033[?25h"); // 显示光标
        shell_exec('stty sane < /dev/tty'); // 恢复终端标准模式
    }

    // ----------------------------------------------------
    // 辅助方法
    // ----------------------------------------------------

    private function getRunnableTasks(Manager $manager)
    {
        $list = [];
        $jobs = $manager->getJobs();

        foreach ($jobs as $job) {
            /** @var Task $job */
            $fullClass = get_class($job);
            $desc = $job->description();
            $rules = $job->rule();

            if (is_string($rules)) {
                $list[] = [
                    'id' => $manager->generateJobId($fullClass, 'run'),
                    'desc' => $this->parseDescription($desc)
                ];
            } elseif (is_array($rules)) {
                foreach ($rules as $method => $rule) {
                    $list[] = [
                        'id' => $manager->generateJobId($fullClass, $method),
                        'desc' => $this->parseDescription($desc, $method)
                    ];
                }
            }
        }
        return $list;
    }

    private function parseDescription($desc, $method = null)
    {
        if (is_string($desc)) return $desc;
        if (is_array($desc)) {
            return isset($desc[$method]) ? $desc[$method] : implode(', ', $desc);
        }
        return '';
    }

    private function resolveJobId(Manager $manager, $inputName)
    {
        if (strpos($inputName, '@') !== false && strpos($inputName, '.') !== false) {
            return $inputName;
        }

        $jobs = $manager->getJobs();
        foreach ($jobs as $job) {
            $shortName = (new \ReflectionClass($job))->getShortName();
            $fullName = get_class($job);

            if ($inputName == $shortName || $inputName == $fullName || strpos($fullName, $inputName) !== false) {
                $rules = $job->rule();
                $method = 'run';
                if (is_array($rules)) {
                    $method = key($rules);
                }
                return $manager->generateJobId($fullName, $method);
            }
        }
        return null;
    }

    private function highlight($text, $keyword)
    {
        if (empty($keyword)) return $text;
        return str_ireplace($keyword, Console::ansiFormat($keyword, [Console::FG_RED]), $text);
    }
}
