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

namespace app\plugins\marketing\cron;

use app\core\Task;
use app\models\Mall;
use app\plugins\marketing\models\MarketingBatchTask;
use app\plugins\marketing\services\BatchService;
use Yii;

/**
 * 定时群发到点分批发放(二期)。
 * 每几分钟扫一次:计划时刻已到、未完成的群发任务,推进一批。
 */
class MarketingBatchDispatchTask extends Task
{
    public function run($params = null)
    {
        $now = time();
        $tasks = MarketingBatchTask::find()
            ->where(['status' => [MarketingBatchTask::STATUS_PENDING, MarketingBatchTask::STATUS_RUNNING], 'is_delete' => 0])
            ->andWhere(['<=', 'plan_at', $now])
            ->orderBy('id ASC')
            ->limit(50)
            ->all();

        $total = 0;
        foreach ($tasks as $task) {
            // 设置任务所属商场上下文(发券链路兜底依赖 Yii::$app->mall)
            $mall = Mall::findOne(['id' => $task->mall_id, 'is_delete' => 0]);
            if (!$mall) {
                continue;
            }
            Yii::$app->set('mall', $mall);
            $total += BatchService::dispatch($task);
        }
        if ($tasks) {
            Yii::info("[营销活动] 群发调度完成,推进 " . count($tasks) . " 个任务,发券 {$total} 张");
        }
        return $total;
    }

    public function rule()
    {
        return "*/5 * * * *"; // 每5分钟推进一次
    }

    public function description()
    {
        return "营销活动-定时群发分批调度";
    }
}
