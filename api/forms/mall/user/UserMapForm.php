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
use yii\data\Pagination; // 引入 Yii 的分页类
use yii\db\Expression;

class UserMapForm extends BaseModel
{
    public $parent_id;
    public $keyword;
    public $page;

    public function rules()
    {
        return [
            [['keyword'], 'string'],
            [['keyword'], 'trim'],
            [['parent_id'], 'default', 'value' => 0],
            [['parent_id'], 'integer'],
            [['page'], 'default', 'value' => 1],
            [['page'], 'integer', 'min' => 1],
        ];
    }

    public function search()
    {
        // 关系图谱要呈现【真实结构】,所以不排除已注销用户。
        // 排除它们会让「A → B(已注销) → C」里的 C 及其整棵子树在树上不可达
        // (C 的 parent_id 指向 B,而 B 不在树里,永远展不开)。
        // 已注销节点由前端渲染成灰色并标注,不脱敏 —— 后台运营需要认出这是谁。
        $query = User::find()->where(['mall_id' => $this->mall_id]);

        if (!empty($this->keyword)) {
            $query->andWhere(['or',
                ['like', 'nickname', $this->keyword],
                ['like', 'mobile', $this->keyword],
                ['=', 'id', $this->keyword]
            ]);
        } else {
            // 在非搜索模式下，总是按 parent_id 查找
            $query->andWhere(['parent_id' => $this->parent_id ?: 0]);
        }

        // [核心改造] 使用 Yii 的分页对象处理所有查询
        $count = $query->count();
        $pagination = new Pagination(['totalCount' => $count, 'page' => $this->page - 1, 'pageSize' => 50]); // 每页加载50条

        $list = $query->alias('u')
            ->select([
                'id',
                'nickname',
                'mobile',
                'avatar_url',
                'created_time',
                // 前端据此把节点渲染成「已注销」/「已拉黑」
                'is_delete',
                'status',
            ])
            ->limit($pagination->limit)
            ->offset($pagination->offset)
            ->orderBy(['id' => SORT_DESC])
            ->asArray()
            ->all();

        // 高效地获取下级数量 (直推) + 团队总数 (全量后代, 走 UserPath 前缀一次批量)
        $userIds = array_column($list, 'id');
        $childrenCounts = [];
        $teamCounts = [];
        if (!empty($userIds)) {
            // 与主查询同口径:直推数含已注销下级。
            // 若这里仍带 is_delete=0,一个「只有注销下级」的节点会被算成 children_count=0 → is_leaf=true,
            // 前端就不给展开箭头,它下面的子树永远点不到。
            $childrenCountsData = User::find()
                ->select('parent_id, COUNT(id) as count')
                ->where(['parent_id' => $userIds])
                ->groupBy('parent_id')
                ->asArray()
                ->all();
            $childrenCounts = array_column($childrenCountsData, 'count', 'parent_id');

            // 团队总数 —— 每人调一次 getTeamCount (内部走 UserPath LIKE 前缀索引, O(1) 每次)
            // 相对于 N+1 普通子查询这里是"N 次前缀检索", 在分页 50 条范围内完全可以接受
            foreach ($userIds as $uid) {
                $teamCounts[$uid] = \app\services\UserService::getTeamCount($uid);
            }
        }

        $list = array_map(function ($item) use ($childrenCounts, $teamCounts) {
            $childrenCount = $childrenCounts[$item['id']] ?? 0;
            // 节点是否为叶子节点（没有下级）
            $item['is_leaf'] = $childrenCount == 0;
            $item['children_count'] = $childrenCount;
            $item['team_count'] = $teamCounts[$item['id']] ?? 0;
            return $item;
        }, $list);

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, 'ok', [
            'list' => $list,
            // [核心改造] 在响应中返回分页信息
            'pagination' => [
                'total_count' => (int)$pagination->totalCount,
                'page_count' => (int)$pagination->getPageCount(),
                'current_page' => (int)$pagination->getPage() + 1,
            ]
        ]);
    }
}