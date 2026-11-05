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

namespace app\plugins\mch\listeners;

use app\events\CommissionTypeEvent;
use app\listeners\BaseListener;
use app\plugins\mch\models\MchCommissionType;
use Yii;

/**
 * 向财务分润日志注册商家相关分润类型的展示元数据（线下收银已随 cashier 插件拆出）
 * - REWARD：商家货款/运费奖励（原核心 TYPE_REWARD）
 */
class CommissionTypeListener extends BaseListener
{
    public function register()
    {
        Yii::$app->on(CommissionTypeEvent::EVENT_COLLECT, [$this, 'onCollect']);
    }

    public function onCollect(CommissionTypeEvent $event)
    {
        $event->add(MchCommissionType::REWARD, [
            'label'          => '商家奖励/货款',
            'tag_type'       => 'info',
            'rate_label'     => '平台抽点',
            'rate_desc_mode' => 'platform_fee',
            'group'          => 'mch',
        ]);

        $event->add(MchCommissionType::RECRUIT, [
            'label'          => '商家推荐奖',
            'tag_type'       => 'warning',
            'rate_label'     => '分红比例',
            'rate_desc_mode' => 'commission',
            'group'          => 'mch',
        ]);
    }
}
