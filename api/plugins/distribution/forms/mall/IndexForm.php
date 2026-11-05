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

namespace app\plugins\distribution\forms\mall;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\CommissionLog;
use app\models\User;
use app\plugins\distribution\models\DistributionCommissionType;
use Yii;

/**
 * 分销数据概览。
 *
 * 口径说明（避免和其它页面对不上数）：
 * · 金额一律只统计 type=distribution 的分润流水，不含其它插件。
 * · 「累计佣金」= 待结算 + 已结算，**不含已失效**（退款追回的那部分不算发出去过）。
 *   已失效单独一格列出，让运营看得见退款造成的冲减规模。
 * · 「分销员总数」按 User.is_inviter=1 统计 —— 与【分销用户】页完全同一口径，
 *   否则两个页面的人数对不上，运营会以为漏人。
 *   注意它是「全局推广员身份」而非本插件的开关：它决定谁**能被绑为上级**，
 *   而「还给不给计佣」是 DistributionUser.is_disabled。两者分属不同层，别混。
 *   (三层关系详见 DistributionListener 头注释。)
 * · 「实际获佣人数」是拿过钱的去重人数，和上面那个不是一回事：
 *   前者是「有资格的人」，后者是「真的分到过的人」，差值才是运营该关心的活跃度。
 */
class IndexForm extends BaseModel
{
    public function rules()
    {
        return [];
    }

