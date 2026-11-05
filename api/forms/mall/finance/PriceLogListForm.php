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

namespace app\forms\mall\finance;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Order;

// [核心] 引入 Order
use app\models\PriceLog;
use app\models\User;
use Yii;

class PriceLogListForm extends BaseModel
{
    public $page;
    public $limit = 10;
    public $keyword;
    public $user_id;
    public $nickname;
    public $is_export;
    public $search_date;
    public $status;
    public $type;

    public function rules()
    {
        return [
            [['search_date'], 'safe'],
            [['page', 'limit', 'is_export', 'user_id', 'status', 'type'], 'integer'],
            [['keyword', 'nickname'], 'string'],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // --- [核心重构] 使用 LEFT JOIN 一次性获取所有信息 ---
        $query = PriceLog::find()->alias('l')
            ->select([
                'l.*',
                'u.nickname',
                'u.avatar_url',
                'buyer.id as buyer_id',
                'buyer.nickname as buyer_name'
            ])
            ->leftJoin(['u' => User::tableName()], 'u.id = l.user_id')
            // [核心] 通过订单号关联 Order 表，再通过 Order.user_id 关联出购买者
            ->leftJoin(['o' => Order::tableName()], 'o.order_no = l.order_no')
            ->leftJoin(['buyer' => User::tableName()], 'buyer.id = o.user_id')
            ->where([
                'l.is_delete' => 0,
                'l.mall_id' => Yii::$app->mall->id,
            ])
            // 确保关联的用户也未被删除
            ->andWhere(['u.is_delete' => 0]);

        // --- 处理筛选条件 ---
        if (isset($this->type) && in_array($this->type, [0, 1])) {
            $query->andWhere(['l.type' => $this->type]);
        }
        if ($this->nickname) {
            $query->andWhere(['like', 'u.nickname', $this->nickname]);
        }
        if ($this->user_id) {
            $query->andWhere(['l.user_id' => $this->user_id]);
        }
        if ($this->keyword) {
            $query->andWhere(['like', 'l.remarks', $this->keyword]);
        }
        if ($this->search_date && is_array($this->search_date) && count($this->search_date) == 2) {
            $start_at = strtotime($this->search_date[0]);
            $end_at = strtotime($this->search_date[1] . ' 23:59:59');
            $query->andWhere(['between', 'l.created_at', $start_at, $end_at]);
        }

        $list = $query->page($pagination, $this->limit, $this->page)
            ->orderBy('l.id DESC')
            ->asArray()
            ->all();

        // 现在不再需要在循环里查询数据库了
        foreach ($list as &$item) {
            $item['created_at'] = Yii::$app->formatter->asDatetime($item['created_at']);
        }
        unset($item);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['list' => $list, 'pagination' => $pagination]);
    }

    public function asyncExport()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $token = 'PL_EXP_' . Yii::$app->mall->id . '_' . Yii::$app->user->id . '_' . time();

        \app\helpers\JobHelper::push(new \app\exports\finance\PriceLogExportJob([
            'params' => $this->attributes,
            'token' => $token,
            'mall_id' => Yii::$app->mall->id
        ]));

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => '导出任务已提交',
            'data' => [
                'token' => $token
            ]
        ];
    }
}