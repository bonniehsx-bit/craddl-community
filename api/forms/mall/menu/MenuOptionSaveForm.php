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

namespace app\forms\mall\menu;

use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\models\BaseModel;
use app\models\MallMenuOption;
use Yii;

class MenuOptionSaveForm extends BaseModel
{
    public $menu_id;
    public $custom_name;
    public $is_hide;
    public $custom_sort;
    public $custom_parent_id;
    public $custom_path;
    public $is_home;

    public function rules()
    {
        return [
            [['menu_id'], 'required'],
            [['menu_id', 'custom_name', 'custom_parent_id', 'custom_path'], 'string'],
            [['is_hide', 'custom_sort', 'is_home'], 'integer'],
            [['is_hide'], 'default', 'value' => 0],
            [['custom_sort'], 'default', 'value' => 0],
            [['is_home'], 'default', 'value' => 0],
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $this->getErrorMsg());
        }

        if ($this->custom_parent_id && $this->custom_parent_id == $this->menu_id) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '父级菜单不能选择自己');
        }

        $mallId = Yii::$app->mall->id;

        $option = MallMenuOption::findOne([
            'mall_id' => $mallId,
            'menu_id' => $this->menu_id
        ]);

        if (!$option) {
            $option = new MallMenuOption();
            $option->mall_id = $mallId;
            $option->menu_id = $this->menu_id;
            $option->created_at = time();
        }

        $option->custom_name = $this->custom_name;
        $option->custom_path = $this->custom_path;
        $option->custom_parent_id = $this->custom_parent_id;

        // 如果当前项被设为主页，互斥地清除本商城其他人的主页标记
        if ($this->is_home == 1) {
            MallMenuOption::updateAll(['is_home' => 0], ['mall_id' => $mallId]);
        }
        $option->is_home = $this->is_home;

        $option->is_hide = $this->is_hide;
        $option->custom_sort = $this->custom_sort;
        $option->updated_at = time();
        if ($option->save()) {
            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '菜单配置已保存');
        } else {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $this->getErrorMsg($option));
        }
    }
}
