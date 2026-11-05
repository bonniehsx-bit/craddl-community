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
use app\models\Order;
use app\models\User;
use yii\db\Query;

class CustomerForm extends BaseModel
{
    public $page = 1;
    public $limit = 10;
    public $keyword;
    public $date_range;

    public function rules()
    {
        return [
            [['page', 'limit'], 'integer'],
            [['page'], 'default', 'value' => 1],
            [['limit'], 'default', 'value' => 10],
            [['keyword'], 'string'],
            [['date_range'], 'safe'],
        ];
    }

    /**
     * 主方法，聚合所有数据
     * @return array
     */
    public function get()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }


        $userId = \Yii::$app->user->id;

        $summary = $this->getSummaryData($userId);
        $inviter = $this->getInviterInfo();
        $listData = $this->getCustomerList($userId);

        $data = [
            'summary' => $summary,
            'inviter' => $inviter,
            'list' => $listData['list'],
            'pagination' => $listData['pagination'],
        ];

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '获取成功', $data);
    }

    /**
     * 获取汇总数据
     * @param int $userId
     * @return array
     */
    private function getSummaryData(int $userId): array
    {
        // 1. 注册用户数
        $registeredUsers = (int)User::find()->where([
            'parent_id' => $userId,
            'is_delete' => 0,
            'mall_id' => $this->mall_id
        ])->count();

        // 2. 下单用户数 (只统计已支付的)
        $orderingUsers = (int)User::find()
            ->alias('u')
            ->where(['u.parent_id' => $userId, 'u.is_delete' => 0, 'u.mall_id' => $this->mall_id])
            ->innerJoin(Order::tableName() . ' o', 'o.user_id = u.id')
            ->andWhere(['o.pay_status' => Order::PAY_STATUS_PAID])
            ->andWhere(['!=', 'o.status', Order::STATUS_CLOSED])
            ->distinct('u.id')
            ->count();

        return [
            'registered_users' => $registeredUsers,
            'ordering_users' => $orderingUsers,
        ];
    }

    /**
     * 获取邀请人信息
     * @return array|null
     */
    private function getInviterInfo(): ?array
    {
        $user = \Yii::$app->user->identity;
        $inviter = null;

        if ($user && $user->parent_id) {
            $parent = User::findOne([
                'id' => $user->parent_id,
                'is_delete' => 0,
                'mall_id' => $this->mall_id
            ]);
            if ($parent) {
                $inviter = [
                    'id' => $parent->id,
                    'name' => $parent->nickname,
                    'avatar' => $parent->avatar_url,
                ];
            }
        }
        return $inviter;
    }

    /**
     * 获取客户列表
     * @param int $userId
     * @return array
     */
    private function getCustomerList(int $userId): array
    {
        $startTime = null;
        $endTime = null;
        if ($this->date_range && is_string($this->date_range)) {
            $this->date_range = json_decode($this->date_range, true);
        }
        if ($this->date_range && is_array($this->date_range) && count($this->date_range) == 2) {
            if (!empty($this->date_range[0]) && !empty($this->date_range[1])) {
                $startTime = strtotime($this->date_range[0] . ' 00:00:00');
                $endTime = strtotime($this->date_range[1] . ' 23:59:59');
            }
        }

        $isPaid = Order::PAY_STATUS_PAID;
        $orderCountSubQuery = (new Query())
            ->select('COUNT(*)')
            ->from(Order::tableName())
            ->where("user_id = u.id AND pay_status={$isPaid} AND status!=0 AND is_delete=0");

        if ($startTime && $endTime) {
            $orderCountSubQuery->andWhere(['between', 'pay_at', $startTime, $endTime]);
        }

        // ======================= [核心新增] 开始 =======================
        // 团队人数子查询
        $teamCountSubQuery = (new Query())
            ->select('COUNT(*)')
            ->from(User::tableName())
            ->where('parent_id = u.id AND is_delete = 0');

        // 消费总额子查询
        $totalConsumptionSubQuery = (new Query())
            ->select('SUM(pay_price)')
            ->from(Order::tableName())
            ->where("user_id = u.id AND pay_status = {$isPaid} AND status!=0 AND is_delete = 0");
        // ======================= [核心新增] 结束 =======================


        // --- 主查询 ---
        $query = User::find()->alias('u')->where([
            'u.parent_id' => $userId,
            'u.is_delete' => 0,
            'u.mall_id' => $this->mall_id,
        ]);

        if ($this->keyword) {
            $query->andWhere(['OR',
                ['like', 'u.nickname', $this->keyword],
                ['u.id' => $this->keyword],
                ['u.mobile' => $this->keyword]
            ]);
        }

        $list = $query->select([
            'u.id',
            'u.nickname as name',
            'u.avatar_url as avatar',
            'u.mobile',
            'FROM_UNIXTIME(u.created_at, \'%Y-%m-%d %H:%i\') as registration_time',
            'order_count' => $orderCountSubQuery,
            'team_count' => $teamCountSubQuery,
            'total_consumption' => $totalConsumptionSubQuery,
        ])
            ->page($pagination, $this->limit, $this->page)
            ->orderBy(['u.id' => SORT_DESC])
            ->asArray()
            ->all();

        // --- 格式化数据 ---
        foreach ($list as &$item) {
            $item['order_count'] = (int)$item['order_count'];
            $item['team_count'] = (int)($item['team_count'] ?? 0);
            $item['total_consumption'] = number_format((float)($item['total_consumption'] ?? 0), 2, '.', '');
        }
        unset($item);

        return [
            'list' => $list,
            'pagination' => $this->getPaginationInfo($pagination),
        ];
    }
}