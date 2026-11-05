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

namespace app\plugins\marketing\services;

use app\plugins\marketing\models\MarketingBatchTask;
use app\plugins\marketing\models\MarketingCampaign;
use Yii;

/**
 * 定时群发(二期)
 *
 * 后台创建群发型活动 + 计划时刻 → 生成一条 MarketingBatchTask(status=待发)。
 * cron 到点后分批发放,断点续发(last_user_id 游标),幂等键固定 batch:{taskId}。
 */
class BatchService
{
    /** 单轮处理的用户数上限(cron 每次 tick 处理一批,未完下轮继续) */
    const BATCH_SIZE = 500;

    /**
     * 推进一个群发任务(处理一批用户)。返回本轮实际发放张数。
     */
    public static function dispatch(MarketingBatchTask $task): int
    {
        $campaign = MarketingCampaign::findOne(['id' => $task->campaign_id, 'is_delete' => 0]);
        if (!$campaign) {
            $task->status = MarketingBatchTask::STATUS_FAILED;
            $task->fail_msg = '活动不存在';
            $task->save(false);
            return 0;
        }
        // 活动被暂停/结束则群发中止(不算失败,可后台恢复)
        if ($campaign->status == MarketingCampaign::STATUS_PAUSED) {
            return 0;
        }
        if ($campaign->status == MarketingCampaign::STATUS_ENDED) {
            $task->status = MarketingBatchTask::STATUS_DONE;
            $task->save(false);
            return 0;
        }

        $task->status = MarketingBatchTask::STATUS_RUNNING;
        $task->save(false);

        $rule = $task->getAudience();
        $userIds = AudienceService::userIds($task->mall_id, $rule, (int)$task->last_user_id, self::BATCH_SIZE);

        if (!$userIds) {
            // 无更多用户 → 完成
            $task->status = MarketingBatchTask::STATUS_DONE;
            $task->save(false);
            return 0;
        }

        $granted = 0;
        // 按活动去重:同一活动即使出现多个群发任务,每人也只发一次
        $dedup = 'batch:c' . $campaign->id;
        foreach ($userIds as $uid) {
            $n = GrantService::grant($campaign->id, $uid, 'batch', $dedup);
            if ($n > 0) {
                $task->total_sent += 1;
                $granted += $n;
            }
            $task->last_user_id = $uid;
        }
        $task->save(false);

        Yii::info("[营销活动] 群发任务#{$task->id} 本轮处理 " . count($userIds) . " 人,发券 {$granted} 张");
        return $granted;
    }
}
