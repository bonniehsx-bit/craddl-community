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

namespace app\plugins\clerk;

use app\plugins\Plugin as BasePlugin;

class Plugin extends BasePlugin
{
    public function getName()
    {
        return 'clerk';
    }

    public function getLabel()
    {
        return '平台核销中心';
    }

    public function getType()
    {
        return self::TYPE_TOOL;
    }

    public function getVersion()
    {
        return '1.0.0';
    }

    public function getHistoryVersion()
    {
        return [];
    }

    /**
     * 应用中心图标（element-ui 图标类名）。矢量图标不依赖文件/host，免设计、免托管。
     */
    public function getIcon(): string
    {
        return 'el-icon-user-solid';
    }

    public function getLogo()
    {
        return parent::getLogo();
    }

    public function getDsc()
    {
        return '平台自营核销员授权与日志中心';
    }

    public function getListeners()
    {
        return [];
    }

    public function getLinks()
    {
        return [
            [
                'name' => '扫码核销',
                'url' => '/plugins1/clerk/verify/verify',
            ],
        ];
    }

    /**
     * 声明本插件不可清空的表（配置/字典/模板/等级类）
     *
     * 本插件只有 plugin_clerk_user（核销员绑定记录），属于用户绑定业务数据，
     * 测试时可清空重建，无需保护。
     */
    public function getProtectedTables(): array
    {
        return [];
    }
}
