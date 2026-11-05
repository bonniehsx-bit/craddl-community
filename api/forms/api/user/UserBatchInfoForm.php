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

namespace app\forms\api\user;

use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\models\BaseModel;
use app\models\User;

class UserBatchInfoForm extends BaseModel
{
    public $ids; // 接收 ID 字符串，例如 "101,102,105"

    public function rules()
    {
        return [
            [['ids'], 'required'],
            [['ids'], 'string'],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return ResponseHelper::send(ApiCode::CODE_ERROR, $this->getErrorMsg());
        }

        // 1. 将字符串转为数组并去重
        $idArray = explode(',', $this->ids);
        $idArray = array_unique(array_filter($idArray));

        if (empty($idArray)) {
            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '获取成功', (object)[]);
        }

        // 2. 查询数据库
        $users = User::find()->where([
            'mall_id' => \Yii::$app->mall->id,
            'is_delete' => 0,
            'id' => $idArray
        ])->select(['id', 'nickname', 'avatar_url'])->asArray()->all();

        // 3. 构建以 ID 为 Key 的结果集，方便前端匹配
        $resMap = [];
        foreach ($users as $user) {
            $resMap[$user['id']] = [
                'nickname'   => $user['nickname'],
                'avatar_url' => $user['avatar_url'],
            ];
        }

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '获取成功', $resMap);
    }
}