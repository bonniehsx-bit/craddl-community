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

namespace app\forms\admin;

use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\models\Admin;
use app\models\AdminMall;
use app\models\AdminRole;
use app\models\BaseModel;
use Yii;

class UserEditForm extends BaseModel
{
    public $id;
    public $username;
    public $password;
    public $nickname;
    public $mobile;
    public $avatar;
    public $email;
    public $remark;
    public $admin_type;
    public $roleIds; // 数组
    public $is_has_expire;
    public $end_at;

    public function rules()
    {
        return [
            [['username', 'nickname', 'mobile', 'roleIds'], 'required'],
            [['id', 'admin_type', 'is_has_expire', 'end_at'], 'integer'],
            [['username', 'nickname', 'mobile', 'password', 'avatar', 'email', 'remark'], 'string'],
            [['password'], 'string', 'min' => 6, 'max' => 20],
            [['roleIds'], 'each', 'rule' => ['integer']], // 验证 roleIds 必须是整数数组
            [['email'], 'email'],
            [['is_has_expire'], 'default', 'value' => 0],
            [['end_at'], 'default', 'value' => 0],
        ];
    }

    public function attributeLabels()
    {
        return [
            'username' => '用户名',
            'nickname' => '昵称',
            'mobile'   => '手机号',
            'roleIds'  => '角色',
            'password' => '密码'
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $this->getErrorMsg());
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // 获取或创建模型（包含SaaS权限检查）
            $adminResult = $this->getAdminModel();

            // 如果返回的是数组，说明是 ResponseHelper 的错误信息
            if (is_array($adminResult)) {
                return $adminResult;
            }
            /** @var Admin $admin */
            $admin = $adminResult;

            // --- 0. 登录账号变更检测（编辑模式才允许；变更后需踢出旧 Token） ---
            $usernameChanged = false;
            if (!$admin->isNewRecord && !empty($this->username) && $this->username !== $admin->username) {
                $admin->username = $this->username;
                $usernameChanged = true;
            }

            // --- 1. 基础赋值 ---
            $admin->nickname = $this->nickname;
            $admin->name     = $this->nickname; // 保持 name 和 nickname 同步
            $admin->mobile   = $this->mobile;
            $admin->avatar   = $this->avatar;
            $admin->email    = $this->email;
            $admin->remark   = $this->remark;

            // --- 1.5 账号有效期（启停账号）---
            $admin->is_has_expire = $this->is_has_expire ? 1 : 0;
            $admin->end_at        = $this->is_has_expire ? (int)$this->end_at : 0;
            $admin->is_expire     = ($admin->is_has_expire && $admin->end_at && $admin->end_at < time()) ? 1 : 0;

            // --- 2. 密码处理 ---
            if (!empty($this->password)) {
                $admin->password = Yii::$app->security->generatePasswordHash($this->password);
                $admin->auth_key = Yii::$app->security->generateRandomString();
            }

            // --- 3. 角色ID处理 (主表字段) ---
            if (is_array($this->roleIds) && count($this->roleIds) > 0) {
                $admin->role_id = $this->roleIds[0]; // 主角色ID
            } else {
                throw new \Exception('请至少选择一个角色');
            }

            // --- 4. SaaS 核心：MallID 注入 ---
            // 如果是新增，且不是超管，必须强制绑定到当前商城
            if ($admin->isNewRecord) {
                if (Yii::$app->admin->identity->admin_type != Admin::ADMIN_TYPE_SUPER) {
                    if (isset(Yii::$app->mall)) {
                        $admin->mall_id = Yii::$app->mall->id;
                    }
                }
            }

            // --- 5. 保存主表 ---
            if (!$admin->save()) {
                throw new \Exception($this->getErrorMsg($admin));
            }

            // --- 6. 处理 AdminMall 关联表 (兼容旧逻辑) ---
            if ($admin->admin_type == Admin::ADMIN_TYPE_OPERATOR) {
                $this->updateAdminMall($admin);
            }

            // --- 7. 处理 AdminRole 关联表 ---
            $this->updateAdminRoles($admin);

            // --- 8. 登录账号变更后强制踢出（旧 Token 全部失效，员工需用新账号重登）---
            if (!empty($usernameChanged)) {
                Admin::invalidateAuth($admin->id);
            }

            $transaction->commit();
            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '保存成功');

        } catch (\Exception $e) {
            $transaction->rollBack();
            return ResponseHelper::send(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }

    /**
     * 获取或创建 Admin 模型，包含权限检查和查重逻辑
     * @return Admin|array
     */
    private function getAdminModel()
    {
        $currentAdmin = Yii::$app->admin->identity;

        if ($this->id) {
            // [编辑模式]
            // SaaS 限制：必须带上 mall_id 查询，防止越权修改其他商城的员工
            $query = Admin::find()->where(['id' => $this->id, 'is_delete' => 0]);

            if ($currentAdmin->admin_type != Admin::ADMIN_TYPE_SUPER) {
                if (isset(Yii::$app->mall)) {
                    $query->andWhere(['mall_id' => Yii::$app->mall->id]);
                } else {
                    // 如果当前没有 mall 上下文，只允许查自己创建的
                    $query->andWhere(['created_by' => $currentAdmin->id]);
                }
            }

            $admin = $query->one();
            if (!$admin) {
                return ResponseHelper::send(ApiCode::CODE_FAIL, '用户不存在或无权操作');
            }

            // 查重：手机号 (排除自己)
            $exist = Admin::find()->where(['mobile' => $this->mobile, 'is_delete' => 0])
                ->andWhere(['!=', 'id', $this->id])->exists();
            if ($exist) {
                return ResponseHelper::send(ApiCode::CODE_FAIL, '手机号已被占用');
            }

            // 查重：登录账号 (排除自己；允许修改但需全局唯一)
            if (!empty($this->username) && $this->username !== $admin->username) {
                $existUser = Admin::find()
                    ->where(['username' => $this->username, 'is_delete' => 0])
                    ->andWhere(['!=', 'id', $this->id])
                    ->exists();
                if ($existUser) {
                    return ResponseHelper::send(ApiCode::CODE_FAIL, '登录账号已被占用');
                }
            }

        } else {
            // [新增模式]
            // 权限检查：非超管不能创建“子账户(Founder)”
            if ($this->admin_type == Admin::ADMIN_TYPE_FOUNDER) {
                if ($currentAdmin->admin_type != Admin::ADMIN_TYPE_SUPER) {
                    return ResponseHelper::send(ApiCode::CODE_FAIL, '您的账户类型不可创建子账户');
                }
            }

            // 查重：用户名 (全局唯一)
            $existUser = Admin::find()->where(['username' => $this->username, 'is_delete' => 0])->exists();
            if ($existUser) {
                return ResponseHelper::send(ApiCode::CODE_FAIL, '用户名已被占用');
            }
            // 查重：手机号 (全局唯一)
            $existMobile = Admin::find()->where(['mobile' => $this->mobile, 'is_delete' => 0])->exists();
            if ($existMobile) {
                return ResponseHelper::send(ApiCode::CODE_FAIL, '手机号已被占用');
            }

            $admin = new Admin();
            $admin->username   = $this->username;
            $admin->admin_type = $this->admin_type;
            $admin->created_by = $currentAdmin->id;
        }

        return $admin;
    }

    /**
     * 更新 AdminMall 关联表
     */
    private function updateAdminMall($admin)
    {
        // 只有在商城环境下才操作
        if (!isset(Yii::$app->mall)) {
            return;
        }

        $adminMall = AdminMall::findOne(['admin_id' => $admin->id, 'is_delete' => 0]);
        if (!$adminMall) {
            $adminMall = new AdminMall();
            $adminMall->admin_id = $admin->id;
            $adminMall->created_at = time();
        }

        $adminMall->mall_id = Yii::$app->mall->id;
        $adminMall->role    = AdminMall::OPERATOR; // 默认为操作员

        if (!$adminMall->save()) {
            throw new \Exception($this->getErrorMsg($adminMall));
        }
    }

    /**
     * 更新 AdminRole 关联表
     */
    private function updateAdminRoles($admin)
    {
        AdminRole::deleteAll(['admin_id' => $admin->id]);

        foreach ($this->roleIds as $roleId) {
            $relation = new AdminRole();
            $relation->admin_id = $admin->id;
            $relation->role_id  = $roleId;
            if (!$relation->save()) {
                throw new \Exception($this->getErrorMsg($relation));
            }
        }
    }
}