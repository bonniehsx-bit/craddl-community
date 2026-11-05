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

namespace app\forms\mall\mall;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Goods;
use app\models\Order;
use app\models\OrderDetail;

// [核心] 引入 OrderDetail
use app\models\OrderRefund;
use app\models\User;
use yii\db\Expression;
use Yii;

class MallDataForm extends BaseModel
{
    public $select_date; // 1=今日, 2=本月, 3=本年
    public $search_date; // 日期范围数组

    public function rules()
    {
        return [
            [['select_date'], 'integer'],
            [['search_date'], 'safe'],
        ];
    }

    public function search()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // --- 步骤 1: 准备时间范围 ---
        list($startTime, $endTime) = $this->getTimeRange();

        // --- 步骤 2: [核心优化] 一次性查询所有核心看板数据 ---
        $dashboardStats = Order::find()
            ->select([
                // 待办事项
                'send_order_count' => new Expression('COUNT(CASE WHEN status = ' . Order::STATUS_TO_BE_SHIPPED . ' THEN id END)'),

                // [已重构] 成交数据 (只统计已结算的)
                'total_complete_price' => new Expression('SUM(CASE WHEN status = ' . Order::STATUS_COMPLETED . ' AND settlement_status = ' . Order::SETTLEMENT_STATUS_SETTLED . ' THEN (pay_price - refund_price) ELSE 0 END)'),
                'select_order_count' => new Expression('COUNT(CASE WHEN status = ' . Order::STATUS_COMPLETED . ' AND settlement_status = ' . Order::SETTLEMENT_STATUS_SETTLED . ' THEN id END)'),
                'buyer_count' => new Expression('COUNT(DISTINCT CASE WHEN status = ' . Order::STATUS_COMPLETED . ' AND settlement_status = ' . Order::SETTLEMENT_STATUS_SETTLED . ' THEN user_id END)'),

                // [已重构] 付款数据
                'paid_order_count' => new Expression('COUNT(CASE WHEN pay_status = 1 THEN id END)'),
                'customer_count' => new Expression('COUNT(DISTINCT CASE WHEN pay_status = 1 THEN user_id END)'),
            ])
            ->where(['mall_id' => $this->mall_id, 'is_delete' => 0])
            ->andFilterWhere(['between', 'created_at', $startTime, $endTime])
            ->asArray()
            ->one();

        // --- 步骤 3: 查询其他非时间筛选的统计数据 ---
        $info['refund_order'] = OrderRefund::find()->where(['mall_id' => $this->mall_id, 'is_delete' => 0, 'status' => OrderRefund::STATUS_PENDING])->count();
        $info['user_count'] = User::find()->where(['mall_id' => $this->mall_id, 'is_delete' => 0])->count();
        $info['goods_count'] = Goods::find()->where(['mall_id' => $this->mall_id, 'is_delete' => 0])->count();

        // --- 步骤 4: 组装数据并计算衍生指标 ---
        $info['send_order'] = (int)($dashboardStats['send_order_count'] ?? 0);
        $info['total_complete_price'] = sprintf('%.2f', $dashboardStats['total_complete_price'] ?? 0.00);
        $info['select_order'] = (int)($dashboardStats['select_order_count'] ?? 0);
        $info['buyer'] = (int)($dashboardStats['buyer_count'] ?? 0);
        $info['order'] = (int)($dashboardStats['paid_order_count'] ?? 0); // 总付款订单数
        $info['customer'] = (int)($dashboardStats['customer_count'] ?? 0); // 总付款人数

        // 计算客单价
        $info['single_price'] = ($info['buyer'] > 0) ? sprintf('%.2f', $info['total_complete_price'] / $info['buyer']) : '0.00';

        // [已重构] 统计已结算订单的商品销量
        $settledOrderQuery = Order::find()->select('id')->where([
            'mall_id' => $this->mall_id, 'is_delete' => 0,
            'status' => Order::STATUS_COMPLETED,
            'settlement_status' => Order::SETTLEMENT_STATUS_SETTLED
        ])->andFilterWhere(['between', 'created_at', $startTime, $endTime]);

