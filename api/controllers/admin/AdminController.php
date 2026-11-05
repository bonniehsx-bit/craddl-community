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

namespace app\controllers\admin;

use app\core\ApiCode;
use app\forms\admin\AdminDeleteForm;
use app\forms\admin\LogoutForm;
use app\forms\admin\MallMenuOptionForm;
use app\forms\admin\MenuDeleteForm;
use app\forms\admin\MenuEditForm;
use app\forms\admin\MenuListForm;
use app\forms\admin\MenuTreeForm;
use app\forms\admin\PasswordChangeForm;
use app\forms\admin\PasswordResetForm;
use app\forms\admin\PluginMapForm;
use app\forms\admin\RoleDeleteForm;
use app\forms\admin\RoleEditForm;
use app\forms\admin\RoleListForm;
use app\forms\admin\RouterListForm;
use app\forms\admin\SystemInfoForm;
use app\forms\admin\UserEditForm;
use app\forms\admin\UserListForm;
use app\helpers\ResponseHelper;
use app\models\Admin;
use app\models\AdminRole;
use app\models\Role;
use app\models\RoleMenu;
use Yii;

class AdminController extends Controller
{
    /**
     * 后端 RBAC 权限映射：action id => 所需权限(字符串 或 数组=任一满足)。
     * 由 AdminFilter 强制校验：超管 / 商城主账号(FOUNDER) 放行；操作员必须命中权限，否则 403。
     * 未列入的 action 不强制（opt-in，避免误锁）。当前按页面级 perms(:list) 收口
     * —— 因按钮级 perms(:add/:edit/:remove) 尚未在菜单表登记，细粒度待权限词表补全后再收紧。
     */
    public $permsMap = [
        // 角色管理
        'roles'                 => ['system:role:list', 'system:user:list'], // 列表读：编辑用户时也要读角色，放宽
        'role'                  => 'system:role:list',
        'role-edit'             => 'system:role:list',
        'role-delete'           => 'system:role:list',
        'role-menu-tree-select' => 'system:role:list',
        // 用户(员工)管理
        'user-list'             => 'system:user:list',
        'user'                  => 'system:user:list',
        'user-edit'             => 'system:user:list',
        'delete'                => 'system:user:list',
        'password-reset'        => 'system:user:list',
        'list'                  => 'system:user:list', // 旧 AdminListForm 僵尸端点，按用户管理权限收口
        // 菜单管理
        'menu-list'             => 'system:menu:list',
        'menu'                  => 'system:menu:list',
        'menu-edit'             => 'system:menu:list',
        'menu-delete'           => 'system:menu:list',
        'menu-tree'             => 'system:menu:list',
    ];

    // =========================================================================
    // 基础功能
    // =========================================================================

    /**
     * 退出登录
     */
    public function actionLogout()
    {
        $form = new LogoutForm();
        return $this->asJson($form->logout());
    }

