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
use app\models\DailyStatistic;
use app\models\Mall;
use Yii;

/**
 * 每日访问量统计数据持久化任务
 * 将昨天的 Redis 访问聚合数据持久化存入 MySQL 数据库进行归档展示
 */
class VisitStatisticPersistenceTask extends Task
{
    /** 关键日任务:每日 PV/UV 持久化,漏跑=永久缺当天访问统计,调度器恢复后补跑一次 */
    public $catchUpIfMissed = true;

    /**
     * 定时任务规则 - 每天凌晨 00:10 运行
     * @return string
     */
    public function rule()
    {
        return "10 0 * * *";
    }

    /**
     * @return string
     */
    public function description()
    {
        return "访问量(PV/UV)每日备份持久化";
    }

    /**
     * 运行定时任务
     * @param mixed $params
     * @return void
     */
    public function run($params = null)
    {
        Yii::warning('========== 访问统计持久化任务启动 ==========');
        $redis = Yii::$app->redis;
        // 获取所有商城ID
        $mallIds = Mall::find()->select('id')->column();
        
        // 主要是结算"昨天"的数据
        $yesterday = date('Ymd', strtotime('-1 day'));
        $dbDate = date('Y-m-d', strtotime('-1 day'));

        foreach ($mallIds as $mallId) {
            $pvKey = "jxmall:stat:pv:{$mallId}:{$yesterday}";
            $uvKey = "jxmall:stat:uv:{$mallId}:{$yesterday}";

            $pvCount = (int)$redis->get($pvKey);
            $uvCount = (int)$redis->scard($uvKey);

            if ($pvCount == 0 && $uvCount == 0) {
                // 昨日无数据，跳过
                continue;
            }

            // 获取全部游客记录
            $uvLogs = $redis->smembers($uvKey) ?: [];

            // 持久化入库 (INSERT ON DUPLICATE KEY UPDATE)
            $model = DailyStatistic::findOne(['mall_id' => $mallId, 'date' => $dbDate]);
            if (!$model) {
                $model = new DailyStatistic();
                $model->mall_id = $mallId;
                $model->date = $dbDate;
            }
            $model->pv_count = $pvCount;
            $model->uv_count = $uvCount;
            $model->uv_log = json_encode($uvLogs, JSON_UNESCAPED_UNICODE);
            
            if ($model->save()) {
                Yii::warning("商城[$mallId] 统计 ($dbDate) 持久化成功 - PV:$pvCount UV:$uvCount");
                // 成功入库后，主动清理 Redis 昨日的 Key 释放内存
                $redis->del($pvKey);
                $redis->del($uvKey);
            } else {
                Yii::error("商城[$mallId] 统计 ($dbDate) 持久化失败: " . json_encode($model->errors));
            }
        }
        Yii::warning('========== 访问统计持久化任务结束 ==========');
    }
}
