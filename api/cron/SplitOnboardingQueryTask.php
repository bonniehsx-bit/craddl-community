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
use app\models\SplitOnboardingLog;
use app\models\User;
use Yii;

class SplitOnboardingQueryTask extends Task
{
    /**
     * 定时任务规则
     * 每10分钟执行一次
     * @return string
     */
    public function rule()
    {
        return '*/10 * * * *';
    }

    /**
     * 任务描述
     * @return string
     */
    public function description()
    {
        return "分账进件状态【定时轮询】任务";
    }

    /**
     * 运行定时任务
     * @param mixed|null $params
     * @return void
     */
    public function run($params=null)
    {

        Yii::warning("--- [定时任务] 开始执行分账进件状态查询 ---");
        // 核心查询：找出所有还在处理中的进件日志 (wait 或 doing)
        $query = SplitOnboardingLog::find()->where([
            'is_delete' => 0
        ])->andWhere([
            'in', 'status', [SplitOnboardingLog::STATUS_WAIT, SplitOnboardingLog::STATUS_DOING, SplitOnboardingLog::STATUS_FAILED]
        ])->andWhere(['>', 'created_at', time() - 60 * 60 * 24 * 6]);
        $totalCount = $query->count();
        if ($totalCount == 0) {
            Yii::warning("[定时任务] 未发现需要查询状态的进件记录。");
            Yii::warning("--- [定时任务] 查询任务执行完毕 ---");
            return;
        }

        Yii::warning("[定时任务] 发现 {$totalCount} 条待查询的进件记录。");

        $successCount = 0;
        $failCount = 0;
        $updatedCount = 0;

        foreach ($query->batch(50) as $logs) {
            /** @var SplitOnboardingLog[] $logs */
            foreach ($logs as $log) {
                try {
                    /** @var \app\split\BaseSplit $splitInstance */
                    $splitInstance = new $log->split_class($log->mall_id);
                    $queryResult = $splitInstance->merchantRegisterQuery(['h5RegisterFlow' => $log->h5_register_flow]);
                    $apiStatus = $queryResult['status'] ?? null;
                    $statusMap = [
                        'SUCCESS' => SplitOnboardingLog::STATUS_SUCCESS,
                        'FAIL' => SplitOnboardingLog::STATUS_FAILED,
                        'DOING' => SplitOnboardingLog::STATUS_DOING,
                        'WAIT' => SplitOnboardingLog::STATUS_WAIT,
                    ];
                    // 如果API返回的状态与我们数据库中的状态不同，则更新
                    if (isset($statusMap[$apiStatus]) && $statusMap[$apiStatus] !== $log->status) {
                        Yii::warning("[定时任务] 日志 #{$log->id} 状态已变更: {$log->status} -> {$statusMap[$apiStatus]}");
                        $log->status = $statusMap[$apiStatus];
                        $log->remark = ($queryResult['desc'] ?? '状态由定时任务更新');
                        if ($log->status === SplitOnboardingLog::STATUS_SUCCESS) {
                            $log->platform_user_id = $queryResult['userId'] ?? null;
                            $log->application_id = $queryResult['applicationId'] ?? null;
                            User::updateAll(['application_id' => $log->application_id, 'other_platform_user_id' => $log->platform_user_id], ['id' => $log->user_id]);
                        }
                        if ($log->save()) {
                            $updatedCount++;
                        }
                    }
                    $successCount++;
                } catch (\Exception $e) {
                    $failCount++;
                    Yii::error("[定时任务] 查询日志 #{$log->id} 失败: " . $e->getMessage());
                }
            }
        }

        Yii::warning("[定时任务] 处理完毕。成功查询: {$successCount} 条, 更新: {$updatedCount} 条, 失败: {$failCount} 条。");
        Yii::warning("--- [定时任务] 查询任务执行完毕 ---");
    }
}
