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

namespace app\plugins\mch\forms\admin\clerk;

use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\models\BaseModel;
use app\models\User;
use app\plugins\mch\models\MchClerk;
use Yii;

class UserSearchForm extends BaseModel
{
    public $keyword;

    public function rules()
    {
        return [
            [['keyword'], 'trim'],
            [['keyword'], 'string'],
        ];
    }

    public function search()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $currentMch = Yii::$app->mch;
        // 1. 找出所有已成为本店成员（无论店长还是店员）的用户ID
        $existingUserIds = MchClerk::find()->select('user_id')
            ->where(['mch_id' => $currentMch->id, 'is_delete' => 0])
            ->column();
        // 2. 查询用户，并排除上述ID
        $query = User::find()->where(['is_delete' => 0])
            ->andWhere(['NOT IN', 'id', $existingUserIds]);

        if (!$this->keyword) {
            return ResponseHelper::send(ApiCode::CODE_SUCCESS, []);
        }
        $query->andWhere(['id' => $this->keyword]);
        $list = $query->select(['id', 'nickname', 'avatar_url', 'mobile'])
            ->limit(20)
            ->asArray()
            ->all();

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['list' => $list]);
    }
}