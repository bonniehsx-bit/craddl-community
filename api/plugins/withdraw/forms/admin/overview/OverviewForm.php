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

namespace app\plugins\withdraw\forms\admin\overview;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\User;
use app\plugins\withdraw\models\WithdrawOrder;
use yii\db\Expression;
use Yii;

/**
 * 后台 - 提现数据概览
 *
 * 聚合 plugin_withdraw_order，输出 KPI + 趋势 + 状态分布 + 资金来源分布 + 渠道排行 + 最近提现。
 * 注意：created_at 是 INT(unix)，比较一律用整数；按天聚合用 FROM_UNIXTIME。
 */
class OverviewForm extends BaseModel
{
    public $trend_days = 14;

    public function rules()
    {
        return [
            [['trend_days'], 'integer'],
            [['trend_days'], 'default', 'value' => 14],
            [['trend_days'], 'in', 'range' => [7, 14, 30]],
        ];
    }

    public function getOverview()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $mallId = (int)Yii::$app->mall->id;
        $base = ['mall_id' => $mallId, 'is_delete' => 0];
        $S = WithdrawOrder::STATUS_SUCCESS;

        // ===== KPI =====
        $totalAmount  = (float)WithdrawOrder::find()->where($base)->andWhere(['status' => $S])->sum('actual_amount');
        $totalCount   = (int)WithdrawOrder::find()->where($base)->andWhere(['status' => $S])->count();
        $totalFee     = (float)WithdrawOrder::find()->where($base)->andWhere(['status' => $S])->sum('fee');
        $pendingCount = (int)WithdrawOrder::find()->where($base)->andWhere([
            'status' => [
                WithdrawOrder::STATUS_PENDING_AUDIT,
                WithdrawOrder::STATUS_PENDING_PAYOUT,
                WithdrawOrder::STATUS_PAYING,
            ],
        ])->count();

        // 成功率 = 已到账 / 已终结（到账+驳回+退回+失败）
        $terminal = (int)WithdrawOrder::find()->where($base)->andWhere([
            'status' => [
                $S,
                WithdrawOrder::STATUS_REJECTED,
                WithdrawOrder::STATUS_RETURNED,
                WithdrawOrder::STATUS_FAILED,
            ],
        ])->count();
        $successRate = $terminal > 0 ? round($totalCount / $terminal * 100, 1) : 0;

        $monthStart = strtotime(date('Y-m-01 00:00:00'));
        $monthAmount = (float)WithdrawOrder::find()->where($base)->andWhere(['status' => $S])
            ->andWhere(['>=', 'created_at', $monthStart])->sum('actual_amount');

        // ===== 趋势（按天）=====
        $days = (int)$this->trend_days;
        $startDay = strtotime(date('Y-m-d 00:00:00', time() - ($days - 1) * 86400));
        $rawTrend = WithdrawOrder::find()
            ->select([
                new Expression("FROM_UNIXTIME(created_at, '%Y-%m-%d') AS d"),
                new Expression('SUM(amount) AS apply_amount'),
                new Expression("SUM(CASE WHEN status = {$S} THEN actual_amount ELSE 0 END) AS paid_amount"),
            ])
            ->where($base)
            ->andWhere(['>=', 'created_at', $startDay])
            ->groupBy('d')
            ->asArray()->all();
        $trendMap = [];
        foreach ($rawTrend as $r) {
            $trendMap[$r['d']] = $r;
        }
        $labels = $applySeries = $paidSeries = [];
        for ($i = 0; $i < $days; $i++) {
            $ts = time() - ($days - 1 - $i) * 86400;
            $key = date('Y-m-d', $ts);
            $labels[] = date('m-d', $ts);
            $applySeries[] = isset($trendMap[$key]) ? round((float)$trendMap[$key]['apply_amount'], 2) : 0;
            $paidSeries[] = isset($trendMap[$key]) ? round((float)$trendMap[$key]['paid_amount'], 2) : 0;
        }

        // ===== 状态分布 =====
        $statusRaw = WithdrawOrder::find()->select(['status', new Expression('COUNT(*) AS cnt')])
            ->where($base)->groupBy('status')->asArray()->all();
        $statusCnt = [];
        foreach ($statusRaw as $r) {
            $statusCnt[(int)$r['status']] = (int)$r['cnt'];
        }
        $statusDist = [];
        foreach (WithdrawOrder::$statusMap as $st => $name) {
            $statusDist[] = ['status' => (int)$st, 'name' => $name, 'value' => $statusCnt[$st] ?? 0];
        }

        // ===== 资金来源分布 =====
        $assetRaw = WithdrawOrder::find()
            ->select(['asset_type', new Expression('SUM(amount) AS amt'), new Expression('COUNT(*) AS cnt')])
            ->where($base)->groupBy('asset_type')->asArray()->all();
        $assetDist = [];
        foreach ($assetRaw as $r) {
            $a = $r['asset_type'] ?: 'other';
            $assetDist[] = [
                'asset'  => $a,
                'label'  => \app\plugins\withdraw\services\AssetService::label($a),
                'amount' => round((float)$r['amt'], 2),
                'count'  => (int)$r['cnt'],
            ];
        }

        // ===== 渠道排行（Top 8 按金额）=====
        $channelRaw = WithdrawOrder::find()
            ->select(['channel_name', new Expression('SUM(amount) AS amt'), new Expression('COUNT(*) AS cnt')])
            ->where($base)->groupBy('channel_name')
            ->orderBy('amt DESC')->limit(8)->asArray()->all();
        $channelRank = [];
        foreach ($channelRaw as $r) {
            $channelRank[] = [
                'name'   => $r['channel_name'] ?: '未命名渠道',
                'amount' => round((float)$r['amt'], 2),
                'count'  => (int)$r['cnt'],
            ];
        }

        // ===== 最近提现（8 条）=====
        $recentRows = WithdrawOrder::find()->where($base)->orderBy('id DESC')->limit(8)->all();
        $uids = array_unique(array_map(function ($o) { return $o->user_id; }, $recentRows));
        $users = $uids
            ? User::find()->select(['id', 'nickname'])->where(['id' => $uids])->indexBy('id')->asArray()->all()
            : [];
        $recent = array_map(function (WithdrawOrder $o) use ($users) {
            return [
                'order_no'      => $o->order_no,
                'nickname'      => $users[$o->user_id]['nickname'] ?? ('用户' . $o->user_id),
                'channel_name'  => $o->channel_name,
                'asset_label'   => \app\plugins\withdraw\services\AssetService::label($o->asset_type),
                'amount'        => (float)$o->amount,
                'actual_amount' => (float)$o->actual_amount,
                'status'        => (int)$o->status,
                'status_text'   => $o->getStatusText(),
                'created_time'  => $o->created_time,
            ];
        }, $recentRows);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'kpi' => [
                'total_amount' => round($totalAmount, 2),
                'total_count'  => $totalCount,
                'month_amount' => round($monthAmount, 2),
                'pending_count' => $pendingCount,
                'success_rate' => $successRate,
                'total_fee'    => round($totalFee, 2),
            ],
            'trend'        => ['labels' => $labels, 'apply_series' => $applySeries, 'paid_series' => $paidSeries],
            'status_dist'  => $statusDist,
            'asset_dist'   => $assetDist,
            'channel_rank' => $channelRank,
            'recent'       => $recent,
        ]);
    }
}
