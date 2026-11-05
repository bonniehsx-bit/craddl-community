<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2025-01-31
 * Time: 14:30
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
use app\models\CommonPayRefund;

// 引用我们新建立的流水表
use app\models\CommonPayRefundHandleLog;
use app\models\User;
use app\services\RefundAttentionService;

class RefundLogListForm extends BaseModel
{
    public $page;
    public $limit;
    public $keyword;     // 搜索关键词 (订单号/退款单号/用户昵称/手机号)
    public $user_id;     // 特定用户
    public $status;      // 状态筛选: 0=待处理, 1=处理中, 2=成功, 3=失败
    public $is_export;
    public $search_date; // 时间筛选

    /** 「备注」里怎么说最近一次生效的人工处理 */
    const HANDLE_TEXT = [
        CommonPayRefundHandleLog::ACTION_MARK_REFUNDED => '人工登记已退款',
        CommonPayRefundHandleLog::ACTION_RETRY => '人工重新发起',
        CommonPayRefundHandleLog::ACTION_QUERY => '人工查询确认已退款',
    ];

    public function rules()
    {
        return [
            [['search_date'], 'safe'],
            [['page', 'limit', 'is_export', 'user_id', 'status'], 'integer'],
            [['keyword'], 'string'],
            [['limit'], 'default', 'value' => 10],
            [['page'], 'default', 'value' => 1]
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // 1. 构建基础查询，关联用户表
        $query = CommonPayRefund::find()
            ->with(['commonPayOrder'])
            ->alias('r')
            ->leftJoin(['u' => User::tableName()], 'u.id=r.user_id')
            ->where(['r.is_delete' => 0]);

        // 2. 商城ID隔离 (如果有 mall_id 属性，建议加上，防止越权)
        if (\Yii::$app->mall->id) {
            $query->andWhere(['r.mall_id' => \Yii::$app->mall->id]);
        }

        // 3. 关键词搜索 (支持：订单号、退款单号、用户昵称、用户手机号)
        if ($this->keyword) {
            $query->andWhere([
                'or',
                ['like', 'r.order_no', $this->keyword],      // 原订单号
                ['like', 'r.out_refund_no', $this->keyword], // 退款单号
                ['like', 'u.nickname', $this->keyword],      // 用户昵称
                ['like', 'u.mobile', $this->keyword]         // 用户手机
            ]);
        }

        // 4. 用户筛选
        if ($this->user_id) {
            $query->andWhere(['r.user_id' => $this->user_id]);
        }

        // 5. 状态筛选
        if ($this->status !== '' && $this->status !== null) {
            $query->andWhere(['r.status' => $this->status]);
        }

        // 6. 时间筛选
        if ($this->search_date && count($this->search_date) > 0) {
            $start_at = strtotime($this->search_date[0]);
            $end_at = strtotime($this->search_date[1]) + 86399; // 包含当天的最后一秒
            $query->andWhere(['>=', 'r.created_at', $start_at]);
            $query->andWhere(['<=', 'r.created_at', $end_at]);
        }

        // 7. 分页处理
        if (!$this->is_export) {
            $query->page($pagination, $this->limit, $this->page);
        }

        // 8. 查询数据
        $list = $query
            ->select(['u.nickname', 'u.avatar_url', 'u.mobile', 'r.*'])
            ->orderBy('r.id DESC')
            ->asArray()
            ->all();

        // 人工处理记录(「退款失败」处理台上做的):处理完的退款会离开处理台,之后只能在这里看到是怎么处理的
        $logs = CommonPayRefundHandleLog::listByRefund(array_column($list, 'id'));
        $mallId = (int)\Yii::$app->mall->id;

        // 9. 格式化数据
        foreach ($list as &$item) {
            $item['created_at'] = date('Y-m-d H:i:s', $item['created_at']);
            $item['updated_at'] = date('Y-m-d H:i:s', $item['updated_at']);
            if ($item['commonPayOrder']) {
                $item['order_no'] = $item['commonPayOrder']['order_no'];
                $item['order_id'] = $item['commonPayOrder']['order_id'];
            }
            $item['payment_name'] = $item['commonPayOrder'] ? RefundAttentionService::paymentName($item['commonPayOrder']['payment'], $mallId) : '';
            $item['success_time'] = (int)$item['success_at'] > 0 ? date('Y-m-d H:i:s', (int)$item['success_at']) : '';

            // 最近一次生效的人工处理(没查到已退款的「查询结果」不算)
            $item['logs'] = $logs[(int)$item['id']] ?? [];
            $handle = null;
            foreach ($item['logs'] as $l) {
                if ($l['result']) {
                    $handle = $l;
                }
            }
            $item['handle_text'] = $handle ? (self::HANDLE_TEXT[$handle['action']] ?? $handle['action_text']) : '';
            $item['handle_remark'] = $handle['remark'] ?? '';
            $item['handle_voucher'] = $handle['voucher'] ?? '';
            $item['handle_operator'] = $handle ? ($handle['operator_name'] ?: '系统') : '';
            $item['handle_at'] = $handle['created_at'] ?? '';
            // 人工登记的退款:钱是在支付平台或线下退的,不是系统原路退的,对账时要和网关退款分开看
            $item['manual'] = (int)((int)$item['status'] === CommonPayRefund::STATUS_SUCCESS
                && $handle && $handle['action'] === CommonPayRefundHandleLog::ACTION_MARK_REFUNDED);

            // 补充状态文字 (方便前端直接展示，也可以前端处理)
            $statusMap = [
                CommonPayRefund::STATUS_PENDING => '待处理',
                CommonPayRefund::STATUS_PROCESSING => '处理中',
                CommonPayRefund::STATUS_SUCCESS => '退款成功',
                CommonPayRefund::STATUS_FAIL => '退款失败',
            ];
            $item['status_text'] = ($statusMap[$item['status']] ?? '未知') . ($item['manual'] ? '(人工登记)' : '');

            // 补充来源文字
            $item['source_text'] = CommonPayRefund::SOURCE_TEXT[$item['source_type']] ?? $item['source_type'];
            unset($item['commonPayOrder']);
        }
        unset($item);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'list' => $list,
            'pagination' => $pagination ?? null
        ]);
    }
}
