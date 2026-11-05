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

namespace app\forms\api\manage;

use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\helpers\UserHelper;
use app\models\BalanceLog;
use app\models\BaseModel;
use app\models\DateBalanceLog;
use app\models\DateIntegralLog;
use app\models\DatePriceLog;
use app\models\IntegralLog;
use app\models\PriceLog;
use app\models\User;
use DateTime;

class DataForm extends BaseModel
{

    public function search()
    {
        $dates = [];
        $today = new DateTime();  // 获取当前日期
        $x_arr = [];
        for ($i = 0; $i < 7; $i++) {
            $dates[] = $today->format('Y-m-d');  // 将日期格式化为 YYYY-MM-DD
            $x_arr[] = $today->format('m-d');  // 将日期格式化为 YYYY-MM-DD
            $today->modify('-1 day');  // 将日期减去一天
        }
        $dates = array_reverse($dates);
        $x_arr = array_reverse($x_arr);
        //1、首先获取收入
        $day_data_add = [];
        $day_total_data = [];
        foreach ($dates as $date) {
            $num = PriceLog::find()->andWhere(['user_id' => UserHelper::getMyId(), 'type' => 1])->andWhere(['date_at' => $date])->sum('price');
            $day_data_add[] =  $num > 0 ? $num : 0;
            $num = DatePriceLog::find()
                ->andWhere(['user_id' => UserHelper::getMyId()])
                ->andWhere(['date_at' => $date])
                ->orderBy('id desc')
                ->select('current')
                ->scalar();
            $day_total_data[] = $num > 0 ? $num : 0;
        }
        $price = ['total' => $day_total_data, 'day' => $day_data_add];
        $day_data_add = [];
        $day_total_data = [];
        foreach ($dates as $date) {
            $num = BalanceLog::find()->andWhere(['user_id' => UserHelper::getMyId(), 'type' => 1])->andWhere(['date_at' => $date])->sum('money');
            $day_data_add[] = $num > 0 ? $num : 0;
            $num = DateBalanceLog::find()
                ->andWhere(['user_id' => UserHelper::getMyId()])
                ->andWhere(['date_at' => $date])
                ->orderBy('id desc')
                ->select('current')
                ->scalar();
            $day_total_data[] = $num > 0 ? $num : 0;
        }
        $balance = ['total' => $day_total_data, 'day' => $day_data_add];
        $day_data_add = [];
        $day_total_data = [];
        foreach ($dates as $date) {
            $num = IntegralLog::find()->andWhere(['user_id' => UserHelper::getMyId(), 'type' => 1])->andWhere(['date_at' => $date])->sum('integral');
            $day_data_add[] = $num > 0 ? $num : 0;
            $num = DateIntegralLog::find()
                ->andWhere(['user_id' => UserHelper::getMyId()])
                ->andWhere(['date_at' => $date])
                ->orderBy('id desc')
                ->select('current')
                ->scalar();
            $day_total_data[] = $num > 0 ? $num : 0;
        }
        $integral = ['total' => $day_total_data, 'day' => $day_data_add];
        $day_data_add = [];
        $day_total_data = [];
        foreach ($dates as $date) {
            $num = User::find()->andWhere(['parent_id' => UserHelper::getMyId(), 'parent_date' => $date])->count();
            $day_data_add[] = $num > 0 ? $num : 0;
            $num = User::find()->andWhere(['parent_id' => UserHelper::getMyId()])->andWhere(['<=', 'parent_date', $date])->count();
            $day_total_data[] = $num > 0 ? $num : 0;
        }
        $fens = ['total' => $day_total_data, 'day' => $day_data_add];
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', [
            'price' => $price,
            'balance' => $balance,
            'integral' => $integral,
            'fens' => $fens,
            'x_arr' => $x_arr
        ]);
    }
}