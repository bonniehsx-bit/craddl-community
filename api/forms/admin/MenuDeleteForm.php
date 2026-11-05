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
use app\models\BaseModel;
use app\models\Menu;
use app\models\RoleMenu;
use Yii;

class MenuDeleteForm extends BaseModel
{
    public $menu_id; // 注意：这里传的是字符串 menu_id，不是自增 id

    public function rules()
    {
        return [
            [['menu_id'], 'required'],
            [['menu_id'], 'string'],
        ];
    }

    public function delete()
    {
        if (!$this->validate()) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $this->getErrorMsg());
        }

        // 【权限控制】只有超级管理员可以删除系统菜单
        if (Yii::$app->admin->identity->admin_type != Admin::ADMIN_TYPE_SUPER) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '无权操作系统菜单');
        }

        $menu = Menu::findOne(['menu_id' => $this->menu_id]);
        if (!$menu) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '菜单不存在');
        }

        // 检查是否有子菜单
        $hasChild = Menu::find()->where(['parent_id' => $this->menu_id, 'is_delete' => 0])->exists();
        if ($hasChild) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '请先删除子菜单');
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // 删除角色-菜单关联
            RoleMenu::deleteAll(['menu_id' => $menu->menu_id]);

            // 删除菜单 (软删除)
            $menu->is_delete = 1;
            if (!$menu->save()) {
                throw new \Exception($this->getErrorMsg($menu));
            }

            $transaction->commit();
            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '删除成功');
        } catch (\Exception $e) {
            $transaction->rollBack();
            return ResponseHelper::send(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }
}