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
use app\models\BalanceLog;
use app\models\BaseModel;
use app\models\User;
use Yii;

class BalanceOverviewForm extends BaseModel
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

    /**
     * 获取余额页面所需的所有聚合数据
     * @return array
     */
    public function getOverview()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $userId = Yii::$app->user->id;
        $mallId = Yii::$app->mall->id;
        $user = UserHelper::getUser();

        // 1. 获取统计信息 (Stats)
        $totalIncome = (float)BalanceLog::find()->where([
            'user_id' => $userId,
            'is_delete' => 0,
            'type' => 1 // 1=收入
        ])->sum('money');

        $stats = [
            'balance' => number_format($user->money, 2, '.', ''),
            'total_income' => number_format($totalIncome, 2, '.', ''),
        ];

        // 2. 获取相关设置 (Setting)
        // 这里的 'cash_setting' 假设是您在后台配置中存储的键名
        $cashSetting = OptionHelper::get('cash_setting', $mallId);
        $setting = [
            'balance' => $cashSetting['balance'],
            'other' => $cashSetting['other']
        ];
        // 审核版(提审中的版本号)不显示提现 / 转赠按钮,与首页、提现插件的审核版同一个判断
        if (VersionHelper::checkIsAuditMode((int)$mallId)) {
            if (is_array($setting['balance'])) {
                $setting['balance']['is_cash'] = 0;
            }
            if (is_array($setting['other'])) {
                $setting['other']['is_balance_transfer'] = 0;
            }
        }

        // 3. 获取筛选后的明细列表 (List & Pagination)
        $query = BalanceLog::find()->where([
            'user_id' => $userId,
            'is_delete' => 0
        ]);

        // 根据前端Tab进行筛选
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
            ->select(['id', 'title', 'created_time', 'type', 'money', 'current', 'content']) // 按需选择字段
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
     * 【重要】根据日志ID查找关联订单号的示例方法
     * 您需要根据您的实际业务逻辑来实现这个方法
     * @param int $logId
     * @return string
     */
    private function findOrderNo($logId)
    {
        // 这里是一个示例。您需要根据您的数据表结构进行修改。
        // 理想情况下，balance_log 表应该有一个 source_id 和 source_type 字段来关联订单。
        // 如果没有，您可能需要通过其他方式（如备注）来查找。

        // 示例：你的 order 表有一个 balance_log_id 字段
        // $order = Order::findOne(['balance_log_id' => $logId]);
        // return $order ? $order->order_no : 'N/A';

        return 'SN' . date('Ymd') . $logId; // 返回一个模拟的订单号
    }
}