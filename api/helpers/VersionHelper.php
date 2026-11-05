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

namespace app\helpers;

/**
 * 应用版本审核模式判断器
 *
 * 业务背景：
 *   商家提交 App Store / 微信小程序审核时，配置「审核版本号」，当审核员从该版本访问时
 *   接口自动返回精简内容（默认 tabbar、默认装修），屏蔽敏感入口（虚拟币、抽奖、灰色业务等）。
 *   审核通过后用户正常版本访问时，恢复完整内容。
 *
 * 配置入口：app\forms\mall\mall\CheckingForm::save()
 * 存储格式：option(checking_versions) = ['is_enabled'=>1, 'mpwx'=>'1.0.5', 'ios'=>'2.1.0', 'android'=>'2.1.0']
 *
 * 调用示例：
 *   if (VersionHelper::checkIsAuditMode($mallId)) {
 *       return $this->getDefaultTabbar();  // 审核版本返回默认
 *   }
 */
class VersionHelper
{
    /**
     * 判断当前请求是否来自"审核中的版本"
     *
     * @param int $mallId 必须显式传入商城 ID（旧版默认 1 在多商城下是隐患，故去除默认值）
     * @return bool
     */
    public static function checkIsAuditMode(int $mallId): bool
    {
        $versions = OptionHelper::get('checking_versions', $mallId);

        if (!$versions || empty($versions['is_enabled'])) {
            return false;
        }

        $platform = \Yii::$app->platform ?? '';
        $currentVersion = \Yii::$app->appVersion ?? '';

        // 微信小程序
        if ($platform === 'MPWX') {
            return isset($versions['mpwx']) && $currentVersion === $versions['mpwx'];
        }

        // 原生 APP（iOS / Android）
        if ($platform === 'APP') {
            $system = \Yii::$app->system ?? '';
            if ($system === 'Iphone') {
                return isset($versions['ios']) && $currentVersion === $versions['ios'];
            }
            if ($system === 'Android') {
                return isset($versions['android']) && $currentVersion === $versions['android'];
            }
        }

        return false;
    }
}
