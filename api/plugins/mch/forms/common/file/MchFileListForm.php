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

class MchFileListForm extends BaseModel
{
    public $mch_id;
    public $page;
    public $limit;
    public $group_id;
    public $type; // image, video, doc
    public $keyword;

    public function rules()
    {
        return [
            [['mch_id'], 'required'],
            [['mch_id', 'page', 'limit', 'group_id'], 'integer'],
            [['type', 'keyword'], 'string'],
            [['page'], 'default', 'value' => 1],
            [['limit'], 'default', 'value' => 20],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        // 选择器可选每页 15/30/50/100,这里封顶 100,防止一次拉全表
        $this->limit = max(1, min(100, (int) $this->limit));

        $query = File::find()->where([
            'mall_id' => Yii::$app->mall->id,
            'mch_id' => $this->mch_id, // 【核心】严格过滤
            'is_delete' => 0
        ]);

        if ($this->group_id) {
            $query->andWhere(['group_id' => $this->group_id]);
        }

        if ($this->type) {
            $query->andWhere(['type' => $this->type]);
        }

        if ($this->keyword) {
            $query->andWhere(['like', 'name', $this->keyword]);
        }

        $list = $query->orderBy('id DESC')
            ->page($pagination, $this->limit, $this->page)
            ->asArray()
            ->all();

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => '获取成功',
            'data' => [
                'list' => $list,
                'pagination' => $pagination
            ]
        ];
    }
}