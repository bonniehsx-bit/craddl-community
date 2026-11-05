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
use app\models\Order; // [核心] 引入 Order
use app\models\User;
use app\models\UserPath;
use yii\db\Expression;
use Yii;

class TeamListForm extends BaseModel
{
    public $page;
    public $limit = 15;
    public $level = 1;

    public function rules()
    {
        return [
            [['level'], 'default', 'value' => 1],
            [['page', 'limit', 'level'], 'integer'],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $currentUserId = Yii::$app->user->id;

        // --- 步骤 1: 查询出当前页的团队成员 ---
        if ($this->level == 1) {
            // 直推：直接查 parent_id
            $query = User::find()->alias('u2')
                ->where(['u2.parent_id' => $currentUserId, 'u2.is_delete' => 0])
                ->select('u2.id');
        } else {
            // 间推：path 包含当前用户但 parent_id 不是当前用户
            $query = UserPath::find()->alias('up')
                ->innerJoin(['u2' => User::tableName()], 'u2.id = up.user_id')
                ->where(['like', 'up.path', \app\services\UserService::subtreeLike($currentUserId), false])
                ->andWhere(['!=', 'up.user_id', $currentUserId])
                ->andWhere(['!=', 'u2.parent_id', $currentUserId])
                ->andWhere(['u2.is_delete' => 0])
                ->select('up.user_id');
        }

        $teamMembersQuery = $query->page($pagination, $this->limit, $this->page);
        $memberIds = $teamMembersQuery->column();

        if (empty($memberIds)) {
            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', [
                'list' => [],
                'pagination' => $pagination
            ]);
        }

        // --- 步骤 2: 批量查询这些成员的详细信息和统计数据 ---

        // 使用 User 模型作为主查询，一次性获取所有需要的数据
        $usersQuery = User::find()->alias('u')
            ->select([
                'u.id',
                'u.avatar_url',
                'u.nickname',
                'u.created_at',
                'self_order_count' => new Expression('IFNULL(os.total_count, 0)'),
                'self_order_price' => new Expression('IFNULL(os.total_price, 0.00)'),
                'team_member_count' => new Expression('IFNULL(tc.team_count, 0)'),
            ])
            ->where(['u.id' => $memberIds]);

        // a) 左连接子查询：统计成员的“个人有效业绩”
        $orderStatsSubQuery = Order::find()
            ->select([
                'user_id',
                'total_count' => new Expression('COUNT(*)'),
                'total_price' => new Expression('SUM(pay_price - refund_price)') // 精确计算
            ])
            ->where([
                'is_delete' => 0,
                'status' => Order::STATUS_COMPLETED,
                'settlement_status' => Order::SETTLEMENT_STATUS_SETTLED // [核心] 只统计已结算订单
            ])
            ->groupBy('user_id');

        $usersQuery->leftJoin(['os' => $orderStatsSubQuery], 'os.user_id = u.id');

        // b) 左连接子查询：统计成员的”团队总人数”（基于 UserTeamStat 预计算）
        $usersQuery->leftJoin(['tc' => \app\models\UserTeamStat::tableName()], 'tc.user_id = u.id');

        // 按照传入的 memberIds 的顺序排序，确保分页结果顺序正确
        if (count($memberIds) > 0) {
            $usersQuery->orderBy(new Expression('FIELD(u.id, ' . implode(',', $memberIds) . ')'));
        }

        $list = $usersQuery->asArray()->all();

        // --- 步骤 3: 格式化最终结果 ---
        $resultList = array_map(function ($item) {
            $item['created_at'] = Yii::$app->formatter->asDatetime($item['created_at']);
            // 可以在这里增加团队业绩的查询，如果需要的话
            // $item['team_order_price'] = ...
            return $item;
        }, $list);

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '获取成功', [
            'list' => $resultList,
            'pagination' => $pagination
        ]);
    }
}