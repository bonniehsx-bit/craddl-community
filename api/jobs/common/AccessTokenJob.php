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

namespace app\jobs\common;

use app\core\Job;
use app\helpers\CacheHelper;
use app\models\AccessToken;

class AccessTokenJob extends Job
{

    public $token;

    public function execute($params = null)
    {
        // DB 中存储的是 hash(token)，直接按 hash 匹配删除
        // Redis Token 缓存与 DB Token 同 TTL，会自动过期，无需手动清除
        // ⚠ 只删真正过期的:用户令牌会活跃续期(UserService::renewToken),
        //   到了签发时排定的时间点还在用的令牌,expire_at 早已被推后,不能删 —— 删了就是把在用的人踢下线
        AccessToken::deleteAll(['and', ['token' => $this->token], ['<=', 'expire_at', time()]]);
    }
}