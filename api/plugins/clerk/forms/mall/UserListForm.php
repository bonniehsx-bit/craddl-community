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

namespace app\plugins\clerk\forms\mall;

use app\core\ApiCode;
use app\models\BaseModel;
use app\plugins\clerk\models\ClerkUser;
use Yii;

class UserListForm extends BaseModel
{
    public $keyword;
    public $page;
    public $limit;

    public function rules()
    {
        return [
            [['keyword'], 'string'],
            [['page'], 'default', 'value' => 1],
            [['limit'], 'default', 'value' => 20],
        ];
    }

    public function search()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $query = ClerkUser::find()->alias('c')
            ->where(['c.mall_id' => Yii::$app->mall->id, 'c.is_delete' => 0])
            ->joinWith(['user u' => function ($query) {
                $query->select('id, nickname, avatar_url, mobile');
            }]);

        if ($this->keyword) {
            $query->andWhere([
                'or',
                ['like', 'c.name', $this->keyword],
                ['like', 'u.nickname', $this->keyword],
                ['like', 'u.mobile', $this->keyword]
            ]);
        }

        $pagination = null;
        $list = $query->orderBy(['c.id' => SORT_DESC])
            ->page($pagination, $this->limit, $this->page)
            ->asArray()
            ->all();

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => '请求成功',
            'data' => [
                'list' => $list,
                'pagination' => $pagination
            ]
        ];
    }
}
