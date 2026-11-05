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

namespace app\forms\admin;

use app\core\ApiCode;
use app\helpers\JobHelper;
use app\helpers\RedisHelper;
use app\models\Admin;
use app\models\BaseModel;
use app\models\ScheduleDeadJob;
use app\models\ScheduleJobLog;
use Yii;

/**
 * 调度中心看板（仅超管）
 * 聚合：心跳状态 / 队列深度 / 处理中 / 死信数 / 今日成败 / 近期执行 / 慢任务
 */
class SchedulerDashboardForm extends BaseModel
{
    const PROCESSING_KEY = 'jxmall_scheduler:processing';
    const HEARTBEAT_KEY  = 'jxmall_scheduler:heartbeat';

    public function getDashboard()
    {
        $admin = Yii::$app->admin->identity;
        if (!$admin || $admin->admin_type != Admin::ADMIN_TYPE_SUPER) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '仅超级管理员可查看');
        }

        $redis = Yii::$app->redis;
        $now = time();

        // 心跳
        $beat = (int)RedisHelper::get(self::HEARTBEAT_KEY);
        $beatAgo = $beat > 0 ? ($now - $beat) : null;
        $alive = ($beat > 0 && $beatAgo >= 0 && $beatAgo <= 15);

        // 队列深度 —— 汇总 4 个车道
        $lanes = [
            'high'     => JobHelper::laneKey(JobHelper::LANE_HIGH),
            'normal'   => JobHelper::laneKey(JobHelper::LANE_NORMAL),
            'low'      => JobHelper::laneKey(JobHelper::LANE_LOW),
            'throttle' => JobHelper::laneKey(JobHelper::LANE_THROTTLE),
        ];
        $laneBreakdown = [];
        $queuePending = 0;
        foreach ($lanes as $name => $key) {
            $c = (int)$redis->zcard($key);
            $laneBreakdown[$name] = $c;
            $queuePending += $c;
        }
        $processing = (int)$redis->zcard(self::PROCESSING_KEY);

        // 死信待处理
        $deadPending = (int)ScheduleDeadJob::find()
            ->where(['is_delete' => 0, 'status' => ScheduleDeadJob::STATUS_PENDING])
            ->count();

        // 今日执行统计
        $todayStart = strtotime('today');
        $todayTotal = (int)ScheduleJobLog::find()
            ->where(['is_delete' => 0])
            ->andWhere(['>=', 'executed_at', $todayStart])
            ->count();
        $todaySuccess = (int)ScheduleJobLog::find()
            ->where(['is_delete' => 0, 'success' => 1])
            ->andWhere(['>=', 'executed_at', $todayStart])
            ->count();
        $todayFailed = $todayTotal - $todaySuccess;

        // 近期执行（最新 20 条）
        $recent = ScheduleJobLog::find()
            ->where(['is_delete' => 0])
            ->orderBy('id DESC')
            ->limit(20)
            ->asArray()
            ->all();
        foreach ($recent as &$r) {
            $r['executed_at_text'] = $r['executed_at'] ? date('Y-m-d H:i:s', $r['executed_at']) : '';
        }
        unset($r);

        // 今日慢任务 TOP5
        $slow = ScheduleJobLog::find()
            ->where(['is_delete' => 0])
            ->andWhere(['>=', 'executed_at', $todayStart])
            ->orderBy('duration_ms DESC')
            ->limit(5)
            ->select(['job_class', 'source', 'duration_ms', 'executed_at'])
            ->asArray()
            ->all();
        foreach ($slow as &$s) {
            $s['executed_at_text'] = $s['executed_at'] ? date('H:i:s', $s['executed_at']) : '';
        }
        unset($s);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'alive'         => $alive ? 1 : 0,
            'seconds_ago'   => $beatAgo,
            'queue_pending' => $queuePending,
            'lane_breakdown'=> $laneBreakdown,
            'processing'    => $processing,
            'dead_pending'  => $deadPending,
            'today'         => [
                'total'   => $todayTotal,
                'success' => $todaySuccess,
                'failed'  => $todayFailed,
            ],
            'recent' => $recent,
            'slow'   => $slow,
        ]);
    }
}
