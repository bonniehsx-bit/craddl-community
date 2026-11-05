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
use app\models\File;
use app\models\FileGroup;
use Yii;

class MchFileGroupDeleteForm extends BaseModel
{
    public $id;
    public $mch_id;

    public function rules()
    {
        return [
            [['id', 'mch_id'], 'required'],
            [['id', 'mch_id'], 'integer'],
        ];
    }

    public function delete()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $group = FileGroup::findOne([
            'id' => $this->id,
            'mch_id' => $this->mch_id, // 【核心】权限校验
            'is_delete' => 0,
            'mall_id' => Yii::$app->mall->id
        ]);

        if (!$group) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '分组不存在或无权操作');
        }

        // 软删除
        $group->is_delete = 1;
        $group->deleted_at = time();

        if ($group->save()) {
            // 可选：将该分组下的文件移动到默认分组(group_id=0)
            File::updateAll(['group_id' => 0], [
                'group_id' => $this->id,
                'mch_id' => $this->mch_id
            ]);

            return $this->apiResponse(ApiCode::CODE_SUCCESS, '删除成功');
        } else {
            return $this->responseErrorInfo($group);
        }
    }
}