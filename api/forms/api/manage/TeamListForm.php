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

namespace app\forms\api\manage;

use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\models\BaseModel;
use app\models\Level;

// 你需要显示等级名称
use app\models\User;
use app\models\UserPath;
use Yii;

/**
 * [最终精简版] 团队成员列表 Form
 * 只负责查询和分页，不进行任何实时统计
 */
class TeamListForm extends BaseModel
{
    public $page;
    public $limit = 15;
    public $level = 1;      // 筛选层级: 1=直推, 2=间推(2级及以上)
    public $level_id;   // 筛选用户自身的等级ID
    public $keyword;    // 按用户昵称或ID搜索

    public function rules()
    {
        return [
            [['keyword'], 'string'],
            [['level_id'], 'safe'],
            [['level', 'page', 'limit'], 'integer'],
            [['level'], 'default', 'value' => 1],
            [['page'], 'default', 'value' => 1],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $currentUserId = Yii::$app->user->id;

        // 基于 UserPath 构建查询
        if ($this->level >= 2) {
            // 间推：path 包含当前用户但 parent_id 不是当前用户
            $query = UserPath::find()->alias('up')
                ->innerJoin(['u' => User::tableName()], 'u.id = up.user_id')
                ->where(['like', 'up.path', \app\services\UserService::subtreeLike($currentUserId), false])
                ->andWhere(['!=', 'up.user_id', $currentUserId])
                ->andWhere(['!=', 'u.parent_id', $currentUserId])
                ->andWhere(['u.is_delete' => 0]);
        } else {
            // 直推：直接查 parent_id
            $query = User::find()->alias('u')
                ->where(['u.parent_id' => $currentUserId, 'u.is_delete' => 0]);
            // 包装一层 alias 兼容后续 select
        }

        // 关键词筛选 (用户昵称 或 用户ID)
        if ($this->keyword) {
            $query->andWhere([
                'or',
                ['like', 'u.nickname', $this->keyword],
                ['u.id' => $this->keyword]
            ]);
        }

        // 筛选用户等级
        if (isset($this->level_id) && $this->level_id != -1 && $this->level_id !== '') {
            $query->andWhere(['u.level_id' => $this->level_id]);
        }

        // --- 执行分页查询 ---
        $pagination = null;
        $list = $query
            ->select(['u.id', 'u.avatar_url', 'u.nickname', 'u.created_at', 'u.level_id', 'u.mobile'])
            ->page($pagination, $this->limit, $this->page)
            ->orderBy('u.id DESC')
            ->asArray()
            ->all();

        // --- 格式化输出 (可选，但推荐) ---
        $levels = Level::find()->where(['mall_id' => $this->mall_id, 'is_delete' => 0])->asArray()->all();
        $levelMap = array_column($levels, 'name', 'id');

        foreach ($list as &$item) {
            $item['created_at_text'] = date('Y-m-d H:i', $item['created_at']);
            $item['level_name'] = $levelMap[$item['level_id']] ?? '默认等级';
        }
        unset($item);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'list' => $list,
            'pagination' => $pagination
        ]);
    }
}