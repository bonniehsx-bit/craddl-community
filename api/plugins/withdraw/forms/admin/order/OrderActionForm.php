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

namespace app\plugins\withdraw\forms\admin\order;

use app\core\ApiCode;
use app\models\BaseModel;
use app\plugins\withdraw\models\WithdrawOrder;
use app\plugins\withdraw\services\WithdrawService;
use Yii;

/**
 * 后台 - 提现单操作：审核 / 打款 / 标记到账 / 标记失败
 *
 * 所有状态流转与资金退回都委托给 WithdrawService（单一状态机来源）。
 */
class OrderActionForm extends BaseModel
{
    public $id;
    public $remark;
    public $receipt; // 打款凭证图片URL
    public $offline; // 1=走「线下打款」兜底(强制 ManualFund),空/0=按订单原渠道自动打款

    public function rules()
    {
        return [
            [['id'], 'required'],
            [['id'], 'integer'],
            [['offline'], 'boolean'],
            [['remark', 'receipt'], 'string'],
        ];
    }

    /** 审核：$pass 通过/驳回 */
    public function audit($pass)
    {
        $order = $this->findOrder();
        if (!$order instanceof WithdrawOrder) {
            return $order; // 错误响应
        }
        $adminId = (int)(Yii::$app->admin->id ?? 0);
        try {
            (new WithdrawService())->audit($order, $adminId, (bool)$pass, (string)$this->remark);
        } catch (\Throwable $e) {
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
        return $this->ok($order, $pass ? '审核通过' : '已驳回并退回');
    }

    /** 发起打款 */
    public function payout()
    {
        $order = $this->findOrder();
        if (!$order instanceof WithdrawOrder) {
            return $order;
        }
        $offline = (bool)$this->offline;
        try {
            (new WithdrawService())->payout($order, $offline);
        } catch (\Throwable $e) {
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
        return $this->ok($order, ($offline ? '已生成线下打款单：' : '打款已发起：') . $order->getStatusText());
    }

    /** 手动标记到账（线下转账后确认 / 异步网关确认），可附打款凭证 */
    public function markSuccess()
    {
        $order = $this->findOrder();
        if (!$order instanceof WithdrawOrder) {
            return $order;
        }
        try {
            // 这里 remark 复用为网关单号备注位；凭证单独传 receipt
            (new WithdrawService())->markSuccess($order, (string)$this->remark, (string)$this->receipt);
        } catch (\Throwable $e) {
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
        return $this->ok($order, '已标记到账');
    }

    /** 上传/更新打款凭证（事后补传） */
    public function uploadReceipt()
    {
        $order = $this->findOrder();
        if (!$order instanceof WithdrawOrder) {
            return $order;
        }
        if (empty($this->receipt)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '请上传凭证');
        }
        (new WithdrawService())->updateReceipt($order, (string)$this->receipt);
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '凭证已保存');
    }

    /** 标记失败 → 退回资产 */
    public function markFailed()
    {
        $order = $this->findOrder();
        if (!$order instanceof WithdrawOrder) {
            return $order;
        }
        try {
            (new WithdrawService())->markFailed($order, (string)($this->remark ?: '后台标记失败'));
        } catch (\Throwable $e) {
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
        return $this->ok($order, '已标记失败并退回');
    }

    /** 打款失败后「重试」→ 退回待打款,资金不动,管理员修好问题后可重新打款 */
    public function retry()
    {
        $order = $this->findOrder();
        if (!$order instanceof WithdrawOrder) {
            return $order;
        }
        try {
            (new WithdrawService())->retryPayout($order);
        } catch (\Throwable $e) {
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
        return $this->ok($order, '已重置为待打款，请重新发起打款');
    }

    private function findOrder()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $mallId = (int)Yii::$app->mall->id;
        $order = WithdrawOrder::findOne(['id' => $this->id, 'mall_id' => $mallId, 'is_delete' => 0]);
        if (!$order) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '提现单不存在');
        }
        return $order;
    }

    private function ok(WithdrawOrder $order, string $msg)
    {
        return $this->apiResponse(ApiCode::CODE_SUCCESS, $msg, [
            'status' => (int)$order->status,
            'status_text' => $order->getStatusText(),
        ]);
    }
}
