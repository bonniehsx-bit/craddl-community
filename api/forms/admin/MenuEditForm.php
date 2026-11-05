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
use Yii;

class MenuEditForm extends BaseModel
{
    public $menu_id; // 【关键修改】使用 menu_id 来判断更新，而不是 id

    public $parent_id;
    public $menu_name;
    public $plugin;
    public $icon;
    public $menu_type;
    public $name;
    public $order_num;
    public $is_cache;
    public $is_frame;
    public $hidden;
    public $status;
    public $path;
    public $component;
    public $breadcrumb;
    public $query;
    public $perms;
    public $show_tag;
    public $active_menu;

    public function rules()
    {
        return [
            [['menu_name', 'menu_type', 'order_num'], 'required'],
            [['order_num', 'status', 'is_cache', 'is_frame', 'hidden', 'breadcrumb', 'show_tag'], 'integer'],
            [['menu_id', 'menu_name', 'plugin', 'icon', 'menu_type', 'name', 'path', 'component', 'query', 'perms', 'active_menu', 'parent_id'], 'string'],
            [['parent_id'], 'default', 'value' => '0'],
            [['order_num'], 'default', 'value' => 0],
            [['plugin'], 'default', 'value' => 'mall'],
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $this->getErrorMsg());
        }

        if (Yii::$app->admin->identity->admin_type != Admin::ADMIN_TYPE_SUPER) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '无权操作系统菜单');
        }

        $model = null;
        // 使用 menu_id 判断是否存在
        if ($this->menu_id) {
            $model = Menu::findOne(['menu_id' => $this->menu_id]);
        }

        if (!$model) {
            $model = new Menu();
            $model->created_at = time();
            $isNew = true;
        } else {
            $isNew = false;
        }

        if ($model->menu_id && $this->parent_id == $model->menu_id) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '父级目录不可是自己');
        }

        $model->menu_name = $this->menu_name;
        $model->plugin    = $this->plugin;
        $model->icon      = $this->icon;
        $model->menu_type = $this->menu_type;
        $model->name      = $this->name;
        $model->order_num = $this->order_num;
        $model->is_cache  = $this->is_cache;
        $model->is_frame  = $this->is_frame;
        $model->hidden    = $this->hidden;
        $model->status    = $this->status;
        $model->path      = $this->path;
        $model->component = $this->component;
        $model->breadcrumb = $this->breadcrumb;
        $model->query     = $this->query;
        $model->perms     = $this->perms;
        $model->show_tag  = $this->show_tag;
        $model->active_menu = $this->active_menu;
        $model->parent_id = $this->parent_id;

        // 生成 menu_id
        if ($isNew) {
            if ($model->parent_id != '0') {
                $parent = Menu::findOne(['menu_id' => $model->parent_id]);
                if ($parent) {
                    $model->menu_id = $parent->menu_id . time();
                } else {
                    $model->menu_id = (string)time();
                }
            } else {
                $model->menu_id = (string)time();
            }
        }

        if (!$model->save()) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $this->getErrorMsg($model));
        }

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '保存成功');
    }
}