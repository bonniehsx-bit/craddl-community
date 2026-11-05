<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-11-02
 * Time: 13:30
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
use app\helpers\JobHelper;
use app\models\BalanceLog;
use app\models\BaseModel;
use app\models\PriceLog;
use app\models\User;
use Yii;

class BalanceLogListForm extends BaseModel
{
    public $page;
    public $limit;
    public $keyword;
    public $user_id;
    public $nickname;
    public $is_export;
    public $search_date;
    public $type; // [新增] 收支类型字段

    public function rules()
    {
        return [
            [['search_date'], 'safe'],
            [['page', 'limit', 'is_export', 'user_id', 'type'], 'integer'], // [修改] type 加入 integer 验证
            [['keyword', 'nickname'], 'string'],
            [['limit'], 'default', 'value' => 10]
        ];
    }


    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $query = BalanceLog::find()
            ->alias('l')
            ->leftJoin(['u' => User::tableName()], 'u.id=l.user_id')
            // ⚠ 商城隔离:漏了这一条,本商城后台会列出全平台所有商城的流水(串号)。
            // 下面 asyncExport 把 mall_id 传给了导出 Job,唯独列表自己没过滤,
            // 于是导出对得上、页面对不上。
            ->andWhere(['l.mall_id' => \Yii::$app->mall->id])
            ->andWhere(['l.is_delete' => 0, 'u.is_delete' => 0]);

        if ($this->nickname) {
            $query->andWhere(['like', 'u.nickname', $this->nickname]);
        }
        if ($this->user_id) {
            $query->andWhere(['l.user_id' => $this->user_id]);
        }
        if ($this->keyword) {
            $query->andWhere(['like', 'l.content', $this->keyword]);
        }

        // [新增] 处理收支类型的筛选逻辑
        // 注意：这里必须判断是否设置，因为 0 也是有效值
        if (isset($this->type) && $this->type !== '' && $this->type !== null) {
            $query->andWhere(['l.type' => $this->type]);
        }

        if ($this->search_date) {
            $start_at = strtotime($this->search_date[0]);
            $end_at = strtotime($this->search_date[1]);
            $query->andWhere(['>=', 'l.created_at', $start_at]);
            $query->andWhere(['<=', 'l.created_at', $end_at]);
        }

        if (!$this->is_export) {
            $query->page($pagination, $this->limit, $this->page);
        }

        $list = $query
            ->select('u.nickname,u.avatar_url,l.*')
            ->orderBy('l.id DESC')
            ->asArray()->all();
        foreach ($list as &$item) {
            $item['created_at'] = date('Y-m-d H:i:s', $item['created_at']);
        }
        unset($item);

        // 如果是导出模式，这里不返回分页对象，避免报错
        $paginationData = isset($pagination) ? $pagination : null;

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['list' => $list, 'pagination' => $paginationData]);
    }

    /**
     * [新增] 异步导出触发方法
     */
    public function asyncExport()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // 生成唯一 Token
        $token = 'BAL_EXP_' . Yii::$app->mall->id . '_' . Yii::$app->user->id . '_' . time();

        // 推送任务
        JobHelper::push(new \app\exports\finance\BalanceLogExportJob([
            'params' => $this->attributes, // 将搜索条件传给 Job
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
