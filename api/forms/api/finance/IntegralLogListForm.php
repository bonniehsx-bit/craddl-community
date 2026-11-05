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
use app\models\BaseModel;
use app\models\IntegralLog;
use Yii; // 引入Yii

class IntegralLogListForm extends BaseModel
{
    // [优化] 将 status 改为 type，与新UI的参数名保持一致
    public $type = 'all'; // 可选值: 'all', 'in', 'out'
    public $page;
    public $limit = 20;

    public function rules()
    {
        return [
            [['type'], 'in', 'range' => ['all', 'in', 'out']],
            [['page', 'limit'], 'integer'],
            [['page'], 'default', 'value' => 1],
            [['limit'], 'default', 'value' => 20],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $query = IntegralLog::find()->where([
            'user_id' => Yii::$app->user->id,
            'is_delete' => 0
        ]);

        // [优化] 根据新的 type 参数进行筛选
        if ($this->type === 'in') {
            $query->andWhere(['type' => 1]); // 1 = 收入
        } elseif ($this->type === 'out') {
            $query->andWhere(['type' => 0]); // 0 = 支出
        }
        // 如果 type 是 'all'，则不添加额外的 type 条件

        $list = $query
            ->page($pagination, $this->limit, $this->page)
            ->orderBy('id DESC')
            ->asArray()
            ->all();

        // --- [核心优化] 格式化数据以匹配新UI ---
        $formattedList = [];
        foreach ($list as $item) {
            // 根据 type 决定 amount 的正负
            $amount = ($item['type'] == 1) ? (float)$item['integral'] : -(float)$item['integral'];

            $formattedList[] = [
                'id' => (int)$item['id'],
                // 如果 content 为空，则使用 title 作为描述
                'description' => $item['content'] ?: $item['title'],
                'amount' => $amount,
                'title' => $item['title'],
                // 将 current 字段映射为 balance_after
                'balance_after' => (float)$item['current'],
                // 返回格式化的 created_time 字段
                'created_at' => $item['created_time'],
            ];
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'list' => $formattedList,
            'pagination' => $pagination
        ]);
    }
}