        $info['sales'] = (int)OrderDetail::find()->where(['in', 'order_id', $settledOrderQuery])
            ->sum('num') ?? 0;

        // 新增用户统计
        $info['new_user'] = (int)User::find()->where(['mall_id' => $this->mall_id, 'is_delete' => 0])
            ->andFilterWhere(['between', 'created_at', $startTime, $endTime])
            ->count();

        // --- 步骤 5: 查询排行榜数据 ---
        $goods_list = $this->getTopGoods();
        $user_list = $this->getTopUsers();

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['info' => $info, 'goods_list' => $goods_list, 'user_list' => $user_list]);
    }

    /**
     * 辅助方法：获取时间范围
     * @return array [startTime, endTime]
     */
    private function getTimeRange(): array
    {
        if ($this->search_date && count($this->search_date) == 2) {
            return [
                strtotime($this->search_date[0]),
                strtotime($this->search_date[1] . ' 23:59:59')
            ];
        }

        switch ($this->select_date) {
            case 1: // 今日
                return [strtotime(date('Y-m-d')), time()];
            case 2: // 本月
                return [strtotime(date('Y-m-01')), time()];
            case 3: // 本年
                return [strtotime(date('Y-01-01')), time()];
            default:
                return [null, null]; // 不筛选时间
        }
    }

    /**
     * 辅助方法：获取商品销量 TOP 10
     * @return array
     */
    private function getTopGoods(): array
    {
        return Goods::find()
            ->where(['is_delete' => 0, 'mall_id' => $this->mall_id])
            ->andWhere(['>', 'sales', 0]) // sales 字段现在由 OrderPaidJob 维护
            ->orderBy('sales DESC')
            ->limit(10)
            ->select('name, cover_pic, sales')
            ->asArray()
            ->all();
    }

    /**
     * 辅助方法：获取用户消费 TOP 10 (基于已结算订单)
     *
     * 【为什么拆成两步查询】原实现是 order INNER JOIN user 再 GROUP BY + ORDER BY 聚合值。
     * MySQL 会挑 user 做驱动表并走 type=ALL —— 用户涨到几十万后,每打开一次数据栏就要
     * 全扫一遍用户表,再 Using temporary + filesort,这正是本页变慢的主因(与订单量无关,
     * 所以订单不多的商城一样卡)。
     * 改法:第一步只在 order 表内聚合出消费最高的 10 个 user_id,全程不碰 user 表;
     * 第二步按主键把这 10 个人的昵称头像取回来。实测驱动表回到 order(type=ref),
     * 第二步是 10 行 PRIMARY 查找。
     * 行为保持不变:已注销用户不进榜(等价于原来的 INNER JOIN u.is_delete = 0)。
     */
    private function getTopUsers(): array
    {
        $top = Order::find()
            ->select([
                'user_id',
                'pay_price' => new Expression('SUM(pay_price - refund_price)'),
            ])
            ->where([
                'is_delete' => 0,
                'mall_id' => $this->mall_id,
                'status' => Order::STATUS_COMPLETED,
                'settlement_status' => Order::SETTLEMENT_STATUS_SETTLED,
            ])
            ->groupBy('user_id')
            // 金额并列时用 user_id 兜底,保证排名稳定 —— 否则 SQL 对并列不保证顺序,
            // 同样的数据每次刷新榜单可能互换位置(实测本库有 4 人并列 5000.00)。
            ->orderBy(['pay_price' => SORT_DESC, 'user_id' => SORT_ASC])
            ->limit(10)
            ->asArray()
            ->all();

        if (!$top) {
            return [];
        }

        $users = User::find()
            ->select(['id', 'nickname', 'avatar_url'])
            ->where(['id' => array_column($top, 'user_id'), 'is_delete' => 0])
            ->indexBy('id')
            ->asArray()
            ->all();

        $list = [];
        foreach ($top as $row) {
            $uid = (int)$row['user_id'];
            if (!isset($users[$uid])) {
                continue;
            }
            $list[] = [
                'pay_price'  => $row['pay_price'],
                'nickname'   => $users[$uid]['nickname'],
                'avatar_url' => $users[$uid]['avatar_url'],
            ];
        }
        return $list;
    }
}
