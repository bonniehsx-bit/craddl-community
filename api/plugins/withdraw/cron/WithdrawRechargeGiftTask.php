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

namespace app\plugins\withdraw\cron;

use app\core\Task;
use app\models\Mall;
use app\plugins\withdraw\services\RechargeGiftService;
use Yii;

/**
 * 充值分期赠品 —— 到期发放。
 *
 * 首期(充值立返)在 RechargeService::credit() 里当场发了,这个任务负责:
 *   · 后续期次到点发放
 *   · 兜底首期 —— credit() 里那段是 try/catch 吞异常的(不能让赠品失败回滚充值),
 *     漏掉的会在这里被重新捞到
 *
 * 每分钟跑一次:分期赠品的时间粒度是天,本不需要这么密,
 * 但"充值立返"要秒级体感,而它走的是同一条路径。
 */
class WithdrawRechargeGiftTask extends Task
{
    /** 单次每个商城最多发多少条 —— fpm 模式受 Go 调度器 30s 硬超时,发券要写库 */
    const MAX_PER_TICK = 100;

    public function run($params = null)
    {
        $total = 0;
        $mallIds = Mall::find()->select('id')->where(['is_delete' => 0])->column();
        foreach ($mallIds as $mallId) {
            try {
                $total += RechargeGiftService::grantDue((int) $mallId, self::MAX_PER_TICK);
            } catch (\Throwable $e) {
                // 单个商城失败不拖垮整轮
                Yii::error("[RechargeGift] 商城 {$mallId} 发放异常: " . $e->getMessage());
            }
        }
        return $total > 0 ? "充值分期赠品发放 {$total} 条" : true;
    }

    public function rule()
    {
        return '*/1 * * * *';
    }

    public function description()
    {
        return '充值赠品-分期到账发放';
    }
}
