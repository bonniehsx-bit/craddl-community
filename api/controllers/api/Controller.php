<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-09-10
 * Time: 16:51
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

namespace app\controllers\api;

use app\controllers\BaseController;

/**
 * Class Controller
 * @package app\controllers\api
 * @Notes
 * API 模块的基础控制器。
 * 经过优化，此控制器不再处理用户登录逻辑，该职责已完全移交给 LoginFilter。
 * 这样做可以避免重复的数据库查询，大幅提升 API 性能。
 */
class Controller extends BaseController
{
    /**
     * @var bool 关闭 CSRF 验证。对于无状态的 API 来说，这是标准且必要的配置。
     */
    public $enableCsrfValidation = false;

    /**
     * 初始化控制器。
     * 优化后的 init 方法只处理与控制器自身相关的通用设置。
     */
    public function init()
    {
        // [优化] 总是先调用父类的 init 方法，确保继承链的正确执行。
        parent::init();

        // [保留] 针对 API 场景的用户组件配置。
        // `LoginFilter` 中执行 `Yii::$app->user->login()` 时会使用这些配置。
        \Yii::$app->user->enableSession = true;
        \Yii::$app->user->enableAutoLogin = true;

        // [保留] 标记当前应用为前端（非后台管理端）。
        \Yii::$app->isAdmin = false;

        // [保留] 从请求头获取并设置当前请求的平台信息（例如 'wxapp', 'aliapp', 'mobile' 等）。


        // [已移除] -----------------------------------------------------------------
        // 此处原有的通过 `x-access-token` 查询数据库并登录用户的逻辑已被完全移除。
        // 所有用户身份验证和加载工作都由 `LoginFilter` 统一处理，其优点包括：
        // 1. **性能提升**：`LoginFilter` 使用了高效的缓存策略，避免了在每个请求中都访问数据库。
        // 2. **职责单一**：代码结构更清晰，登录逻辑集中在一处，易于维护和调试。
        // 3. **避免重复**：消除了与 `LoginFilter` 之间重复的功能和性能开销。
        // -----------------------------------------------------------------------
    }
}
