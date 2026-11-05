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

namespace app\controllers\admin\filters;

use app\core\ApiCode;
use app\models\Admin;
use app\models\RoleMenu;
use Yii;
use yii\base\ActionFilter;

/**
 * Class AdminFilter
 * @package app\controllers\admin\filters
 *
 * [重构后] 后台权限过滤器
 * 职责：① 检查是否已登录；② opt-in 的后端 RBAC（按控制器 $permsMap 校验）。
 * 注意：具体的身份识别（Authentication）工作已移交 AdminBootstrap 完成。
 */
class AdminFilter extends ActionFilter
{
    // 这些属性保留，用于控制器配置
    public $ignore;
    public $only;

    // 如果这些属性在父类或你的业务逻辑中没用到，其实可以删除，保持代码整洁
    public $loginUrl;
    public $safeActions;
    public $onlyActions;
    public $safeRoutes;

    public function beforeAction($action)
    {
        // 1. 处理过滤器的白名单/黑名单逻辑 (保持不变)
        $id = $action->id;

        if (is_array($this->ignore) && in_array($id, $this->ignore)) {
            return true;
        }

        if (is_array($this->only) && !in_array($id, $this->only)) {
            return true;
        }

        // 2. [核心修改] 仅仅检查登录状态
        // 这里的 Yii::$app->admin->isGuest 状态是由 AdminBootstrap 在请求一开始就设置好的
        if (Yii::$app->admin->isGuest) {

            // [已移除] -----------------------------------------------------------
            // 移除原本在这里的 Token 解析、数据库查询、login() 逻辑。
            // 因为如果 AdminBootstrap 运行完还是 Guest，说明 Token 无效或过期，
            // 没必要在这里再查一遍库。
            // -----------------------------------------------------------------

            Yii::$app->response->data = [
                'code' => ApiCode::CODE_NOT_LOGIN,
                'msg' => '请先登录!',
            ];
            return false;
        }

        // 2.5 带了商城上下文(请求头 x-mall-id)的,这个账号必须能管这个商城。
        //     平台页里的员工管理、角色、重置密码都按「当前商城」圈范围 —— 不校验的话,
        //     A 商城的主账号改个请求头就能重置 B 商城员工的密码(2026-10-01 资金审计)。
        if ($denied = Admin::mallScopeDenied()) {
            Yii::$app->response->data = $denied;
            return false;
        }

        // 3. 后端 RBAC（opt-in）：仅对控制器 $permsMap 中声明的 action 强制校验。
        //    超管(SUPER) / 商城主账号(FOUNDER) 直接放行；操作员(OPERATOR) 必须命中所需权限。
        if (!$this->checkRbac($action)) {
            Yii::$app->response->data = [
                'code' => ApiCode::CODE_FAIL,
                'msg'  => '无操作权限',
            ];
            return false;
        }

        return parent::beforeAction($action);
    }

    /**
     * @return bool true=放行 / false=拦截
     */
    private function checkRbac($action)
    {
        /** @var Admin $admin */
        $admin = Yii::$app->admin->identity;
        if (!$admin) {
            return true; // 登录校验已在上方完成，这里防御性放行
        }
        // 超管 + 商城主账号：全权放行
        if ($admin->isMallSuper()) {
            return true;
        }

        $controller = $action->controller;
        $map = (isset($controller->permsMap) && is_array($controller->permsMap)) ? $controller->permsMap : [];
        if (empty($map[$action->id])) {
            return true; // 未声明 → 不强制
        }
        $need = (array)$map[$action->id];

        // 操作员的权限串 = 其角色 RoleMenu 关联菜单的非空 perms
        $myPerms = RoleMenu::permsByRole($admin->role_id);

        return (bool)array_intersect($need, $myPerms);
    }
}