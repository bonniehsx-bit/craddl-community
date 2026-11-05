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

namespace app\core;

use app\models\Mall;
use yii\base\Component;
use yii\helpers\FileHelper;
use Yii;

/**
 * 任务管理器 (配置式注册版:核心 CronRegister + 插件 getCrons(),不再扫描目录)
 */
class Manager extends Component
{
    public $executeUser = 'www'; // 执行用户，用于文件权限
    public $pidDir = '';         // PID 文件目录

    /** flock 锁句柄:key=jobId。持锁期间保持打开;进程退出时 OS 自动释放,消除 PID 残留误判 */
    protected $lockHandles = [];

    /** 任务最近一次成功完成时间的 Redis key 前缀(供 MissedDailyCatchupTask 判断日任务漏跑) */
    const LASTRUN_PREFIX = 'cron:lastok:';

    public function init()
    {
        // 初始化 PID 目录
        if (empty($this->pidDir)) {
            $this->pidDir = Yii::$app->runtimePath . DIRECTORY_SEPARATOR . 'pid';
        }

        // 确保目录存在且有权限
        if (!is_dir($this->pidDir)) {
            try {
                FileHelper::createDirectory($this->pidDir);
                // 尝试修改权限 (在非 root 环境下可能会失败，忽略错误)
                @chown($this->pidDir, $this->executeUser);
                @chgrp($this->pidDir, $this->executeUser);
            } catch (\Exception $e) {
                // 仅记录日志，不阻断流程
                Yii::warning("Init PID dir error: " . $e->getMessage());
            }
        }
        parent::init();
    }

    /**
     * 运行一个任务
     * @param string $jobId 任务ID (类名@方法名)
     * @param array $args 参数
     * @return bool
     */
    public function run($jobId, $args = [])
    {
        // 解析 JobID
        list($instance, $method) = $this->getJobFromId($jobId);
        if ($instance === false) {
            Yii::error("run job[$jobId] error: job class not found", 'crontab');
            return false;
        }

        try {
            // 1. PID 锁检查 (防止单例任务重复运行)
            if (!$this->prepareRun($jobId, $method, $instance)) {
                return false;
            }


            $query = Mall::find()
                ->where(['is_delete' => 0, 'is_stop' => 0, 'is_expire' => 0]);

            // 进入前的上下文,跑完要原样还回去(见下面的 finally)。
            $hadMall = Yii::$app->has('mall');
            $originalMall = $hadMall ? Yii::$app->get('mall') : null;

            try {
                foreach ($query->each(20) as $mall) {
                    /** @var Mall $mall */

                    // 核心：注入全局上下文
                    //
                    // 必须用 set() 而不是 `Yii::$app->mall = $mall`:ServiceLocator::__get 优先查
                    // 组件容器,容器里没有才回落到 getMall()。而 AppBootstrap 在 **Console** 下会无条件
                    // set('mall', 商城1),此时属性赋值只写进 trait 属性、读出来的仍是商城1 —— 逐商场
                    // 循环等于没切。踩这个的是 runMode()='cli' 的任务(OrderPayCheckTask、
                    // TeamStatBatchRebuildTask)和 `php yii crontab/run` 手动执行:它们只跑得到商城1。
                    // Web 路径(Go 派发的 FastCGI 不传 mall_id、容器里没注册)一直是对的,行为不变。
                    Yii::$app->set('mall', $mall);

                    try {
                        // 将 mall 注入参数
                        $params = array_merge($args, ['mall' => $mall]);

                        // 执行任务
                        call_user_func([$instance, $method], $params);

                    } catch (\Throwable $e) {
                        // 捕获单个商城的错误，不影响下一个商城（\Throwable 含 \Error，否则 TypeError 等会逃逸成未捕获致命错误）
                        Yii::error("Cron Mall[{$mall->id}] Job[{$jobId}] Error: " . $e->getMessage(), 'crontab');
                    }
                }
            } finally {
                // 还原,而不是一律置空:Web 请求里 AppBootstrap 已经 set 过 mall,
                // 若跑完 cron 就把它清掉,调用方后续代码会突然拿不到商城。
                // 进入时本就没注册的话 $originalMall 是 null,set(null) 会 unset 定义,
                // 正好回到「未注册」的初始状态。
                Yii::$app->set('mall', $originalMall);
            }
            // 仅【关键日任务】(catchUpIfMissed)需要记录「上次成功」供漏跑补偿判断,其余不必写(省 Redis 写放大)
            if (!empty($instance->catchUpIfMissed)) {
                $this->markLastRun($jobId);
            }
            return true;

        } catch (\Throwable $e) {
            Yii::error("Job '{$jobId}' Global Exception: " . $e->getMessage(), 'crontab');
            return false;
        } finally {
            // 无论成功失败，最后都释放单例锁(flock;进程异常退出 OS 也会自动释放)
            $this->releaseLock($jobId);
        }
    }

