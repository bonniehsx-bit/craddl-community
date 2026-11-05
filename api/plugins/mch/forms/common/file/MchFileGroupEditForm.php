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

namespace app\plugins\mch\forms\common\file;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\FileGroup;
use Yii;

class MchFileGroupEditForm extends BaseModel
{
    public $id;
    public $name;
    public $mch_id;

    public function rules()
    {
        return [
            [['name', 'mch_id'], 'required'],
            [['id', 'mch_id'], 'integer'],
            [['name'], 'trim'],
            [['name'], 'string', 'max' => 8],
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        if ($this->id) {
            // 编辑：必须校验 mch_id，防止越权修改其他商家的分组
            $group = FileGroup::findOne([
                'id' => $this->id,
                'mch_id' => $this->mch_id,
                'is_delete' => 0,
                'mall_id' => Yii::$app->mall->id
            ]);
            if (!$group) {
                return $this->apiResponse(ApiCode::CODE_FAIL, '分组不存在或无权操作');
            }
        } else {
            // 新增
            $group = new FileGroup();
            $group->mall_id = Yii::$app->mall->id;
            $group->mch_id = $this->mch_id;
            $group->is_site = 0;
            $group->is_delete = 0;
        }

        $group->name = $this->name;

        if ($group->save()) {
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '保存成功', ['group' => $group]);
        } else {
            return $this->responseErrorInfo($group);
        }
    }
}