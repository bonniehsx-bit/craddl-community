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

namespace app\plugins\article\listeners;

use app\events\DiyComponentEvent;
use app\forms\mall\mall\DiyForm;
use app\helpers\ImageHelper;
use app\listeners\BaseListener;
use Yii;

class DiyComponentListener extends BaseListener
{
    public function register()
    {
        Yii::$app->on(DiyForm::EVENT_DIY_COMPONENT_REGISTER, [$this, 'onDiyComponentRegister']);
    }

    public function onDiyComponentRegister(DiyComponentEvent $event)
    {
        // 将文章专有的 DIY 组件推入总集合
        $event->components[] = [
            'is_fixed' => 0,
            'group' => '文章插件',
            'name' => 'article-list',
            'display_name' => '文章',
            'active' => false,
            'icon' => ImageHelper::getUrl("/assets/diy/video.png"), // 沿用原本这里配的图标
            'data' => null
        ];
    }
}
