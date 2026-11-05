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
use app\models\IntegralLog;
use app\models\BaseModel;
use app\models\User;
use Yii;

class IntegralOverviewForm extends BaseModel
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
        $mallId = Yii::$app->mall->id;
        /**
         * @var User $user
         */
        $user = UserHelper::getUser();


        // 1. 获取统计信息 (Stats)
        $totalIntegral = (float)IntegralLog::find()->where([
            'user_id' => $userId,
            'is_delete' => 0,
            'type' => 1 // 1=获得
        ])->sum('integral');

        $stats = [
            'integral' => (string)$user->integral,
            'total_integral' => (string)$totalIntegral,
            'retained_integral' => $user->retained_integral,
            'cashable_integral' => $user->integral > $user->retained_integral ? bcsub($user->integral, $user->retained_integral, 2) : 0.00
        ];
        $res = OptionHelper::get('cash_setting', $this->mall_id);
        $setting = [
            'integral' => $res['integral'] ?? null,
            'other' => $res['other'] ?? null
        ];
        // 审核版(提审中的版本号)不显示提现 / 转赠按钮,与首页、提现插件的审核版同一个判断
        if (VersionHelper::checkIsAuditMode((int)$this->mall_id)) {
            if (is_array($setting['integral'])) {
                $setting['integral']['is_cash'] = 0;
            }
            if (is_array($setting['other'])) {
                $setting['other']['is_integral_transfer'] = 0;
            }
        }

        // 3. 获取筛选后的明细列表 (List & Pagination)
        $query = IntegralLog::find()->where([
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

        $list = $query
            ->select(['id', 'title', 'created_time', 'type', 'integral', 'current', 'content'])
            ->page($pagination, $this->limit, $this->page)
            ->orderBy('id DESC')
            ->asArray()
            ->all();


        // 4. 组装最终返回数据
        $data = [
            'stats' => $stats,
            'setting' => $setting,
            'list' => $list,
            'pagination' => $pagination
        ];

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', $data);
    }

    /**
     * 根据积分日志ID查找关联订单号的示例方法
     * @param int $logId
     * @return string
     */
    private function findOrderNoFromIntegralLog($logId)
    {
        // 【重要】您需要根据您的 integral_log 表结构来实现这里的逻辑
        // 推荐为 integral_log 表也增加 source_id 和 source_type 字段
        return 'SN_INTEGRAL_' . date('Ymd') . $logId; // 返回一个模拟的订单号
    }
}