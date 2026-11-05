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

/**
 * 核心定时任务注册器 —— 取代"扫描 @app/cron 目录"。
 *
 * 与 app\listeners\ListenerRegister 同构:新增 / 删除【核心】定时任务在此登记。
 * 插件任务由各插件 Plugin::getCrons() 声明,统一由 Manager::getJobs() 聚合。
 *
 * ⚠️ 新增核心 Task 后务必在此登记,否则不会被加载调度。
 */
class CronRegister
{
    /** @return string[] 核心 Task 子类完整类名 */
    public static function getCrons(): array
    {
        return [
            AutoCancelOrderTask::class,
            AutoCompleteFallbackTask::class,
            EventOutboxTask::class,
            GoodsDownTask::class,
            HealthTask::class,
            LogFileTask::class,
            MissedDailyCatchupTask::class,
            OnboardTask::class,
            OrderAutoConfirmFallbackTask::class,
            OrderPayCheckTask::class,
            OrderSettleTask::class,
            PushRealFulfillmentTask::class,
            RefundTask::class,
            SplitOnboardingQueryTask::class,
            TeamStatBatchRebuildTask::class,
            TeamStatDirtyCheckTask::class,
            TeamStatFullRebuildTask::class,
            TeamStatHealthTask::class,
            TeamStatScrubTask::class,
            UnlinkLogJob::class,
            UserAccountTask::class,
            UserAnonymizeExpiredTask::class,
            UserCouponExpireTask::class,
            UserLevelExpireTask::class,
            VisitStatisticPersistenceTask::class,
            YiiErrorLogCleanTask::class,
            IntegralExpireTask::class,
        ];
    }
}
