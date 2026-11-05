<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-09-11
 * Time: 19:30
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

namespace app\forms\admin;

use app\core\ApiCode;
use app\models\Admin;
use app\models\BaseModel;
use Yii;

/**
 * Class LogoutForm
 * @package app\forms\admin
 *
 * [重构版] 管理员退出表单
 * 逻辑极其精简，因为核心脏活累活都交给了 Admin 模型处理。
 */
class LogoutForm extends BaseModel
{
    public function logout()
    {
        /** @var Admin $admin */
        $admin = Yii::$app->admin->identity;

        // 防御性检查：虽然 AdminFilter 应该拦截了，但多判断一次无害
        if (!$admin) {
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '已退出');
        }

        // 调用 Admin 模型中定义的新版 logout 方法
        // 它会自动：
        // 1. 获取当前请求的 AccessToken
        // 2. 删除数据库 AccessToken 表中对应的记录
        // 3. 删除 Redis 中的 Token 映射
        // 4. 执行 Yii::$app->admin->logout()
        $admin->logout();

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '退出登录成功');
    }
}
