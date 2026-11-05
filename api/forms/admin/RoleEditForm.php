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
use app\models\BaseModel;
use app\models\Role;
use app\models\RoleMenu;
use Yii;

class RoleEditForm extends BaseModel
{
    public $role_id;
    public $role_name;
    public $role_key;
    public $role_sort;
    public $status;
    public $remark;
    public $menu_check_strictly;
    public $admin;
    public $menuIds; // 这是一个数组 [1, 2, 3]

    public function rules()
    {
        return [
            // 1. 必填项
            [['role_name', 'role_key'], 'required', 'message' => '{attribute}不能为空'],

            // 2. 整数校验 (status, sort)
            [['role_id', 'role_sort', 'status'], 'integer'],

            // 3. 关键修复：menu_check_strictly 和 admin 建议转为整数存库 (tinyint)
            // 配合前端传来的 1/0
            [['menu_check_strictly', 'admin'], 'default', 'value' => 0],
            [['menu_check_strictly', 'admin'], 'integer'],

            // 4. 字符串及其长度限制
            [['role_name', 'role_key'], 'string', 'max' => 50],
            [['remark'], 'string', 'max' => 255],

            // 5. 关键修复：menuIds 必须是数组，且里面的每一项都是整数或数字字符串
            [['menuIds'], 'safe'],
            // 如果允许为空数组，可以加 default
            [['menuIds'], 'default', 'value' => []],
        ];
    }

    public function attributeLabels()
    {
        return [
            'role_name' => '角色名称',
            'role_key' => '权限字符',
            'menuIds' => '菜单权限',
        ];
    }

    public function save()
    {
        // 1. 验证数据
        if (!$this->validate()) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $this->getErrorMsg());
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $model = null;

            // 2. SaaS 权限控制：获取或创建角色模型
            if ($this->role_id) {
                // 编辑模式：必须校验 mall_id 防止越权
                $model = Role::findOne([
                    'id' => $this->role_id,
                    'mall_id' => Yii::$app->mall->id,
                    'is_delete' => 0
                ]);
                if (!$model) {
                    throw new \Exception('角色不存在或无权操作');
                }
            } else {
                // 新增模式
                $model = new Role();
                $model->mall_id = Yii::$app->mall->id; // 绑定当前商城
                $model->created_by = Yii::$app->admin->identity->getId();
                $model->created_at = time();
            }

            // 3. 赋值基础字段
            $model->role_name = $this->role_name;
            $model->role_key = $this->role_key;
            $model->role_sort = $this->role_sort ?: 0;
            $model->status = $this->status;
            $model->remark = $this->remark;

            // 强制转换为 int 存储
            $model->menu_check_strictly = intval($this->menu_check_strictly);
            $model->admin = intval($this->admin);

            // 4. 保存角色主表
            if (!$model->save()) {
                throw new \Exception($this->getErrorMsg($model));
            }

            // =========================================================
            // 5. 【核心修复】手动保存菜单关联数据 (RoleMenu)
            // =========================================================

            // 5.0 越权天花板：操作员只能分配自己角色已有的菜单，给不出自己没有的(防提权)。
            //     超管 / 商城主账号(FOUNDER) 不受限（FOUNDER 在本店全权）。
            $identity = Yii::$app->admin->identity;
            if ($identity && !$identity->isMallSuper()) {
                $ownMenuIds = RoleMenu::find()
                    ->where(['role_id' => $identity->role_id])
                    ->select('menu_id')->column();
                $ownSet = array_flip(array_map('strval', $ownMenuIds));
                foreach ((array)$this->menuIds as $mid) {
                    if ($mid === '' || $mid === null) continue;
                    if (!isset($ownSet[(string)$mid])) {
                        throw new \Exception('不能分配超出自身权限范围的菜单');
                    }
                }
            }

            // 5.1 先删除旧的权限
            RoleMenu::deleteAll(['role_id' => $model->id]);

            // 5.2 循环插入新权限
            if (!empty($this->menuIds) && is_array($this->menuIds)) {
                $batchData = [];
                foreach ($this->menuIds as $menuId) {
                    // 过滤无效ID
                    if (empty($menuId)) continue;

                    $roleMenu = new RoleMenu();
                    $roleMenu->role_id = $model->id;
                    $roleMenu->menu_id = (string)$menuId; // menu_id 是字符串类型


                    if (!$roleMenu->save()) {
                        throw new \Exception($this->getErrorMsg($roleMenu));
                    }
                }

                // 优化提示：如果数据量大，建议使用 batchInsert 批量插入
                // Yii::$app->db->createCommand()->batchInsert(RoleMenu::tableName(), ['role_id', 'menu_id', 'created_at'], $batchData)->execute();
            }

            $transaction->commit();
            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '保存成功');

        } catch (\Exception $e) {
            $transaction->rollBack();
            return ResponseHelper::send(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }
}