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

namespace app\services;

use app\core\Manager;
use app\models\CronSetting;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * Cron 任务服务 —— 扫码发现 + 覆盖合并 + 同步 Redis
 *
 * 代码定义的 cron（Task::rule()）为默认值，jxmall_cron_setting 表为后台覆盖层。
 * crontab/sync 命令与后台「Cron 管理」均复用本服务。
 */
class CronTaskService
{
    // 与 Go config.yaml 的 cron_key 一致
    const REDIS_KEY = 'jxmall_cron_config';

    /**
     * 扫码发现所有 cron 任务，并合并 DB 覆盖
     *
     * @return array 每项: job_key/class/method/description/code_expression/expression/code_enabled/enabled/overridden
     */
    public static function mergedTasks()
    {
        $manager = new Manager();
        $jobs = $manager->getJobs();

        /** @var CronSetting[] $overrides */
        $overrides = CronSetting::find()
            ->where(['is_delete' => 0])
            ->indexBy('job_key')
            ->all();

        $list = [];
        foreach ($jobs as $job) {
            /** @var \app\core\Task $job */
            $class = get_class($job);
            $codeEnabled = $job->enable() ? 1 : 0;
            $desc = $job->description();
            $rules = $job->rule();
            $mode = method_exists($job, 'runMode') ? (string)$job->runMode() : 'fpm';

            // 统一成 method => expression
            $pairs = [];
            if (is_string($rules)) {
                $pairs['run'] = $rules;
            } elseif (is_array($rules)) {
                foreach ($rules as $method => $rule) {
                    $pairs[$method] = is_string($rule) ? $rule : ArrayHelper::getValue($rule, 'spec');
                }
            }

            foreach ($pairs as $method => $codeExpr) {
                $jobKey = $class . '@' . $method;
                $ov = isset($overrides[$jobKey]) ? $overrides[$jobKey] : null;

                $enabled = $ov ? (int)$ov->enabled : $codeEnabled;
                $expr = ($ov && $ov->expression !== '') ? $ov->expression : $codeExpr;

                $list[] = [
                    'job_key'         => $jobKey,
                    'class'           => $class,
                    'method'          => $method,
                    'description'     => self::parseDescription($desc, $method),
                    'code_expression' => (string)$codeExpr,
                    'expression'      => (string)$expr,
                    'code_enabled'    => $codeEnabled,
                    'enabled'         => $enabled,
                    'mode'            => $mode,
                    'overridden'      => $ov ? 1 : 0,
                ];
            }
        }
        return $list;
    }

    /**
     * 把合并后的启用任务写入 Redis（供 Go 调度器热加载）
     * @return int 写入的任务数
     */
    public static function syncToRedis()
    {
        $tasks = self::mergedTasks();
        $redisTasks = [];
        foreach ($tasks as $t) {
            if (!$t['enabled']) {
                continue;
            }
            $redisTasks[] = [
                'class'       => $t['class'],
                'method'      => $t['method'],
                'expression'  => $t['expression'],
                'description' => $t['description'],
                'mode'        => $t['mode'] ?? 'fpm',
            ];
        }
        Yii::$app->redis->set(self::REDIS_KEY, json_encode($redisTasks));
        return count($redisTasks);
    }

    private static function parseDescription($desc, $method = null)
    {
        if (is_string($desc)) {
            return $desc;
        }
        if (is_array($desc)) {
            return isset($desc[$method]) ? $desc[$method] : implode(', ', $desc);
        }
        return '';
    }
}
