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
 * 所有类继承这个类
 */

namespace app\core;


use yii\helpers\ArrayHelper;

abstract class Task
{
    /**
     * 允许运行多个实例
     *
     * [默认改为 false（安全优先）]：
     *   原先默认 true 会导致长耗时任务与下一轮调度重叠，产生以下问题：
     *   · 结算类 cron（OrderSettleTask/MchSettlementTask 等）重复处理同一批订单，
     *     引发 EVENT_ORDER_SETTLED 重入、佣金转账 double-spend
     *   · 扫描类 cron 重复占用 DB 资源，加剧锁竞争
     *   · 外部推送/同步类 cron 对第三方系统发重复请求
     *
     *   安全原则：默认 false = 串行执行，每轮自然排队。
     *   只有**确认幂等且耗时长**（如推送告警、上报统计等无副作用任务）的任务
     *   才应在子类里显式覆盖为 true。
     *
     * @var bool
     */
    public $allowMultiInstance = false;

    /**
     * 关键日任务:调度器宕机等原因错过每日窗口时,恢复后由 MissedDailyCatchupTask【补跑一次】。
     * 仅适合「幂等 + 每日一次 + 漏跑有实质损失(如永久数据缺口)」的任务;高频任务靠频率自愈,无需开。
     * @var bool
     */
    public $catchUpIfMissed = false;

    /** 判定「漏跑」的时长阈值(小时):上次成功距今超过它 = 今日窗口已错过。日任务默认 25h。 */
    public $catchUpMaxAgeHours = 25;

    /**
     * 运行定时任务
     * @param mixed $params
     * @return mixed
     */
    abstract public function run($params = null);

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
    abstract public function rule();

    /**
     * 任务描述
     * @return mixed
     */
    abstract public function description();

    /**
     * 任务是否启用
     * @return bool
     */
    public function enable()
    {
        return true;
    }

    /**
     * 派发方式(供 Go 调度器决定怎么执行本任务):
     *   'fpm'(默认)—— 走 FastCGI/PHP-FPM,复用热 worker、适合"多而小"的任务,但受调度器 30s 派发硬超时;
     *   'cli'      —— 走命令行 php yii,无超时、不占 fpm worker,适合"重而长"的批处理(全表对账、导出等)。
     * 重/长任务在子类里覆盖返回 'cli' 即可。
     * @return string 'fpm' | 'cli'
     */
    public function runMode()
    {
        return 'fpm';
    }

    /**
     * 运行时候登录的用户,如果为false，那么就以匿名用户运行命令，如果返回工号，系统会进行登录对应的工号
     *
     * 使用`Yii::$app->user->id`那么就可以获取到这里返回的工号信息
     *
     * @return mixed
     */
    public function getRunUser()
    {
        return false;
    }

    /**
     * 获取某个方法是否允许多实例运行
     * @param null|mixed $method 方法名称
     * @return bool 如果可以返回true，否则返回false
     */
    public function getAllowMultiInstance($method = null)
    {
        if (empty($method)) {
            return $this->allowMultiInstance;
        }
        $rules = $this->rule();
        if (!is_array($rules)) {
            return $this->allowMultiInstance;
        }
        return !ArrayHelper::getValue(ArrayHelper::getValue($rules, $method, []), 'single', false);
    }
}