    /**
     * 获取所有定时任务实例(配置式:核心 CronRegister + 已安装插件 Plugin::getCrons(),取代目录扫描)。
     * @return Task[]
     */
    public function getJobs()
    {
        // 1. 汇总任务类名:核心(app\cron\CronRegister) + 已安装插件声明(Plugin::getCrons())
        $classes = \app\cron\CronRegister::getCrons();
        foreach (\app\models\Plugin::find()->where(['is_delete' => 0])->all() as $item) {
            $pluginClass = '\\app\\plugins\\' . $item->name . '\\Plugin';
            if (!class_exists($pluginClass)) {
                continue;
            }
            try {
                $crons = (new $pluginClass())->getCrons();
                if (is_array($crons)) {
                    $classes = array_merge($classes, $crons);
                }
            } catch (\Throwable $e) {
                Yii::error("[Cron] 插件 {$item->name} getCrons() 失败: " . $e->getMessage(), 'crontab');
            }
        }

        // 2. 实例化 + 去重 + 校验为非抽象 Task 子类(损坏/误登记不影响整体加载)
        $jobs = [];
        foreach (array_unique($classes) as $className) {
            if (!class_exists($className) || !is_subclass_of($className, Task::class)) {
                Yii::warning("[Cron] 已登记但无法加载(类不存在 / 非 Task 子类): {$className}", 'crontab');
                continue;
            }
            try {
                $jobs[] = Yii::createObject($className);
            } catch (\Throwable $e) {
                Yii::error("[Cron] 实例化任务失败 {$className}: " . $e->getMessage(), 'crontab');
            }
        }
        return $jobs;
    }

    /**
     * 运行前的准备工作 (PID 锁检查 + 进程重命名)
     */
    protected function prepareRun($jobId, $method, $instance)
    {
        if (!method_exists($instance, $method)) {
            Yii::error("Method not found: {$method}", 'crontab');
            return false;
        }

        // 仅在 CLI 模式下修改进程名称
        if (PHP_SAPI === 'cli') {
            // 缩短进程名显示，防止过长
            $title = "yii-cron: " . $jobId;
            if (version_compare(PHP_VERSION, '5.5.0') >= 0) {
                @cli_set_process_title($title);
            } elseif (function_exists('setproctitle')) {
                @setproctitle($title);
            }
        }

        // 允许并发的任务不加锁
        $allowMulti = method_exists($instance, 'getAllowMultiInstance')
            ? $instance->getAllowMultiInstance($method)
            : false;
        if ($allowMulti) {
            return true;
        }

        // 单例锁:flock 独占锁(替代旧的「PID 文件 + posix_kill」)。
        //   进程无论以何种方式退出(正常/异常/致命错误/OOM 被杀/kill -9/断电),OS 都会自动释放文件锁,
        //   从根上消除「PID 文件残留 + PID 被复用 → posix_kill 恒真 → 永久误判『仍在运行』而一直 skip」。
        //   (旧实现清理放在 finally,但 finally 覆盖不到 OOM / 致命错误 / SIGKILL,故会残留锁文件。)
        $pidFile = $this->pidDir . DIRECTORY_SEPARATOR . $jobId;
        $fp = @fopen($pidFile, 'c+');
        if ($fp === false) {
            // 锁文件打不开不阻塞业务:退化为无锁执行,优先保证任务能跑
            Yii::warning("Job [$jobId] 无法打开锁文件 {$pidFile},本次不加锁执行", 'crontab');
            return true;
        }
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            // 拿不到锁 = 确有另一实例正在运行(持锁未释放)
            Yii::warning("Job [$jobId] skipped (上一实例仍在运行)。", 'crontab');
            fclose($fp);
            return false;
        }
        // 拿到锁:写入当前 PID 便于观测;句柄保持打开(持锁),结束时由 releaseLock 释放
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string)getmypid());
        fflush($fp);
        $this->lockHandles[$jobId] = $fp;
        return true;
    }

    /**
     * 释放单例锁(flock)。
     * 只解锁、不删除锁文件 —— 删除会引入 unlink 竞态(A 删旧 inode 后,已打开旧 inode 的 B 与锁住新文件的 C
     * 互不排斥而并发跑)。锁文件为固定的每 job 一个,不会累积泄漏;进程若异常退出,OS 也会自动释放锁。
     */
    protected function releaseLock($jobId)
    {
        if (isset($this->lockHandles[$jobId]) && is_resource($this->lockHandles[$jobId])) {
            @flock($this->lockHandles[$jobId], LOCK_UN);
            @fclose($this->lockHandles[$jobId]);
            unset($this->lockHandles[$jobId]);
        }
    }

    /** 记录任务最近一次成功完成时间(Redis;失败不影响任务本身) */
    protected function markLastRun($jobId)
    {
        try {
            Yii::$app->redis->set(self::LASTRUN_PREFIX . $jobId, time());
        } catch (\Throwable $e) {
            // 记录失败不影响业务
        }
    }

    /** 读取任务最近一次成功完成的时间戳(无记录返回 0) */
    public function getLastRun($jobId): int
    {
        try {
            return (int)Yii::$app->redis->get(self::LASTRUN_PREFIX . $jobId);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * 生成 Job ID
     */
    public function generateJobId($jobClass, $method = 'run')
    {
        return str_replace('\\', '.', $jobClass . '@' . $method);
    }

    /**
     * 通过 Job ID 反解出 实例 和 方法名
     */
    public function getJobFromId($jobId)
    {
        $strs = explode('@', $jobId);
        if (count($strs) < 1) return [false, false];

        // 默认方法为 run
        $method = isset($strs[1]) ? $strs[1] : 'run';
        $className = str_replace('.', '\\', $strs[0]);

        if (class_exists($className)) {
            try {
                $class = new \ReflectionClass($className);
                if (!$class->isAbstract() && $class->isSubclassOf('app\core\Task')) {
                    return [Yii::createObject($className), $method];
                }
            } catch (\Exception $e) {
                Yii::error("Instance Job Error: " . $e->getMessage());
            }
        }
        return [false, false];
    }
}