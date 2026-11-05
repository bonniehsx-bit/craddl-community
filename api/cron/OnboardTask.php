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
use app\funds\BaseFund;
use app\models\UserSettlementInfo;

class OnboardTask extends Task
{

    /**
     * 运行定时任务
     * @param mixed $params
     * @return mixed
     */
    public function run($params=null)
    {
        // 日志已翻译为中文
        \Yii::warning("开始轮询处理中的进件状态...");
        $limit = 100; // 每次处理的数量，防止内存耗尽
        $processedCount = 0;
        $failedCount = 0;
        $sevenDaysAgo = time() - 86400 * 7;
        // 1. 找出所有需要轮询的记录
        // 核心是查找 extra_info 中，任何一个渠道状态为 'pending' 或 'pending_verify' 的记录
        $query = UserSettlementInfo::find()->where([
            'or',
            ['like', 'extra_info', '"status":"pending"'],
            ['like', 'extra_info', '"status":"pending_verify"'],
            ['like', 'extra_info', '"status":"failed"']
        ])->andWhere(['>=', 'updated_at', $sevenDaysAgo]) // 关键优化点：updated_at 是 Unix 时间戳
        ->andWhere(['is_delete' => 0]);

        foreach ($query->batch($limit) as $batch) {
            /** @var UserSettlementInfo[] $batch */
            foreach ($batch as $settlementInfo) {
                try {
                    $extra = $settlementInfo->getExtraInfo();
                    $statuses = $extra['onboarding_status'] ?? [];
                    $isUpdated = false;

                    foreach ($statuses as $fundClass => &$statusInfo) {
                        // 2. 只处理处于中间状态的
                        if (in_array($statusInfo['status'], ['pending', 'pending_verify','failed'])) {

                            // 3. 动态实例化对应的 Fund 类
                            if (!class_exists($fundClass)) continue;
                            /** @var BaseFund $fundInstance */
                            $fundInstance = \Yii::createObject($fundClass, [$settlementInfo->mall_id]);

                            // 4. 调用 checkOnboardingStatus 方法查询最新状态
                            $newStatus = $fundInstance->checkOnboardingStatus($settlementInfo->user_id);

                            // 5. 如果状态有变化，则更新
                            if ($newStatus['status'] !== $statusInfo['status']) {
                                // 日志已翻译为中文
                                \Yii::warning("用户ID {$settlementInfo->user_id} 的状态发生变化，渠道 {$fundClass}：{$statusInfo['status']} -> {$newStatus['status']}");
                                $statusInfo['status'] = strtolower($newStatus['status']);
                                $statusInfo['remark'] = $newStatus['message'];
                                $statusInfo['updated_at'] = time();
                                $isUpdated = true;
                            }
                        }
                    }
                    unset($statusInfo);

                    if ($isUpdated) {
                        $extra['onboarding_status'] = $statuses;
                        $settlementInfo->setExtraInfo($extra);
                        if (!$settlementInfo->save()) {
                            // 日志已翻译为中文
                            \Yii::warning("为用户ID: {$settlementInfo->user_id} 保存更新后的状态失败");
                            throw new \Exception("为用户ID: {$settlementInfo->user_id} 保存更新后的状态失败");
                        }
                        $processedCount++;
                    }
                } catch (\Exception $e) {
                    // 日志已翻译为中文
                    \Yii::warning("为用户ID {$settlementInfo->user_id} 轮询进件状态失败: " . $e->getMessage());
                    $failedCount++;
                }
            }
        }

        // 日志已翻译为中文
        \Yii::warning("轮询结束。成功处理: {$processedCount}, 失败: {$failedCount}");
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
        return " */5 * * * *";
    }

    /**
     * 任务描述
     * @return mixed
     */
    public function description()
    {
        // TODO: Implement description() method.
        return "每五分钟检查代付或灵工进件进度";
    }
}