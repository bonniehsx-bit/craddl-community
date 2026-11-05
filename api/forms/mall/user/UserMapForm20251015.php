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

namespace app\forms\mall\user;

use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\models\BaseModel;
use app\models\User;
use yii\db\Expression;

class UserMapForm20251015 extends BaseModel
{
    public $parent_id;
    public $keyword;

    public function rules()
    {
        return [
            [['keyword'], 'string'],
            [['keyword'], 'trim'],
            [['parent_id'], 'default', 'value' => 0],
            [['parent_id'], 'integer'],
        ];
    }

    public function search()
    {
        $query = User::find()->where([
            'mall_id' => $this->mall_id,
            'is_delete' => 0,
        ]);

        if (!empty($this->keyword)) {
            $query->andWhere(['or',
                ['like', 'nickname', $this->keyword],
                ['like', 'mobile', $this->keyword],
                ['=', 'id', $this->keyword]
            ]);
        } else {
            $query->andWhere(['parent_id' => $this->parent_id ?: 0]);
        }

        $subQuery = User::find()->select('id')->where('parent_id = u.id AND is_delete = 0');

        $list = $query->alias('u')
            ->select([
                'id',
                'nickname',
                'mobile',
                'avatar_url',
                'created_time'
            ])
            ->asArray()
            ->all();

        // 同时，我们需要获取下级数量以在详情中显示
        $userIds = array_column($list, 'id');
        $childrenCounts = [];
        if (!empty($userIds)) {
            $childrenCounts = User::find()
                ->select('parent_id, COUNT(id) as count')
                ->where(['parent_id' => $userIds, 'is_delete' => 0])
                ->groupBy('parent_id')
                ->asArray()
                ->all();
            $childrenCounts = array_column($childrenCounts, 'count', 'parent_id');
        }

        $list = array_map(function ($item) use ($childrenCounts) {
            $item['is_leaf'] = (bool)$item['is_leaf'];
            $item['children_count'] = $childrenCounts[$item['id']] ?? 0; // 附加下级数量
            return $item;
        }, $list);

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, 'ok', [
            'list' => $list
        ]);
    }
}