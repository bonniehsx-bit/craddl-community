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
use app\models\AdminRole;
use app\models\BaseModel;
use app\models\Role;
use app\models\RoleMenu;
use Yii;

class RoleDeleteForm extends BaseModel
{
    public $role_id;

    public function rules()
    {
        return [
            [['role_id'], 'required'],
            [['role_id'], 'integer'],
        ];
    }

    public function delete()
    {
        if (!$this->validate()) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $this->getErrorMsg());
        }

        // 【SaaS】删除：只能查当前商城的
        $role = Role::findOne([
            'id' => $this->role_id,
            'mall_id' => Yii::$app->mall->id,
            'is_delete' => 0
        ]);

        if (!$role) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '角色不存在或无权操作');
        }

        // 检查是否有用户正在使用该角色
        $exist = AdminRole::find()
            ->alias('ar')
            ->innerJoin(['r' => Role::tableName()], 'r.id = ar.role_id')
            ->where(['ar.role_id' => $role->id])
            ->exists();

        if ($exist) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '该角色关联了部分系统用户，请先移除系统用户的角色后再删除');
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // 删除关联菜单权限
            RoleMenu::deleteAll(['role_id' => $role->id]);
            // 删除角色本身 (软删除)
            $role->is_delete = 1;
            if (!$role->save()) {
                throw new \Exception($this->getErrorMsg($role));
            }

            $transaction->commit();
            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '删除成功');

        } catch (\Exception $e) {
            $transaction->rollBack();
            return ResponseHelper::send(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }
}