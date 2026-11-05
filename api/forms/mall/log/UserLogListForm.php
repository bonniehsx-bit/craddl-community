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

namespace app\forms\mall\log;

use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\models\BaseModel;
use app\models\User;
use app\models\UserActionLog;

class UserLogListForm extends BaseModel
{
    public $search_date;
    public $id;         // 用户 ID(沿用原参数名,前端老调用不受影响)
    public $keyword;    // 在 备注 / 变更前后内容 里模糊搜

    public function rules()
    {
        return [
            [['id'], 'integer'],
            [['keyword'], 'string'],
            [['search_date'], 'safe'],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $query = UserActionLog::find()->andWhere(['mall_id' => $this->mall_id]);

        if ($this->search_date && is_array($this->search_date) && count($this->search_date) === 2) {
            $query->andWhere(['>=', 'created_at', strtotime($this->search_date[0])]);
            $query->andWhere(['<=', 'created_at', strtotime($this->search_date[1])]);
        }
        if ($this->id) {
            $query->andWhere(['user_id' => $this->id]);
        }
        // before/after 是 text,LIKE 走不了索引,所以只在明确输了关键字时才加这段。
        if ($this->keyword !== null && $this->keyword !== '') {
            $query->andWhere([
                'or',
                ['like', 'remarks', $this->keyword],
                ['like', 'before_update', $this->keyword],
                ['like', 'after_update', $this->keyword],
            ]);
        }

        $list = $query->orderBy('id desc')->page($pagination, $this->limit, $this->page)->asArray()->all();

        // 补用户昵称/手机:只看 user_id 定位不到人。批量取,别在循环里查(N+1)。
        $userIds = array_values(array_filter(array_column($list, 'user_id')));
        $users = $userIds
            ? User::find()->select(['id', 'nickname', 'mobile'])
                ->where(['id' => $userIds])->indexBy('id')->asArray()->all()
            : [];
        foreach ($list as &$row) {
            $u = $users[$row['user_id']] ?? null;
            $row['user_nickname'] = $u['nickname'] ?? '';
            $row['user_mobile']   = $u['mobile'] ?? '';
        }
        unset($row);

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', ['list' => $list, 'pagination' => $pagination]);
    }
}
