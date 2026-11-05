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

namespace app\plugins\withdraw\services;

use app\plugins\mch\models\Mch;
use app\plugins\withdraw\models\Channel;

/**
 * 用户类型解析 —— 区分「商户」与「普通用户」。
 *
 * 规则：当前商城下存在 user_id = 该用户 的有效 Mch 记录 → 商户；否则普通用户。
 * （mch 为基础依赖插件，见 docs §21；仍做 class_exists 防御，缺失时一律按普通用户处理。）
 */
class UserTypeResolver
{
    /** 内存级缓存，避免一次请求内重复查库 */
    private static $cache = [];

    public static function resolve(int $userId, int $mallId): string
    {
        $key = $mallId . ':' . $userId;
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $isMerchant = false;
        if (class_exists(Mch::class)) {
            $isMerchant = Mch::find()
                ->where(['user_id' => $userId, 'mall_id' => $mallId, 'is_delete' => 0])
                ->exists();
        }

        return self::$cache[$key] = $isMerchant
            ? Channel::USER_TYPE_MERCHANT
            : Channel::USER_TYPE_USER;
    }
}
