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
use Yii;

class MchFileDeleteForm extends BaseModel
{
    public $ids; // 数组或逗号分隔字符串
    public $mch_id;

    public function rules()
    {
        return [
            [['ids', 'mch_id'], 'required'],
            [['mch_id'], 'integer'],
            [['ids'], 'safe']
        ];
    }

    public function delete()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $idList = is_array($this->ids) ? $this->ids : explode(',', $this->ids);

        // 软删除
        $count = File::updateAll(
            ['is_delete' => 1, 'deleted_at' => time()],
            [
                'id' => $idList,
                'mch_id' => $this->mch_id, // 【核心】权限校验
                'mall_id' => Yii::$app->mall->id
            ]
        );

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '删除成功', ['res' => $count]);
    }
}