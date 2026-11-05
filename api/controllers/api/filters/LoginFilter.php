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

namespace app\controllers\api\filters;

use app\core\ApiCode;
use app\helpers\JobHelper;
use app\helpers\LockHelper;

// 保留 UserLoginJob
use app\models\User;
use app\jobs\user\UserLoginJob;
use Yii;
use yii\base\ActionFilter;

/**
 * Class LoginFilter
 * @package app\controllers\api\filters
 *
 * [重构后]
 * 此过滤器现在专注于访问控制和登录后的附加操作。
 * 它假设用户的身份加载和初始化工作已由更早执行的 AppBootstrap 组件完成。
 */
class LoginFilter extends ActionFilter
{
    /**
     * @var array 定义不需要登录即可访问的 Action ID 列表。
     */
    public $ignore;

    /**
     * @var array 定义仅需要登录即可访问的 Action ID 列表。
     * 如果设置了此项，则只有列表内的 Action 会被检查登录状态。
     */
    public $only;

    public function beforeAction($action)
    {
        // [已移除] -----------------------------------------------------------
        // 所有的商城验证、Token 解析、缓存查询、数据库查询、用户登录逻辑
        // 都已移除，因为 AppBootstrap 已经处理完毕。
        // -----------------------------------------------------------------

        // 1. Action 访问控制 (ignore/only 逻辑保持不变)
        $actionId = $action->id;
        if (is_array($this->ignore) && in_array($actionId, $this->ignore)) {
            // 在 'ignore' 列表中的 Action，直接允许访问
            return true;
        }

        if (is_array($this->only) && !in_array($actionId, $this->only)) {
            // 如果定义了 'only' 列表，但当前 Action 不在其中，也直接允许访问
            return true;
        }

        // 2. 检查登录状态 (核心职责)
        // 此处直接检查由 AppBootstrap 初始化后的结果
        if (Yii::$app->user->isGuest) {
            Yii::$app->response->data = [
                'code' => ApiCode::CODE_NOT_LOGIN,
                'msg' => '请先登录!',
            ];
            // 阻止执行 Action
            return false;
        }

        $user = Yii::$app->user->identity;
        if (!$user || $user->status) {
            Yii::$app->response->data = [
                'code' => ApiCode::CODE_NOT_LOGIN,
                'msg' => '请先登录!',
            ];
            // 阻止执行 Action
            return false;
        }

        // 3. 已登录用户的附加操作 (登录后的副作用，例如记录活跃度)
        // 这部分逻辑保留在这里非常合适，因为它只应该对已确认登录的用户执行。
        $loggedInUserId = Yii::$app->user->id;
        if (LockHelper::setUserLogin($loggedInUserId)) {
            $parent_id = Yii::$app->request->headers->get('x-parent-id', null);
            Yii::warning("成功获取用户登录锁，准备提交 UserLoginJob。 UserID: {$loggedInUserId}, ParentID: " . ($parent_id ?: '无'));
            JobHelper::push(new UserLoginJob(['user_id' => $loggedInUserId, 'parent_id' => $parent_id]));
        }

        // 允许执行 Action
        return parent::beforeAction($action);
    }
}
