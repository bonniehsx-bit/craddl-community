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

namespace app\plugins\distribution\cron;

use app\core\Task;
use app\plugins\distribution\services\DistributionSettleService;

/**
 * 分销佣金 T+N 到期结算：把 settle_delay_days 到期的待发放佣金发出去。
 * 未配延迟天数的商城本表没有计划记录，任务空跑即可。
 */
class SettleDueTask extends Task
{
    public function run($params = null)
    {
        // Manager 已按商城注入上下文
        $mall = \Yii::$app->mall;
        if (!$mall) {
            return;
        }

        try {
            [$settled, $invalid] = (new DistributionSettleService())->settleDue((int) $mall->id);
            if ($settled || $invalid) {
                \Yii::warning("DistributionSettleDueTask: Mall {$mall->id} 发放 {$settled} 笔，作废 {$invalid} 笔", 'crontab');
            }
        } catch (\Exception $e) {
            \Yii::error("DistributionSettleDueTask: Mall {$mall->id} 失败: " . $e->getMessage(), 'crontab');
        }
    }

    public function rule()
    {
        // 每小时的 :20 扫一次到期待结算。
        //
        // ⚠ 不能只在凌晨跑:due_at = 计提时刻 + N 天(精确到点)。若某笔下午到期而 cron 只在凌晨,
        //   当天凌晨那趟(到期时刻还没到)会跳过,被迫拖到次日凌晨才发 —— 每笔都白延近一天。
        //   按小时跑则到点后 ≤1h 内即发。与 xwy(:30)、dealer(:10) 错开时段避免撞车。
        return "20 * * * *";
    }

    public function description()
    {
        return '分销佣金-T+N到期结算';
    }
}