    /**
     * 修改个人密码
     */
    public function actionPasswordChange()
    {
        $form = new PasswordChangeForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->save());
    }

    /**
     * 重置他人密码
     */
    public function actionPasswordReset()
    {
        $form = new PasswordResetForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->save());
    }

    /**
     * 获取个人信息 (Profile)
     */
    public function actionProfile()
    {
        return $this->actionInfo();
    }

    /**
     * 更新个人信息
     */
    public function actionProfileUpdate()
    {
        $data = $this->request->post();
        /** @var Admin $admin */
        $admin = Yii::$app->admin->identity;

        if (isset($data['nickname'])) {
            $admin->nickname = $data['nickname'];
        }
        // 邮箱/手机号是 C 端会员概念,后台管理账号不维护(前端已移除对应字段)
        if (isset($data['bind_uid'])) {
            $admin->bind_uid = intval($data['bind_uid']);
        }
        if (isset($data['is_kefu'])) {
            $admin->is_kefu = intval($data['is_kefu']);
        }

        if (!$admin->save()) {
            return $this->asJson(ResponseHelper::send(ApiCode::CODE_FAIL, '保存失败'));
        }
        return $this->asJson(ResponseHelper::send(ApiCode::CODE_SUCCESS, '更新成功'));
    }

    /**
     * 设置头像
     */
    public function actionSetAvatar()
    {
        $data = $this->request->post();
        /** @var Admin $admin */
        $admin = Yii::$app->admin->identity;

        if (!empty($data['avatar'])) {
            $admin->avatar = $data['avatar'];
        }

        if (!$admin->save()) {
            return $this->asJson(ResponseHelper::send(ApiCode::CODE_FAIL, '保存失败'));
        }
        return $this->asJson(ResponseHelper::send(ApiCode::CODE_SUCCESS, '更新成功'));
    }

    /**
     * 获取登录用户信息及权限
     */
    /**
     * 获取登录用户信息及权限
     * 【修复】：为了兼容旧前端，这里不使用 ResponseHelper::send 包裹 data，而是直接返回扁平结构
     */
    public function actionInfo()
    {

        /** @var Admin $admin */
        $admin = Yii::$app->admin->identity;

        // 获取角色键名列表
        $roleKeys = Role::find()->alias('r')
            ->innerJoin(['a' => AdminRole::tableName()], 'a.role_id=r.id')
            ->where(['a.admin_id' => $admin->id])
            ->select('r.role_key')
            ->column();

        // 获取角色ID列表
        $roleIds = AdminRole::find()->where(['admin_id' => $admin->id])->select('role_id')->column();

        // 获取完整角色信息
        $roles = Role::find()->alias('r')
            ->innerJoin(['a' => AdminRole::tableName()], 'a.role_id=r.id')
            ->where(['a.admin_id' => $admin->id])
            ->select('a.*, a.id as role_id')
            ->asArray()->all();

        // 获取主角色信息
        $role = Role::findOne($admin->role_id);

        // 获取权限列表
        // 平台超管(SUPER) + 商城主账号(FOUNDER) 在其可见范围内全权 → *:*:*（FOUNDER 的范围由动态路由按商城收口）；
        // 操作员(OPERATOR) 才按其角色 RoleMenu 的 perms 受限。
        if ($admin->isMallSuper()) {
            $permissions = ["*:*:*"];
        } else {
            $permissions = RoleMenu::permsByRole($admin->role_id);
        }

        // 【注意】：这里手动构造数组，保持 code, user, permissions 同级
        return $this->asJson([
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => '操作成功',
            'permissions' => $permissions,
            'roles' => $roleKeys,
            'user' => [
                "created_time" => $admin->created_time,
                "remark" => $admin->remark,
                "user_id" => $admin->id,
                "username" => $admin->username,
                "nickname" => $admin->nickname,
                "email" => $admin->email,
                "mobile" => $admin->mobile,
                "avatar" => $admin->avatar,
                "login_ip" => $admin->login_ip,
                "roles" => $roles,
                "roleIds" => $roleIds,
                "role_id" => $admin->role_id,
                "admin_type" => $admin->admin_type,
                // 「切换商城」这个入口该不该给,判据是**能进的商城多于一个**,不是「是不是超管」。
                // 前端只知道 admin_type,算不出这个数(要查 admin_mall 关联和自己创建的商城),
                // 所以由后端算好下发 —— 判断收在 Admin::canSwitchMall() 一处,前端不再猜。
                "mall_switchable" => $admin->canSwitchMall(),
                "bind_uid" => $admin->bind_uid,
                "is_kefu" => $admin->is_kefu,
                "admin" => $role && $role->admin ? true : false
            ]
        ]);
    }


    // =========================================================================
    // 路由与插件
    // =========================================================================

    /**
     * 获取动态路由 (SaaS + RBAC)
     */
    public function actionRouters()
    {
        $form = new RouterListForm();
        return $this->asJson($form->getRouters());
    }

    /**
     * 插件列表 (所有插件)
     */
    public function actionPluginMap()
    {
        $form = new PluginMapForm();
        return $this->asJson($form->getList());
    }

    /**
     * 系统配置信息（仅超级管理员可见，只读）
     */
    public function actionSystemInfo()
    {
        if (Yii::$app->admin->identity->admin_type != Admin::ADMIN_TYPE_SUPER) {
            return $this->asJson(ResponseHelper::send(ApiCode::CODE_ERROR, '无权访问'));
        }
        $form = new SystemInfoForm();
        return $this->asJson($form->getInfo());
    }

    /**
     * 当前商城可用插件
     */
    public function actionMallPlugin()
    {
        // 简单读取操作保留在控制器，确保使用 ResponseHelper
        $list = \app\models\MallPlugin::find()->alias('m')
            ->innerJoin(['p' => \app\models\Plugin::tableName()], 'p.name=m.plugin')
            ->andWhere(['m.mall_id' => $this->mall_id]) // BaseController 设置的 mall_id
            ->andWhere(['m.is_delete' => 0, 'p.is_delete' => 0])
            ->select('m.plugin,p.label')
            ->orderBy('p.type asc')
            ->asArray()
            ->all();

        return $this->asJson(ResponseHelper::send(ApiCode::CODE_SUCCESS, '', ['list' => $list]));
    }

    // =========================================================================
    // 员工管理 (SaaS 隔离)
    // =========================================================================

    /**
     * 员工列表
     */
    public function actionUserList()
    {
        $form = new UserListForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getList());
    }

    /**
     * 员工新增/编辑
     */
    public function actionUserEdit()
    {
        $form = new UserEditForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->save());
    }

    /**
     * 员工删除
     */
    public function actionDelete()
    {
        $form = new AdminDeleteForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->save());
    }

    /**
     * 员工详情 (用于编辑回显)
     */
    public function actionUser()
    {
        $id = $this->request->get('id');

        // SaaS 权限校验：只能查询当前商城的员工
        $query = Admin::find()->where(['id' => $id, 'is_delete' => 0]);
        if (Yii::$app->admin->identity->admin_type != Admin::ADMIN_TYPE_SUPER) {
            $query->andWhere(['mall_id' => $this->mall_id]);
        }
        $admin = $query->one();

        if (!$admin) {
            return $this->asJson(ResponseHelper::send(ApiCode::CODE_FAIL, '用户不存在'));
        }

        $role = Role::findOne($admin->role_id);

        // 查找该用户拥有的所有角色ID
        $roleIds = AdminRole::find()
            ->where(['admin_id' => $admin->id])
            ->select('role_id')
            ->column();
        $arr = array_map('intval', $roleIds);

        // 获取当前商城可用的角色列表供选择
        // 使用 findByMall 自动过滤
        $roleList = Role::findByMall()
            ->andWhere(['is_delete' => 0])
            ->orderBy('id asc')
            ->asArray()
            ->all();

        foreach ($roleList as &$item) {
            $item['admin'] = $item['admin'] ? 1 : 0;
            $item['role_id'] = $item['id'];
            $item['menu_check_strictly'] = $item['menu_check_strictly'] ? true : false;
        }

        $info = [
            "created_time" => $admin->created_time,
            "remark" => $admin->remark,
            "id" => $admin->id,
            "admin_type" => $admin->admin_type,
            "username" => $admin->username,
            "nickname" => $admin->nickname,
            "email" => $admin->email,
            "mobile" => $admin->mobile,
            "avatar" => $admin->avatar,
            "bind_uid" => $admin->bind_uid,
            "is_kefu" => $admin->is_kefu,
            'login_ip' => $admin->login_ip,
            'is_has_expire' => (int)$admin->is_has_expire,
            'end_at' => (int)$admin->end_at,
            'is_expire' => (int)$admin->is_expire,
        ];
        if ($role) {
            $info['roles'] = [];
        }

        $data = [
            "roleIds" => $arr,
            "data" => $info,
            "roles" => $roleList,
            "admin_type" => Yii::$app->admin->identity->admin_type
        ];

        return $this->asJson(ResponseHelper::send(ApiCode::CODE_SUCCESS, '操作成功', $data));
    }

    // =========================================================================
    // 角色管理 (SaaS 隔离)
    // =========================================================================

    /**
     * 角色列表
     */
    public function actionRoles()
    {
        $form = new RoleListForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getList());
    }

    /**
     * 角色新增/编辑
     */
    public function actionRoleEdit()
    {
        $form = new RoleEditForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->save());
    }

    /**
     * 角色删除
     */
    public function actionRoleDelete()
    {
        $form = new RoleDeleteForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->delete());
    }

    /**
     * 角色详情
     */
    /**
     * 角色详情
     */
    public function actionRole()
    {
        $roleId = $this->request->get('role_id');

        // SaaS 权限校验：只能查看当前商城的角色
        $role = Role::findOne(['id' => $roleId, 'mall_id' => $this->mall_id, 'is_delete' => 0]);

        if (!$role) {
            return $this->asJson(ResponseHelper::send(ApiCode::CODE_FAIL, '角色不存在'));
        }

        $info = $role->attributes;

        // 格式化字段，适配前端 Form 组件
        $info['admin'] = $info['admin'] ? 1 : 0;
        $info['role_id'] = $role->id;
        $info['menu_check_strictly'] = $info['menu_check_strictly'] ? 1 : 0;

        // =========================================================
        // 【核心修复】查询当前角色拥有的 menu_id 集合
        // =========================================================
        $menuIds = RoleMenu::find()
            ->where(['role_id' => $role->id])
            // 既然是关联表，通常物理删除，或者带上 is_delete=0 (取决于你是否有软删除逻辑)
            // 建议加上 is_delete=0 以防万一
            ->andWhere(['is_delete' => 0])
            ->select('menu_id')
            ->column();

        // 将查到的 ID 数组放入返回数据中，前端 this.form.menuIds 会接收到它
        $info['menuIds'] = $menuIds;

        return $this->asJson(ResponseHelper::send(ApiCode::CODE_SUCCESS, '操作成功', $info));
    }

    /**
     * 角色-菜单树选择 (用于角色编辑时的菜单树展示和回显)
     */
    public function actionRoleMenuTreeSelect()
    {
        $role_id = $this->request->get('role_id');

        // 1. 获取该角色已选中的菜单ID
        $checkedKeys = RoleMenu::find()
            ->where(['role_id' => $role_id])
            ->select('menu_id')
            ->column();

        // 2. 获取完整的菜单树 (使用 MenuTreeForm 的 SaaS 逻辑)
        $treeForm = new MenuTreeForm();
        $treeResult = $treeForm->getTree(); // 这里返回的是 ResponseHelper 数组

        $data = [
            "menus" => $treeResult['data'], // 取出 Form 返回的数据部分
            "checkedKeys" => $checkedKeys
        ];

        return $this->asJson(ResponseHelper::send(ApiCode::CODE_SUCCESS, '操作成功', $data));
    }

    // =========================================================================
    // 菜单管理 (系统级，通常仅超管操作)
    // =========================================================================

    /**
     * 菜单列表
     */
    public function actionMenuList()
    {
        $form = new MenuListForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getList());
    }

    /**
     * 「我的常用」可配置功能库(只读菜单目录,供个人快捷看板勾选)。
     * 与 actionMenuList 同源,但刻意不进 permsMap —— 配置个人常用属个人行为,
     * 不应要求「菜单管理(system:menu:list)」权限,否则子账号进配置页即被 AdminFilter 拦成「无操作权限」。
     */
    public function actionShortcutMenus()
    {
        $form = new MenuListForm();
        $form->attributes = $this->request->get();
        // 只给「显示中」的菜单。隐藏菜单(hidden=1)在左侧导航里本就看不到,
        // 它们多是从列表页跳进去的编辑页(goods-edit / diy-edit / freight-edit 这类),
        // 单独当常用入口点进去往往缺参数、打不开。本接口专服务「我的常用」,
        // 这里写死而不是靠前端传,免得哪天调用方漏传又把它们放出来。
        $form->hidden = 0;
        return $this->asJson($form->getList());
    }

    /**
     * 菜单新增/编辑
     */
    public function actionMenuEdit()
    {
        $form = new MenuEditForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->save());
    }

    /**
     * 兼容旧路由: actionAddMenu -> actionMenuEdit
     */
    public function actionAddMenu()
    {
        return $this->actionMenuEdit();
    }

    /**
     * 菜单删除
     */
    public function actionMenuDelete()
    {
        $form = new MenuDeleteForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->delete());
    }

    /**
     * 菜单树 (单纯获取树结构)
     */
    public function actionMenuTree()
    {
        $form = new MenuTreeForm();
        return $this->asJson($form->getTree());
    }

    /**
     * 菜单详情
     */
    public function actionMenu()
    {
        $menuId = $this->request->get('menu_id');
        $menu = \app\models\Menu::findOne(['menu_id' => $menuId]);

        if (!$menu) {
            return $this->asJson(ResponseHelper::send(ApiCode::CODE_FAIL, '菜单不存在'));
        }

        return $this->asJson(ResponseHelper::send(ApiCode::CODE_SUCCESS, '操作成功', $menu->attributes));
    }

    // =========================================================================
    // 管理员列表 (系统级)
    // =========================================================================

    /**
     * 所有管理员列表
     *
     * 【已废弃】旧 AdminListForm 无 mall 隔离、会返回全平台管理员（数据泄漏隐患），
     * 且已被按商城隔离的 user-list(UserListForm) 取代。此处直接关闭，避免越权读取。
     */
    public function actionList()
    {
        return $this->asJson(\app\helpers\ResponseHelper::send(
            ApiCode::CODE_FAIL,
            '该接口已废弃，请使用「用户管理」(user-list)'
        ));
    }
    /**
     * 保存商户菜单个性化配置
     * (SaaS 装修功能)
     */
    public function actionMenuOptionSave()
    {
        $form = new MallMenuOptionForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->save());
    }

    // =========================================================================
    // 快捷菜单管理 (SaaS 工作台)
    // =========================================================================

    /**
     * 获取当前用的快捷菜单
     */
    public function actionShortcutList()
    {
        // ext_data 是动态字段:身份缓存(Admin::refreshIdentityCache,动静分离)刻意不缓存它,
        // 而 AdminBootstrap 是用该缓存重建 identity 的 —— 故 Yii::$app->admin->identity->ext_data 恒为空。
        // 必须实时回库读,否则保存后永远拿到空列表(表现为「保存无效」)。
        $admin = Admin::findOne(Yii::$app->admin->id);
        $shortcuts = ($admin && $admin->ext_data) ? json_decode($admin->ext_data, true) : [];
        if (!is_array($shortcuts)) {
            $shortcuts = [];
        }
        return $this->asJson(ResponseHelper::send(ApiCode::CODE_SUCCESS, '获取成功', ['list' => $shortcuts]));
    }

    /**
     * 保存/更新快捷菜单
     */
    public function actionShortcutUpdate()
    {
        $form = new \app\forms\admin\ShortcutUpdateForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->save());
    }

    // =========================================================================
    // 调度器死信任务（失败重试耗尽的任务，仅超管）
    // =========================================================================

    /**
     * 死信任务列表
     */
    public function actionDeadJobList()
    {
        $form = new \app\forms\admin\ScheduleDeadJobForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getList());
    }

    /**
     * 死信任务人工重投
     */
    public function actionDeadJobRetry()
    {
        $form = new \app\forms\admin\ScheduleDeadJobForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->retry());
    }

    /**
     * 死信任务忽略
     */
    public function actionDeadJobIgnore()
    {
        $form = new \app\forms\admin\ScheduleDeadJobForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->ignore());
    }
    public function actionDeadJobRetryAll()
    {
        $form = new \app\forms\admin\ScheduleDeadJobForm();
        return $this->asJson($form->retryAll());
    }
    public function actionDeadJobIgnoreAll()
    {
        $form = new \app\forms\admin\ScheduleDeadJobForm();
        return $this->asJson($form->ignoreAll());
    }

    /**
     * 调度中心看板
     */
    public function actionSchedulerDashboard()
    {
        $form = new \app\forms\admin\SchedulerDashboardForm();
        return $this->asJson($form->getDashboard());
    }

    /**
     * Cron 任务列表（扫码 + 覆盖合并）
     */
    public function actionCronTaskList()
    {
        $form = new \app\forms\admin\CronTaskForm();
        return $this->asJson($form->getList());
    }

    /**
     * Cron 任务保存（启停 / 自定义表达式）
     */
    public function actionCronTaskSave()
    {
        $form = new \app\forms\admin\CronTaskForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->save());
    }
}
