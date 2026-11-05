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

namespace app\plugins\distribution\forms\api;

use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\models\BaseModel;
use app\models\CommissionLog;
use app\models\Level;
use app\plugins\distribution\models\DistributionCommissionType;
use app\models\User;
use app\models\Goods;
use app\models\OrderDetail;
use app\helpers\OptionHelper;
use app\plugins\distribution\models\DistributionUser;

class DistributionCenterForm extends BaseModel
{
    public $page = 1;
    public $limit = 10;
    /** 状态筛选:-1=全部 0=待结算 1=已结算 2=已失效(和代理中心分润明细同一套) */
    public $status = -1;
    /** 按月筛选 YYYY-MM;留空 = 全部 */
    public $month;
    // 旧参数,按起止日期筛,保留兼容
    public $date_start;
    public $date_end;

    public function rules()
    {
        return [
            [['page', 'limit', 'status'], 'integer'],
            [['month', 'date_start', 'date_end'], 'string'],
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

        $userId = (int)\Yii::$app->user->id;

        $summary = $this->getSummaryData($userId);
        $logData = $this->getLogList($userId);

        $data = [
            'summary' => $summary,
            'setting' => $this->getPublicSetting(),
            'list' => $logData['list'],
            'pagination' => $logData['pagination'],
        ];
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '获取成功', $data);
    }

    /**
     * 前端用得到的那几项设置:首页那句「什么时候结算到哪」和提现中心入口进哪个账户。
     * 只给这几项,比例、等级规则这些不往 C 端发。
     */
    private function getPublicSetting(): array
    {
        $s = (array)OptionHelper::get('distribution_setting', \Yii::$app->mall->id, []);
        return [
            'is_enable'         => (int)($s['is_enable'] ?? 0),
            'settlement_timing' => (string)($s['settlement_timing'] ?? 'completed'),
            'settle_delay_days' => (int)($s['settle_delay_days'] ?? 0),
            'payout_target'     => (string)($s['payout_target'] ?? 'price'),
        ];
    }

    /**
     * 获取汇总数据
     * @param int $userId
     * @return array
     */
    private function getSummaryData(int $userId): array
    {
        $mallId = \Yii::$app->mall->id;

        // 当前等级名称：分销等级已并入平台会员等级（User.level_id → Level），没有等级时显示「推广员」
        $levelName = (string)Level::find()->alias('lv')
            ->innerJoin(['u' => User::tableName()], 'u.level_id = lv.id')
            ->where(['u.id' => $userId, 'u.mall_id' => $mallId, 'lv.is_delete' => 0])
            ->select('lv.name')
            ->scalar();
        if ($levelName === '') {
            $levelName = '推广员';
        }

        $user = User::find()->select(['id', 'nickname', 'avatar_url', 'is_inviter'])
            ->where(['id' => $userId, 'mall_id' => $mallId])->asArray()->one();

        // 推广资格:User.is_inviter=1(会员等级驱动或后台授予),且没被单独取消(DistributionUser.is_disabled)
        // 口径同后台分销商列表 UserListForm
        $isDisabled = (int)DistributionUser::find()
            ->where(['mall_id' => $mallId, 'user_id' => $userId, 'is_disabled' => 1])
            ->exists();
        $isDistributor = $user && (int)$user['is_inviter'] === 1 && !$isDisabled;

        $customerCount = (int)User::find()->where([
            'parent_id' => $userId,
            'is_delete' => 0,
            'mall_id' => $mallId,
        ])->count();

        $base = ['user_id' => $userId, 'mall_id' => $mallId, 'type' => DistributionCommissionType::DISTRIBUTION, 'is_delete' => 0];

        $unsettledCommission = (float)CommissionLog::find()
            ->where($base + ['status' => CommissionLog::STATUS_PENDING])
            ->sum('commission_amount');

        $settledCommission = (float)CommissionLog::find()
            ->where($base + ['status' => CommissionLog::STATUS_SETTLED])
            ->sum('commission_amount');

        // 本月:本月产生、没失效的(待结算 + 已结算),和代理中心「本月」同口径。先取当月 1 号再算,避免月末推月滚月
        $monthStart = strtotime(date('Y-m-01 00:00:00'));
        $monthCommission = (float)CommissionLog::find()
            ->where($base)
            ->andWhere(['status' => [CommissionLog::STATUS_PENDING, CommissionLog::STATUS_SETTLED]])
            ->andWhere(['>=', 'created_at', $monthStart])
            ->sum('commission_amount');

        return [
            'customer_count'       => $customerCount,
            'unsettled_commission' => round($unsettledCommission, 2),
            'settled_commission'   => round($settledCommission, 2),
            'month_commission'     => round($monthCommission, 2),
            'level_name'           => $levelName,
            'is_distributor'       => $isDistributor ? 1 : 0,
            'is_disabled'          => $isDisabled ? 1 : 0,
            'nickname'             => $user['nickname'] ?? '',
            'avatar_url'           => $user['avatar_url'] ?? '',
        ];
    }

