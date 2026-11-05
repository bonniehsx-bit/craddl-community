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
use app\helpers\IntegralSettingHelper;
use app\services\IntegralExpireService;
use Yii;

/**
 * 积分滚动有效期自动过期回收(仅 mode=rolling 的商城生效)。
 *
 * 逐商场跑(Manager 注入 Yii::$app->mall)。放核心:有效期机制属核心,配置是 OptionHelper 数据,
 * cron 放核心才能"配了就持续生效",不受 integral 插件装卸影响(见 docs 方案 §13.1)。
 *
 * 语义:每年 rolling_month/rolling_day 当天,回收「今年 1 月 1 日之前(即去年及更早)入账、且仍未用」的积分。
 * 手动 mode 不走本 cron,由后台手动触发。
 */
class IntegralExpireTask extends Task
{
    public function rule()
    {
        return "10 3 * * *"; // 每天 03:10 检查一次
    }

    public function description()
    {
        return "积分滚动有效期自动过期回收";
    }

    public function run($params = null)
    {
        $mallId = (int) (Yii::$app->mall->id ?? 0);
        if ($mallId <= 0) {
            return;
        }
        $s = IntegralSettingHelper::get($mallId)['expire'];
        if (!self::shouldRunAt($s, time())) {
            return;
        }
        $beforeDate = self::cutoffDate(time());
        $res = (new IntegralExpireService())->expireBefore($mallId, $beforeDate, 'rolling', 0);
        Yii::info("[积分滚动过期] mall={$mallId} run={$res['run_no']} 影响{$res['affected_users']}人 回收{$res['total_recovered']}", 'integral');
    }

    /**
     * 今天是否该执行滚动过期。抽成纯函数(传入时间戳)是为了能测 —— 日期边界正是这里最容易错的地方。
     *
     * ⚠ 到期日必须向**当月最后一天收敛**:后台允许填 1~31,若配成「2月31日」这类不存在的日期,
     * date('j') 永远等不到 31 → 任务静默永不执行,跳过一整年且无任何报错,极难发现。
     * 收敛后「31日」在 2 月自动落到 28/29 日,语义即"当月最后一天",符合运营预期。
     *
     * @param array $expire integral_setting 的 expire 段
     * @param int   $now    时间戳
     */
    public static function shouldRunAt(array $expire, int $now): bool
    {
        if (empty($expire['enabled'])) {
            return false;
        }
        if (($expire['mode'] ?? '') !== 'rolling') {
            return false; // 手动模式由后台触发,不归 cron 管
        }
        if ((int) date('n', $now) !== (int) ($expire['rolling_month'] ?? 5)) {
            return false;
        }
        $targetDay = min((int) ($expire['rolling_day'] ?? 31), (int) date('t', $now));
        return (int) date('j', $now) === $targetDay;
    }

    /** 回收截止日:当年 1 月 1 日 —— 即回收「去年及更早」入账且未用的积分 */
    public static function cutoffDate(int $now): string
    {
        return date('Y', $now) . '-01-01';
    }
}