    public function getData()
    {
        $mallId = (int) Yii::$app->mall->id;

        $sumBy = function ($status) use ($mallId) {
            $q = CommissionLog::find()->where([
                'mall_id'   => $mallId,
                'type'      => DistributionCommissionType::DISTRIBUTION,
                'is_delete' => 0,
            ]);
            if ($status !== null) {
                $q->andWhere(is_array($status) ? ['status' => $status] : ['status' => $status]);
            }
            return (float) ($q->sum('commission_amount') ?: 0);
        };

        $pending = $sumBy(CommissionLog::STATUS_PENDING);
        $settled = $sumBy(CommissionLog::STATUS_SETTLED);
        $invalid = $sumBy(CommissionLog::STATUS_INVALID);

        // 今日 / 本月新增(按计提时间,不含已失效,与「累计」同口径)。本月从当月 1 号算,别用 strtotime('-1 month') 推
        $sinceSum = function (int $since) use ($mallId) {
            return (float) (CommissionLog::find()
                ->where(['mall_id' => $mallId, 'type' => DistributionCommissionType::DISTRIBUTION, 'is_delete' => 0])
                ->andWhere(['!=', 'status', CommissionLog::STATUS_INVALID])
                ->andWhere(['>=', 'created_at', $since])
                ->sum('commission_amount') ?: 0);
        };
        $today = $sinceSum(strtotime(date('Y-m-d 00:00:00')));
        $month = $sinceSum(strtotime(date('Y-m-01 00:00:00')));

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg'  => '获取成功',
            'data' => [
                'total_commission'   => number_format($pending + $settled, 2, '.', ''),
                'pending_commission' => number_format($pending, 2, '.', ''),
                'settled_commission' => number_format($settled, 2, '.', ''),
                'invalid_commission' => number_format($invalid, 2, '.', ''),
                'today_commission'   => number_format($today, 2, '.', ''),
                'month_commission'   => number_format($month, 2, '.', ''),
                'total_distributors' => $this->countDistributors($mallId),
                'earned_users'       => $this->countEarnedUsers($mallId),
                'total_orders'       => $this->countOrders($mallId),
                'trend'              => $this->getTrend($mallId),
                'level_distribution' => $this->getLevelDistribution($mallId),
                'ranking'            => $this->getRanking($mallId),
            ],
        ];
    }

    /** 有推广员身份的人数（能被绑为上级的人）：与【分销用户】页同口径 */
    private function countDistributors(int $mallId): int
    {
        return (int) User::find()
            ->where(['mall_id' => $mallId, 'is_delete' => 0, 'is_inviter' => 1])
            ->count();
    }

    /** 真正拿到过佣金的去重人数（不含只有失效流水的） */
    private function countEarnedUsers(int $mallId): int
    {
        return (int) CommissionLog::find()
            ->where([
                'mall_id'   => $mallId,
                'type'      => DistributionCommissionType::DISTRIBUTION,
                'is_delete' => 0,
            ])
            ->andWhere(['!=', 'status', CommissionLog::STATUS_INVALID])
            ->count('DISTINCT user_id');
    }

    /** 产生过分销佣金的订单数 */
    private function countOrders(int $mallId): int
    {
        return (int) CommissionLog::find()
            ->where([
                'mall_id'   => $mallId,
                'type'      => DistributionCommissionType::DISTRIBUTION,
                'is_delete' => 0,
            ])
            ->andWhere(['>', 'order_id', 0])
            ->count('DISTINCT order_id');
    }

    /**
     * 近 30 日分润趋势（按计提日期）。
     *
     * ⚠ created_at 是 INT 时间戳，绝不能拿 date('Y-m-d') 字符串去比 ——
     *   MySQL 会把 '2026-08-19' 强转成数字 2026，条件永远成立。
     *   这里统一用整数区间 + FROM_UNIXTIME 分组。
     */
    private function getTrend(int $mallId): array
    {
        $days  = 30;
        $start = strtotime(date('Y-m-d', strtotime("-" . ($days - 1) . " days")));

        $rows = CommissionLog::find()
            ->select([
                'd'   => new \yii\db\Expression("FROM_UNIXTIME(created_at, '%Y-%m-%d')"),
                'amt' => new \yii\db\Expression('SUM(commission_amount)'),
            ])
            ->where([
                'mall_id'   => $mallId,
                'type'      => DistributionCommissionType::DISTRIBUTION,
                'is_delete' => 0,
            ])
            ->andWhere(['!=', 'status', CommissionLog::STATUS_INVALID])
            ->andWhere(['>=', 'created_at', $start])
            ->groupBy('d')
            ->asArray()
            ->all();

        $map = array_column($rows, 'amt', 'd');

        // 补齐没有数据的日子，否则折线图会把空白日直接跳过、趋势失真
        $xAxisData = [];
        $seriesData = [];
        for ($i = 0; $i < $days; $i++) {
            $day = date('Y-m-d', strtotime("-" . ($days - 1 - $i) . " days"));
            $xAxisData[]  = date('m-d', strtotime($day));
            $seriesData[] = round((float) ($map[$day] ?? 0), 2);
        }

        return ['xAxisData' => $xAxisData, 'seriesData' => $seriesData];
    }

    /** 各分销层级分了多少（一级/二级/…） */
    private function getLevelDistribution(int $mallId): array
    {
        $rows = CommissionLog::find()
            ->select([
                'level',
                'amt' => new \yii\db\Expression('SUM(commission_amount)'),
                'cnt' => new \yii\db\Expression('COUNT(*)'),
            ])
            ->where([
                'mall_id'   => $mallId,
                'type'      => DistributionCommissionType::DISTRIBUTION,
                'is_delete' => 0,
            ])
            ->andWhere(['!=', 'status', CommissionLog::STATUS_INVALID])
            ->groupBy('level')
            ->orderBy(['level' => SORT_ASC])
            ->asArray()
            ->all();

        $out = [];
        foreach ($rows as $r) {
            $lv = (int) $r['level'];
            $out[] = [
                'name'  => $lv > 0 ? "{$lv}级分销" : '未标层级',
                'value' => round((float) $r['amt'], 2),
                'count' => (int) $r['cnt'],
            ];
        }
        return $out;
    }

    /** 分销佣金排行 TOP 10 */
    private function getRanking(int $mallId): array
    {
        $rows = CommissionLog::find()
            ->select([
                'user_id',
                'amt' => new \yii\db\Expression('SUM(commission_amount)'),
            ])
            ->where([
                'mall_id'   => $mallId,
                'type'      => DistributionCommissionType::DISTRIBUTION,
                'is_delete' => 0,
            ])
            ->andWhere(['!=', 'status', CommissionLog::STATUS_INVALID])
            ->groupBy('user_id')
            ->orderBy(['amt' => SORT_DESC])
            ->limit(10)
            ->asArray()
            ->all();

        if (empty($rows)) {
            return [];
        }

        $users = User::find()
            ->select(['id', 'nickname', 'avatar_url'])
            ->where(['id' => array_column($rows, 'user_id')])
            ->indexBy('id')
            ->asArray()
            ->all();

        $out = [];
        foreach ($rows as $r) {
            $uid = (int) $r['user_id'];
            $out[] = [
                'user_id'    => $uid,
                // 用户可能已被删除，别让排行榜出现空白行
                'nickname'   => $users[$uid]['nickname'] ?? ('用户#' . $uid),
                'avatar_url' => $users[$uid]['avatar_url'] ?? '',
                'amount'     => number_format((float) $r['amt'], 2, '.', ''),
            ];
        }
        return $out;
    }
}
