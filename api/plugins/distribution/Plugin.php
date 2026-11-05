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

namespace app\plugins\distribution;

use app\events\GoodsEvent;
use app\helpers\FileHelper;
use app\plugins\distribution\listeners\DistributionListener;
use app\plugins\distribution\listeners\GoodsListener;

class Plugin extends \app\plugins\Plugin
{
    public function getLabel()
    {
        return '分销推客';
    }

    public function getName()
    {
        return 'distribution';
    }

    /**
     * 应用中心图标（element-ui 图标类名）。矢量图标不依赖文件/host，免设计、免托管。
     */
    public function getIcon(): string
    {
        return 'el-icon-share';
    }

    public function getLogo()
    {
        $plugin = \app\models\Plugin::findOne(['name' => $this->getName(), 'is_delete' => 0]);
        return \app\helpers\PluginHelper::resolveLogo($this->getName(), $plugin->logo ?? '');
    }

    public function getLinks()
    {
        // 用户端分销中心的链接
        return [
            [
                'url' => '/plugins/distribution/index',
                'name' => '分销中心'
            ]
        ];
    }

    public function getListeners()
    {
        return [
            DistributionListener::class,
            GoodsListener::class,
            \app\plugins\distribution\listeners\CommissionTypeListener::class,
        ];
    }

    public function getCrons(): array
    {
        return [
            \app\plugins\distribution\cron\SettleDueTask::class,
        ];
    }

    public function getType()
    {
        return self::TYPE_COMMISSION; // 营销插件
    }

    public function getVersion()
    {
        return file_get_contents(__DIR__ . '/version');
    }

    public function getDsc()
    {
        return '提供多级分销佣金奖励体系，激励用户分享推广。';
    }

    public function getHistoryVersion()
    {
        // TODO: Implement getHistoryVersion() method.
    }

    /**
     * 声明本插件不可清空的表（配置/字典/模板/等级类）
     */
    public function getProtectedTables(): array
    {
        // 分销商等级已并入会员等级，本插件不再有受保护的配置表
        return [];
    }


}