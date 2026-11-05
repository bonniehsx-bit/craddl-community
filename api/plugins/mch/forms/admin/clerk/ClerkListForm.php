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


class ClerkListForm extends BaseModel
{
    public $page;
    public $limit;
    public $keyword; // [新增] 接收搜索关键词

    public function rules()
    {
        return [
            [['page', 'limit'], 'integer'],
            [['page'], 'default', 'value' => 1],
            [['limit'], 'default', 'value' => 15],
            [['keyword'], 'string'],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $currentMch = Yii::$app->mch;

        $query = MchClerk::find()->alias('mc')
            ->with('user')
            ->where([
                'mc.mch_id' => $currentMch->id,
                'mc.is_delete' => 0,
                'mc.is_manager' => 0
            ]);

        // [新增] 关键词搜索逻辑
        if ($this->keyword) {
            $query->innerJoinWith('user u')->andWhere([
                'OR',
                ['LIKE', 'u.nickname', $this->keyword],
                ['=', 'u.id', $this->keyword]
            ]);
        }

        $count = $query->count();
        $pagination = new \yii\data\Pagination(['totalCount' => $count, 'pageSize' => $this->limit, 'page' => $this->page - 1]);

        $list = $query->orderBy(['mc.id' => SORT_DESC])
            ->limit($pagination->limit)
            ->offset($pagination->offset)
            ->asArray()
            ->all();

        // [新增] 批量查询核销订单数
        $userIds = array_column($list, 'user_id');
        $verifyCounts = [];
        if (!empty($userIds)) {
            $counts = Order::find()
                ->select('clerk_id, COUNT(id) as count')
                ->where(['mch_id' => $currentMch->id, 'clerk_id' => $userIds, 'pay_status' => Order::PAY_STATUS_PAID]) // 假设订单表有clerk_id记录核销员
                ->groupBy('clerk_id')
                ->asArray()
                ->all();
            $verifyCounts = array_column($counts, 'count', 'clerk_id');
        }

        // 格式化数据
        $formattedList = [];
        foreach ($list as $item) {
            if ($item['user']) {
                $formattedList[] = [
                    'id' => $item['id'],
                    'user_id' => $item['user']['id'],
                    'nickname' => $item['user']['nickname'],
                    'avatar_url' => $item['user']['avatar_url'],
                    'mobile' => $item['user']['mobile'],
                    'status' => $item['status'],
                    'created_time' => $item['created_time'], // [新增]
                    'verify_count' => $verifyCounts[$item['user_id']] ?? 0, // [新增]
                ];
            }
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'list' => $formattedList,
            'pagination' => $pagination
        ]);
    }

}