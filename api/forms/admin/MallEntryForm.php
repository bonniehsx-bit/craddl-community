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
use app\models\BaseModel;
use app\models\Mall;
use Yii;

class MallEntryForm extends BaseModel
{
    public $mall_id;

    public function rules()
    {
        return [
            [['mall_id'], 'required'],
            [['mall_id'], 'integer'],
        ];
    }

    public function enter()
    {
        if (!$this->validate()) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $this->getErrorMsg());
        }

        $mall = Mall::findOne(['id' => $this->mall_id, 'is_delete' => 0]);
        if (!$mall) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '商城不存在');
        }

        if ($mall->is_stop == 1) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '该商城已停用，无法进入');
        }

        /** @var Admin $admin */
        $admin = Yii::$app->admin->identity;

        // 鉴权逻辑：判断当前用户是否有权进入该 mall_id
        if ($admin->admin_type != Admin::ADMIN_TYPE_SUPER) {
            // 1. 如果账号绑定了 mall_id，必须匹配
            if ($admin->mall_id > 0 && $admin->mall_id != $this->mall_id) {
                return ResponseHelper::send(ApiCode::CODE_FAIL, '您无权进入该商城');
            }

            // 2. 如果未绑定，检查是否是创建者或在关联表中
            if ($admin->mall_id == 0) {
                $isOwner = ($mall->admin_id == $admin->id);
                $isAssigned = AdminMall::find()->where([
                    'admin_id' => $admin->id,
                    'mall_id' => $this->mall_id,
                    'is_delete' => 0
                ])->exists();

                if (!$isOwner && !$isAssigned) {
                    return ResponseHelper::send(ApiCode::CODE_FAIL, '您无权进入该商城');
                }
            }
        }

        // 可以在这里记录登录日志...

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '允许进入', [
            'mall_id' => $mall->id,
            'name' => $mall->name
        ]);
    }
}