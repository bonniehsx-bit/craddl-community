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

namespace app\forms\mall\plugin;

use app\core\ApiCode;
use app\models\AdminPluginShortcut;
use app\models\BaseModel;
use Yii;

class PluginShortcutToggleForm extends BaseModel
{
    public $plugin_name;

    public function rules()
    {
        return [
            [['plugin_name'], 'required'],
            [['plugin_name'], 'string'],
        ];
    }

    public function toggle()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $adminId = Yii::$app->admin->id;
        // Super admins may not have an active mall, depending on router context. 
        // But for plugin market, they must be inside a mall.
        $mallId = Yii::$app->mall ? Yii::$app->mall->id : 0; 

        if (!$mallId) {
            return [
                'code' => ApiCode::CODE_ERROR,
                'msg' => '非法操作，缺乏商户上下文',
            ];
        }

        $model = AdminPluginShortcut::find()->where([
            'mall_id' => $mallId,
            'admin_id' => $adminId,
            'plugin_name' => $this->plugin_name,
        ])->one();

        if ($model) {
            // Toggle
            $model->is_delete = $model->is_delete == 1 ? 0 : 1;
            if (!$model->save()) {
                return $this->responseErrorInfo($model);
            }
        } else {
            // Create
            $model = new AdminPluginShortcut();
            $model->mall_id = $mallId;
            $model->admin_id = $adminId;
            $model->plugin_name = $this->plugin_name;
            $model->is_delete = 0;
            if (!$model->save()) {
                return $this->responseErrorInfo($model);
            }
        }

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => $model->is_delete == 1 ? '已取消星标置顶' : '已添加至快捷工作台',
            'data' => [
                'is_shortcut' => $model->is_delete == 0
            ]
        ];
    }
}
