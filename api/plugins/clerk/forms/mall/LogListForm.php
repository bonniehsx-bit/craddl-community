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

namespace app\plugins\clerk\forms\mall;

use app\core\ApiCode;
use app\models\BaseModel;
use app\plugins\clerk\models\ClerkLog;
use Yii;
use yii\helpers\Json;

/**
 * 平台核销记录查询——读统一台账 ClerkLog(平台自营 mch_id=0)。
 *
 * 阶段3 读侧收口:原直接反查 Order 表,只能看到核心自营自提订单;现读 ClerkLog,
 * 一处覆盖 核心 Order 自提 + 各插件(积分/团购/酒店/…)+ xwy 云仓提货自提。
 * 核销时由平台核销台 PlatformVerifyForm 统一写 ClerkLog(guarded),故此处一读即全。
 */
class LogListForm extends BaseModel
{
    public $keyword;
    public $start_date;
    public $end_date;
    public $page;
    public $limit;

    public function rules()
    {
        return [
            [['keyword', 'start_date', 'end_date'], 'string'],
            [['page'], 'default', 'value' => 1],
            [['limit'], 'default', 'value' => 20],
        ];
    }

    public function search()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $query = ClerkLog::find()->where([
            'mall_id' => Yii::$app->mall->id,
            'mch_id' => 0,   // 平台自营(商家核销走 mch 侧 MchVerifyLog)
            'result' => 1,   // 只列核销成功的
            'is_delete' => 0,
        ]);

        if ($this->keyword) {
            $query->andWhere([
                'or',
                ['like', 'order_no', $this->keyword],
                ['like', 'clerk_name', $this->keyword],
                ['like', 'code', $this->keyword],
            ]);
        }

        if ($this->start_date && $this->end_date) {
            $query->andWhere(['>=', 'verify_at', strtotime($this->start_date)])
                ->andWhere(['<=', 'verify_at', strtotime($this->end_date) + 86399]);
        }

        $pagination = null;
        $list = $query->orderBy(['verify_at' => SORT_DESC])
            ->page($pagination, $this->limit, $this->page)
            ->all();

        $result = [];
        foreach ($list as $log) {
            $products = [];
            if ($log->products_snapshot) {
                try {
                    $products = Json::decode($log->products_snapshot) ?: [];
                } catch (\Exception $e) {
                }
            }
            $result[] = [
                'verify_time_text' => $log->verify_at ? date('Y-m-d H:i:s', $log->verify_at) : '--',
                'clerk_name' => $log->clerk_name ?: '未知',
                'verify_type' => $log->biz_type ?: 'normal',
                'verify_type_name' => $log->biz_type_name ?: '自提订单',
                'order_no' => $log->order_no ?: $log->code,
                'products_snapshot' => $products,
                'remark' => $log->remark ?: '',
            ];
        }

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => '请求成功',
            'data' => [
                'list' => $result,
                'pagination' => $pagination,
            ]
        ];
    }
}
