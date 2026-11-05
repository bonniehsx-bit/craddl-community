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

namespace app\forms\mall\split;

use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\models\BaseModel;
use app\models\SplitOnboardingLog;

class SplitOnboardingLogForm extends BaseModel
{
    public $page;
    public $limit;

    public $user_id;
    public $status;
    public $out_mer_code;
    public $search_date;

    public function rules()
    {
        return [
            [['page', 'limit', 'user_id'], 'integer'],
            [['status', 'out_mer_code'], 'string'],
            [['search_date'], 'safe'],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $query = SplitOnboardingLog::find()->where([
            'mall_id' => \Yii::$app->mall->id,
            'is_delete' => 0
        ]);


        // 根据用户ID搜索
        if ($this->user_id) {
            $query->andWhere(['user_id' => $this->user_id]);
        }

        // 根据外部商户号搜索
        if ($this->out_mer_code) {
            $query->andWhere(['like', 'out_mer_code', $this->out_mer_code]);
        }

        // 根据状态搜索
        if ($this->status) {
            $query->andWhere(['status' => $this->status]);
        }

        // 根据申请时间范围搜索
        if ($this->search_date && is_array($this->search_date) && count($this->search_date) == 2) {
            $startTime = strtotime($this->search_date[0]);
            $endTime = strtotime($this->search_date[1] . ' 23:59:59');
            $query->andWhere(['>=', 'created_at', $startTime])
                ->andWhere(['<=', 'created_at', $endTime]);
        }

        // 关联查询商户和用户信息
        $list = $query->with(['user']) // 假设 Mch 模型有关联 store
        ->orderBy('id DESC')
            ->page($pagination, $this->limit, $this->page)
            ->asArray() // 使用 asArray 提高性能
            ->all();

        // 格式化时间戳
        foreach ($list as &$item) {
            $item['created_time_str'] = $item['created_time'];
        }
        unset($item);

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', [
            'list' => $list,
            'pagination' => $pagination
        ]);
    }
}