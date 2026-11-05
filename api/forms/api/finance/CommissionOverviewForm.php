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

namespace app\forms\api\finance;

use app\core\ApiCode;
use app\helpers\OptionHelper;
use app\helpers\VersionHelper;
use app\helpers\UserHelper;

use app\models\PriceLog;
use app\models\BaseModel;
use app\models\User;
use Yii;

class CommissionOverviewForm extends BaseModel
{
    public $page = 1;
    public $limit = 15;
    public $log_type = 'income'; // 'income' 或 'expense'

    public function rules()
    {
        return [
            [['page', 'limit'], 'integer'],
            [['log_type'], 'string'],
            [['log_type'], 'in', 'range' => ['all', 'income', 'expense']],
        ];
    }

    public function getOverview()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $userId = Yii::$app->user->id;
        /** @var User $user */
        $user = UserHelper::getUser();

        // 「提现中」的奖励总额 = 待审核 + 待打款 + 打款中
        //
        // 走 WithdrawStatEvent 问、提现插件答 —— 核心不认识插件的表/模型。
        // (原先直查老 Cash 表,新提现单不落那张表,本页恒显示 0)
        $e = new \app\events\WithdrawStatEvent([
            'mallId'    => $this->mall_id,
            'userId'    => $userId,
            'assetType' => 'price',   // 只统计奖励/佣金
        ]);
        Yii::$app->trigger(\app\events\WithdrawStatEvent::NAME, $e);
        $pendingPrice = (float) ($e->pendingAmount ?? 0);

        // 1. 获取统计信息 (Stats) - 直接从 User 模型获取，并加入新字段
        $stats = [
            'price' => number_format($user->price, 2, '.', ''),           // 可提现
            'total_price' => number_format($user->total_price, 2, '.', ''), // 累计
            'cashed' => number_format($user->cashed_price, 2, '.', ''),    // 已提现
            'pending' => number_format($pendingPrice, 2, '.', ''),       // [核心新增] 提现中
        ];

        // 2. 获取筛选后的明细列表 (List & Pagination)
        $query = PriceLog::find()->where([
            'user_id' => $userId,
            'is_delete' => 0
        ]);

        // ⚠ 必须写成三分支。原来是「income 走 type=1，其余一律 type=0」——
        //   那个 else 是把「不是收入」兜底成「支出」，传 all 进来会被当成支出，
        //   只放开上面的 in 校验而不改这里，「全部」会静默变成「只看支出」。
        if ($this->log_type === 'income') {
            $query->andWhere(['type' => 1]);
        } elseif ($this->log_type === 'expense') {
            $query->andWhere(['type' => 0]);
        }
        // log_type = all:不加 type 条件，收支都要

        $pagination = null;
        $list = $query
            ->select(['id', 'title', 'created_at', 'type', 'price', 'current', 'order_no', 'created_time', 'remarks'])
            ->page($pagination, $this->limit, $this->page)
            ->orderBy('id DESC')
            ->asArray()
            ->all();


        $res = OptionHelper::get('cash_setting', $this->mall_id);
        $setting = [
            'price' => $res['price'] ?? null,
            'other' => $res['other'] ?? null
        ];
        // 审核版(提审中的版本号)不显示提现 / 转赠按钮,与首页、提现插件的审核版同一个判断
        if (VersionHelper::checkIsAuditMode((int)$this->mall_id) && is_array($setting['price'])) {
            $setting['price']['is_cash'] = 0;
        }


        // 3. 组装最终返回数据
        $data = [
            'setting' => $setting,
            'stats' => $stats,
            'list' => $list,
            'pagination' => $pagination
        ];

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', $data);
    }
}