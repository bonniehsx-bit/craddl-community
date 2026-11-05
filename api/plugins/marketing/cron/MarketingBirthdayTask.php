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

namespace app\plugins\marketing\cron;

use app\core\Task;
use app\models\Mall;
use app\plugins\marketing\services\TriggerService;
use Yii;

/**
 * 生日券:每日扫当天/当月生日用户发放(按年幂等)。
 */
class MarketingBirthdayTask extends Task
{
    public function run($params = null)
    {
        $mallIds = Mall::find()->where(['is_delete' => 0])->select('id')->column();
        $total = 0;
        foreach ($mallIds as $mallId) {
            $mall = Mall::findOne(['id' => $mallId, 'is_delete' => 0]);
            if (!$mall) {
                continue;
            }
            Yii::$app->set('mall', $mall);
            $total += TriggerService::runBirthday((int)$mallId);
        }
        Yii::info("[营销活动] 生日券完成,发券 {$total} 张,扫描 " . count($mallIds) . " 个商城");
        return $total;
    }

    public function rule()
    {
        return "20 1 * * *"; // 每日 01:20
    }

    public function description()
    {
        return "营销活动-生日券发放";
    }
}
