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
use app\services\UserLevelGrantService;
use Yii;

/**
 * 用户等级有效期到期降级(通用台账)
 * 每天 03:20 扫描 user_level_grant,到点的执行降级。Manager 已按 mall 注入上下文。
 */
class UserLevelExpireTask extends Task
{
    public function run($params = null)
    {
        $mall = Yii::$app->mall;
        if (!$mall) return;
        try {
            $n = UserLevelGrantService::processExpired((int)$mall->id);
            if ($n > 0) {
                Yii::warning("[等级台账] Mall {$mall->id} 到期降级 {$n} 个用户", 'crontab');
            }
        } catch (\Throwable $e) {
            Yii::error("[等级台账] 到期降级失败 Mall {$mall->id}: " . $e->getMessage(), 'crontab');
        }
    }

    public function rule()
    {
        return "20 3 * * *";
    }

    public function description()
    {
        return "用户等级有效期到期降级";
    }
}
