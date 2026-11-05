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
use app\helpers\JobHelper;
use app\models\BaseModel;
use app\models\CommissionLog;
use app\models\User;
use app\exports\finance\CommissionLogExportJob; // [核心] 引入Job类
use Yii;

class CommissionLogListForm extends BaseModel
{
    public $page;
    public $limit = 20;

    // --- 筛选字段 ---
    public $keyword;      // 订单号
    public $user_keyword; // 用户昵称/ID
    public $type;
    public $status;
    public $date_range;

    public function rules()
    {
        return [
            [['page', 'limit', 'status'], 'integer'],
            [['keyword', 'user_keyword', 'type'], 'string'],
            [['date_range'], 'safe'],
            [['page'], 'default', 'value' => 1],
        ];
    }

    /**
     * 获取列表数据 (用于页面展示)
     */
    public function search()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $query = CommissionLog::find()->alias('cl')
            ->where(['cl.mall_id' => Yii::$app->mall->id])
            ->with(['user', 'buyUser']); // 列表展示预加载关联

        $query->joinWith(['order o']);

        // --- 筛选逻辑 (与 Job 中复用的逻辑一致) ---
        if ($this->keyword) {
            $query->andWhere(['like', 'o.order_no', trim($this->keyword)]);
        }

        if ($this->user_keyword) {
            $query->leftJoin(['u' => User::tableName()], 'u.id = cl.user_id');
            $query->andWhere([
                'or',
                ['=', 'cl.user_id', $this->user_keyword],
                ['like', 'u.nickname', $this->user_keyword]
            ]);
        }

        if ($this->type) {
            $query->andWhere(['cl.type' => $this->type]);
        }

        if ($this->status !== null && $this->status !== '' && $this->status !== 'all') {
            $query->andWhere(['cl.status' => $this->status]);
        }

        if ($this->date_range && is_array($this->date_range) && count($this->date_range) == 2) {
            $startTime = strtotime($this->date_range[0] . ' 00:00:00');
            $endTime = strtotime($this->date_range[1] . ' 23:59:59');
            $query->andWhere(['between', 'cl.created_at', $startTime, $endTime]);
        }

        $query->orderBy('cl.id DESC');

        // 执行分页查询
        $list = $query->page($pagination, $this->limit, $this->page)->asArray()->all();

        // 获取模型映射（启用 DB 自动发现 —— 新类型写入后自动出现在下拉）
        $mallId = Yii::$app->mall->id;
        $typeMeta = \app\services\CommissionTypeRegistry::getAll($mallId);
        $statusMap = CommissionLog::getStatusMap();

        // --- 数据格式化 ---
        foreach ($list as &$item) {
            $item['order_no'] = $item['order']['order_no'] ?? '';
            $meta = $typeMeta[$item['type']] ?? ['label' => $item['type']];
            $item['type_text'] = $meta['label'] ?: $item['type'];

            $item['status_text'] = $statusMap[$item['status']] ?? '未知状态';

            $item['created_at_text'] = date('Y-m-d H:i:s', $item['created_at']);
            $item['settled_at_text'] = $item['settled_at'] > 0 ? date('Y-m-d H:i:s', $item['settled_at']) : '---';

            // 用户信息
            $item['user_nickname'] = $item['user']['nickname'] ?? '未知用户';
            $item['user_avatar'] = $item['user']['avatar_url'] ?? '';
        }
        unset($item);

        // 富元数据类型选项：label/value/tag_type/rate_label/rate_desc_mode/group
        $typeOptions = [];
        foreach ($typeMeta as $type => $meta) {
            $typeOptions[] = [
                'value'          => $type,
                'label'          => $meta['label'] ?: $type,
                'tag_type'       => $meta['tag_type'] ?? '',
                'rate_label'     => $meta['rate_label'] ?? '佣金比例',
                'rate_desc_mode' => $meta['rate_desc_mode'] ?? 'commission',
                'group'          => $meta['group'] ?? 'custom',
            ];
        }

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => '获取成功',
            'data' => [
                'list' => $list,
                'pagination' => $pagination,
                'type_options' => $typeOptions,
                'status_options' => $this->formatOptions($statusMap),
            ]
        ];
    }

    /**
     * [新增] 异步导出触发方法
     * 该方法不直接生成 Excel，而是推送任务到队列
     */
    public function asyncExport()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // 1. 生成唯一 Token (前缀+商城ID+用户ID+时间戳)
        $token = 'COMM_EXP_' . Yii::$app->mall->id . '_' . Yii::$app->user->id . '_' . time();

        // 2. 组装任务数据
        $jobData = [
            'params' => $this->attributes, // 将当前搜索条件传给 Job
            'token' => $token,
            'mall_id' => Yii::$app->mall->id
        ];

        // 3. 推送任务到队列
        JobHelper::push(new CommissionLogExportJob($jobData));

        // 4. 立即返回 Token 给前端
        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => '导出任务已提交，正在处理中...',
            'data' => [
                'token' => $token
            ]
        ];
    }

    /**
     * 格式化下拉选项
     */
    private function formatOptions($map)
    {
        $options = [];
        foreach ($map as $value => $label) {
            $options[] = [
                'label' => $label,
                'value' => $value
            ];
        }
        return $options;
    }
}