    /**
     * 获取佣金记录列表
     * @param int $userId
     * @return array
     */
    private function getLogList(int $userId): array
    {
        $mallId = \Yii::$app->mall->id;

        $query = CommissionLog::find()->where([
            'user_id'   => $userId,
            'mall_id'   => $mallId,
            'is_delete' => 0,
            'type'      => DistributionCommissionType::DISTRIBUTION,
        ])->with(['buyUser', 'order']);

        if ((int)$this->status >= 0) {
            $query->andWhere(['status' => (int)$this->status]);
        }

        if ($this->month && preg_match('/^(\d{4})-(\d{2})$/', $this->month, $m)) {
            $start = mktime(0, 0, 0, (int)$m[2], 1, (int)$m[1]);
            $end = mktime(0, 0, 0, (int)$m[2] + 1, 1, (int)$m[1]) - 1;
            $query->andWhere(['between', 'created_at', $start, $end]);
        } elseif ($this->date_start && $this->date_end) {
            $startTime = strtotime($this->date_start . " 00:00:00");
            $endTime = strtotime($this->date_end . " 23:59:59");
            $query->andWhere(['between', 'created_at', $startTime, $endTime]);
        }

        $pagination = null;
        /** @var CommissionLog[] $logs */
        $logs = $query->orderBy(['id' => SORT_DESC])
            ->page($pagination, $this->limit, $this->page)
            ->all();

        // 每笔带上商品名和图(口径同代理中心 OrderForm):按件的记录取那一件,整单的取该订单第一件
        $detailIds = array_filter(array_map(function ($i) { return (int)$i->order_detail_id; }, $logs));
        $orderIds = array_filter(array_map(function ($i) { return (int)$i->order_detail_id ? 0 : (int)$i->order_id; }, $logs));
        $details = [];
        if ($detailIds || $orderIds) {
            $q = OrderDetail::find()->select(array_merge(['id', 'order_id', 'goods_id'], OrderDetail::snapshotSelect()))->where(['mall_id' => $mallId]);
            $q->andWhere(['or', ['id' => array_values($detailIds) ?: [0]], ['order_id' => array_values($orderIds) ?: [0]]]);
            foreach ($q->orderBy(['id' => SORT_ASC])->asArray()->all() as $d) {
                $details['d' . $d['id']] = $d;
                if (!isset($details['o' . $d['order_id']])) {
                    $details['o' . $d['order_id']] = $d;
                }
            }
        }
        $goodsIds = array_values(array_unique(array_map(function ($d) { return (int)$d['goods_id']; }, $details)));
        $goods = $goodsIds ? Goods::find()->select(['id', 'name', 'cover_pic'])->where(['id' => $goodsIds])->indexBy('id')->asArray()->all() : [];

        $list = [];
        foreach ($logs as $log) {
            $d = $details[$log->order_detail_id ? 'd' . $log->order_detail_id : 'o' . $log->order_id] ?? null;
            $g = $d ? ($goods[(int)$d['goods_id']] ?? null) : null;
            $buyer = $log->buyUser;
            // 只给昵称和头像:原先这里还带着买家手机号,页面虽没显示,接口里谁都能拿到
            $list[] = [
                'id'             => (int)$log->id,
                'order_no'       => $log->order ? $log->order->order_no : '',
                'order_amount'   => $log->order ? (float)$log->order->total_price : 0,
                'user_nickname'  => $buyer ? ($buyer->nickname ?: '未知用户') : '未知用户',
                'avatar'         => $buyer ? ($buyer->avatar_url ?: '') : '',
                'is_my_customer' => $buyer && (int)$buyer->parent_id === $userId ? 1 : 0,
                'commission'     => (float)$log->commission_amount,
                'level'          => (int)$log->level,
                'status'         => (int)$log->status,
                'status_text'    => $log->getStatusText(),
                // 下单时的商品(快照),老数据回退商品表
                'goods_name'     => ($d['goods_name'] ?? '') ?: ($g['name'] ?? ''),
                'goods_pic'      => ($d['goods_pic'] ?? '') ?: ($g['cover_pic'] ?? ''),
                'created_at'     => (int)$log->created_at,
            ];
        }

        return [
            'list' => $list,
            'pagination' => $pagination,
        ];
    }
}
