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
use app\models\CommonPayRefund;
use app\models\CommonPayRefundHandleLog;
use app\models\User;
use app\services\RefundAttentionService;

/**
 * 「退款失败」处理台列表:需要人工处理的退款(口径见 RefundAttentionService)。
 *
 * 原来读的是旧表 refund_fail_log(早年银行代付留下的),现在已没有代码往里写,页面永远是空的;
 * 改为直接读退款流水 common_pay_refund。每条带上:失败原因的白话解释和处理建议、能不能向网关查询、
 * 重发前要不要先人工核实,以及这笔的处理记录。
 */
class RefundFailListForm extends BaseModel
{
    public $page;
    public $limit;
    public $keyword;      // 订单号 / 退款单号 / 用户昵称 / 手机号
    public $source_type;
    public $kind;         // fail / stuck / unsynced
    public $search_date;
    public $is_export;

    public function rules()
    {
        return [
            [['search_date'], 'safe'],
            [['page', 'limit', 'is_export'], 'integer'],
            [['keyword', 'source_type', 'kind'], 'string'],
            [['keyword'], 'trim'],
            [['limit'], 'default', 'value' => 20],
            [['page'], 'default', 'value' => 1],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $mallId = (int)\Yii::$app->mall->id;
        $now = time();
        $query = RefundAttentionService::query($mallId, $now)
            ->with(['commonPayOrder'])
            ->leftJoin(['u' => User::tableName()], 'u.id = r.user_id');

        if ($this->keyword) {
            $query->andWhere(['or',
                ['like', 'r.order_no', $this->keyword],
                ['like', 'r.out_refund_no', $this->keyword],
                ['like', 'u.nickname', $this->keyword],
                ['like', 'u.mobile', $this->keyword],
            ]);
        }
        if ($this->source_type) {
            $query->andWhere(['r.source_type' => $this->source_type]);
        }
        if ($this->kind === RefundAttentionService::KIND_FAIL) {
            $query->andWhere(['r.status' => CommonPayRefund::STATUS_FAIL]);
        } elseif ($this->kind === RefundAttentionService::KIND_STUCK) {
            $query->andWhere(['r.status' => CommonPayRefund::STATUS_PROCESSING]);
        } elseif ($this->kind === RefundAttentionService::KIND_UNSYNCED) {
            $query->andWhere(['r.status' => CommonPayRefund::STATUS_SUCCESS]);
        }
        if ($this->search_date && count($this->search_date) === 2) {
            $query->andWhere(['>=', 'r.created_at', strtotime($this->search_date[0])]);
            $query->andWhere(['<=', 'r.created_at', strtotime($this->search_date[1]) + 86399]);
        }
        if (!$this->is_export) {
            $query->page($pagination, $this->limit, $this->page);
        }
        /** @var CommonPayRefund[] $rows */
        $rows = $query->select(['r.*'])->orderBy(['r.id' => SORT_DESC])->all();

        $userIds = array_unique(array_map(fn($r) => (int)$r->user_id, $rows));
        $users = $userIds ? User::find()->select(['id', 'nickname', 'mobile'])->where(['id' => $userIds])->indexBy('id')->asArray()->all() : [];
        $logs = CommonPayRefundHandleLog::listByRefund(array_map(fn($r) => (int)$r->id, $rows));

        $list = [];
        foreach ($rows as $r) {
            $payOrder = $r->commonPayOrder;
            $kind = RefundAttentionService::kindOf($r);
            $isPayment = $payOrder && RefundAttentionService::isPayment($payOrder->payment);
            [$reason, $advice] = self::reasonOf($r, $kind, $isPayment);
            $u = $users[(int)$r->user_id] ?? [];
            $verifyReason = $kind === RefundAttentionService::KIND_UNSYNCED ? '' : RefundAttentionService::verifyReason($r);
            $list[] = [
                'id' => (int)$r->id,
                'order_no' => $payOrder ? $payOrder->order_no : $r->order_no,
                'order_id' => $payOrder ? (int)$payOrder->order_id : 0,
                'out_refund_no' => $r->out_refund_no,
                'refund_price' => $r->refund_price,
                'pay_price' => $payOrder ? $payOrder->pay_price : '',
                'nickname' => $u['nickname'] ?? '',
                'mobile' => $u['mobile'] ?? '',
                'source_text' => CommonPayRefund::SOURCE_TEXT[$r->source_type] ?? $r->source_type,
                'payment_name' => $payOrder ? RefundAttentionService::paymentName($payOrder->payment, $mallId) : '',
                'kind' => $kind,
                'kind_text' => RefundAttentionService::KIND_TEXT[$kind],
                'error_msg' => (string)$r->error_msg,
                'reason' => $reason,
                'advice' => $advice,
                'retry_count' => (int)$r->retry_count,
                // 余额不足的后台还会按时自动再试:给出下次时间
                'next_retry_text' => RefundAttentionService::autoRetryPending($r) ? date('m-d H:i', (int)$r->next_retry_at) : '',
                'can_query' => $kind !== RefundAttentionService::KIND_UNSYNCED && $isPayment && RefundAttentionService::canQuery($payOrder->payment),
                'can_retry' => $kind !== RefundAttentionService::KIND_UNSYNCED && $isPayment,
                'can_mark' => $kind !== RefundAttentionService::KIND_UNSYNCED,
                'need_verify' => $verifyReason !== '',
                'verify_reason' => $verifyReason,
                'created_at' => date('Y-m-d H:i:s', (int)$r->created_at),
                'updated_at' => date('Y-m-d H:i:s', (int)$r->updated_at),
                'logs' => $logs[(int)$r->id] ?? [],
            ];
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'list' => $list,
            'pagination' => $pagination ?? null,
            'source_options' => array_map(fn($k, $v) => ['value' => $k, 'label' => $v], array_keys(CommonPayRefund::SOURCE_TEXT), CommonPayRefund::SOURCE_TEXT),
            'kind_options' => array_map(fn($k, $v) => ['value' => $k, 'label' => $v], array_keys(RefundAttentionService::KIND_TEXT), RefundAttentionService::KIND_TEXT),
        ]);
    }

    /** 这笔为什么要人管、该怎么处理:[原因, 建议] */
    private static function reasonOf(CommonPayRefund $r, string $kind, bool $isPayment): array
    {
        if ($kind === RefundAttentionService::KIND_UNSYNCED) {
            return ['钱已退,售后单完成、订单状态同步等后续处理还没做完', '系统每分钟自动补做;一直不成功请联系技术排查'];
        }
        if (!$isPayment) {
            return ['这笔的支付方式记录有误,系统没法原路退款', '到支付平台或线下把钱退给顾客,再「登记已退款」'];
        }
        if ($kind === RefundAttentionService::KIND_STUCK) {
            return ['退到一半中断了,超过 30 分钟没有结果', '先「查询结果」或到支付平台核实:已退了就「登记已退款」;确认没退就勾选「已核实」重新发起'];
        }
        return RefundAttentionService::explain($r->error_msg);
    }
}
