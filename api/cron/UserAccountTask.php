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
use app\models\AccessToken;
use app\models\DateBalanceLog;
use app\models\DateIntegralLog; // 注意：这里可能需要一个新的Model，或者您决定复用哪个
use app\models\DatePriceLog;
use app\models\User;
use DateTime;
use Yii; // 引入Yii

class UserAccountTask extends Task
{
    /** 关键日任务:每日余额/货款/积分快照,漏跑=永久缺当天历史,调度器恢复后补跑一次 */
    public $catchUpIfMissed = true;

    public function run($params=null)
    {
        Yii::warning('--- [定时任务] 开始执行用户账户每日快照任务 ---');

        // 1. 清理过期的 AccessToken (这个操作通常很快，保持原样)
        $deletedTokens = AccessToken::deleteAll(['<', 'expire_at', time() - 30 * 24 * 60 * 60]);
        Yii::warning("[定时任务] 清理了 {$deletedTokens} 个过期的 AccessToken。");

        // --- 准备日期和批处理大小 ---
        $today = new DateTime();
        $today->modify('-1 day');
        $date = $today->format('Y-m-d');
        $batchSize = 1000; // 每次处理1000个用户

        // --- 2. 批量处理用户余额快照 ---
        Yii::warning("[定时任务] 开始处理用户 [余额] 快照...");
        $balanceQuery = User::find()->where(['mall_id' => Yii::$app->mall->id])->andWhere(['>', 'money', 0])->andWhere(['>', 'id', 1])->select('mall_id, id, money');
        $balanceProcessedCount = 0;
        foreach ($balanceQuery->batch($batchSize) as $users) {
            $rowsToInsert = [];
            foreach ($users as $user) {
                $rowsToInsert[] = [
                    'mall_id' => $user->mall_id,
                    'user_id' => $user->id,
                    'current' => $user->money,
                    'date_at' => $date,
                ];
                $balanceProcessedCount++;
            }
            if (!empty($rowsToInsert)) {
                // 使用批量插入，性能更高
                Yii::$app->db->createCommand()->batchInsert(DateBalanceLog::tableName(), ['mall_id', 'user_id', 'current', 'date_at'], $rowsToInsert)->execute();
            }
        }
        Yii::warning("[定时任务] [余额] 快照处理完成，共处理 {$balanceProcessedCount} 个用户。");


        // --- 3. 批量处理用户奖励快照 ---
        Yii::warning("[定时任务] 开始处理用户 [奖励] 快照...");
        $priceQuery = User::find()->where(['mall_id' => Yii::$app->mall->id])->andWhere(['>', 'price', 0])->andWhere(['>', 'id', 1])->select('mall_id, id, price');
        $priceProcessedCount = 0;
        foreach ($priceQuery->batch($batchSize) as $users) {
            $rowsToInsert = [];
            foreach ($users as $user) {
                $rowsToInsert[] = [
                    'mall_id' => $user->mall_id,
                    'user_id' => $user->id,
                    'current' => $user->price,
                    'date_at' => $date,
                ];
                $priceProcessedCount++;
            }
            if (!empty($rowsToInsert)) {
                Yii::$app->db->createCommand()->batchInsert(DatePriceLog::tableName(), ['mall_id', 'user_id', 'current', 'date_at'], $rowsToInsert)->execute();
            }
        }
        Yii::warning("[定时任务] [奖励] 快照处理完成，共处理 {$priceProcessedCount} 个用户。");

        // --- 4. 批量处理用户积分快照 (修复原始代码中的笔误) ---
        // 原始代码中，积分日志错误地写入了 DatePriceLog，这里应修正
        // 你有一个 DateIntegralLog 模型用于记录积分快照
        Yii::warning("[定时任务] 开始处理用户 [积分] 快照...");
        $integralQuery = User::find()->where(['mall_id' => Yii::$app->mall->id])->andWhere(['>', 'integral', 0])->andWhere(['>', 'id', 1])->select('mall_id, id, integral');
        $integralProcessedCount = 0;
        foreach ($integralQuery->batch($batchSize) as $users) {
            $rowsToInsert = [];
            foreach ($users as $user) {
                $rowsToInsert[] = [
                    'mall_id' => $user->mall_id,
                    'user_id' => $user->id,
                    'current' => $user->integral,
                    'date_at' => $date,
                ];
                $integralProcessedCount++;
            }
            if (!empty($rowsToInsert)) {
                // 请确保您有一个对应的 DateIntegralLog 表和模型
                // 如果没有，您需要创建一个，或者决定将积分快照记录在哪里
                Yii::$app->db->createCommand()->batchInsert('{{%date_integral_log}}', ['mall_id', 'user_id', 'current', 'date_at'], $rowsToInsert)->execute();
            }
        }
        Yii::warning("[定时任务] [积分] 快照处理完成，共处理 {$integralProcessedCount} 个用户。");

        Yii::warning('--- [定时任务] 用户账户每日快照任务执行完毕 ---');
    }

    public function rule()
    {
        return "05 04 * * *"; // 调整为每天执行一次
    }

    public function description()
    {
        return '【已优化】用户账户每日数据快照任务';
    }
}