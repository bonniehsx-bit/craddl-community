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

namespace app\plugins\mch\forms\admin\clerk;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Order;
use app\plugins\mch\models\MchClerk;
use Yii;

class ClerkRecordForm extends BaseModel
{
    public $page;
    public $limit;
    public $clerk_id; // 核销员的 user_id
    public $month;    // 筛选的月份，格式 'YYYY-MM'

    public function rules()
    {
        return [
            [['page', 'limit', 'clerk_id'], 'integer'],
            [['page'], 'default', 'value' => 1],
            [['limit'], 'default', 'value' => 15],
            [['month'], 'string'],
        ];
    }

    /**
     * 获取当前商家的所有核销员 (用于前端筛选器)
     * @return array
     */
    public function getAllClerks()
    {
        $currentMch = Yii::$app->mch;

        $list = MchClerk::find()->alias('mc')
            ->with('user')
            ->where([
                'mc.mch_id' => $currentMch->id,
                'mc.is_delete' => 0,
                'mc.status' => 1
            ])
            ->asArray()
            ->all();

        $formattedList = [];
        foreach ($list as $item) {
            if ($item['user']) {
                $formattedList[] = [
                    'id' => $item['user_id'], // 返回 user_id
                    'name' => $item['user']['nickname'] // 返回昵称
                ];
            }
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['list' => $formattedList]);
    }

    /**
     * 获取核销订单记录列表
     * @return array
     */
    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $currentMch = Yii::$app->mch;

        $query = \app\plugins\mch\models\MchVerifyLog::find()->alias('v')
            ->where([
                'v.mch_id' => $currentMch->id,
                'v.is_delete' => 0,
            ]);

        // 按核销员筛选
        if ($this->clerk_id) {
            $query->andWhere(['v.clerk_id' => $this->clerk_id]);
        }

        // 按月份筛选
        if ($this->month && preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $startTime = strtotime($this->month . '-01 00:00:00');
            $endTime = strtotime(date('Y-m-t 23:59:59', $startTime));
            $query->andWhere(['between', 'v.verify_at', $startTime, $endTime]);
        }

        $count = $query->count();
        $pagination = new \yii\data\Pagination(['totalCount' => $count, 'pageSize' => $this->limit, 'page' => $this->page - 1]);

        $list = $query->select('order_no, verify_at as verify_time, clerk_name, verify_type, verify_type_name, remark, products_snapshot')
            ->orderBy(['v.verify_at' => SORT_DESC])
            ->limit($pagination->limit)
            ->offset($pagination->offset)
            ->asArray()
            ->all();

        foreach ($list as &$item) {
            $item['verify_time'] = date('Y-m-d H:i:s', $item['verify_time']);
            // 解析快照JSON以便前端富文本展示
            if (!empty($item['products_snapshot'])) {
                try {
                    $item['products_snapshot'] = \yii\helpers\Json::decode($item['products_snapshot']);
                } catch (\Exception $e) {
                    $item['products_snapshot'] = [];
                }
            } else {
                $item['products_snapshot'] = [];
            }
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'list' => $list,
            'pagination' => $pagination
        ]);
    }
}