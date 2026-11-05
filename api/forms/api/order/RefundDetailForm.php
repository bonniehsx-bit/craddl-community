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

namespace app\forms\api\order;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\OrderDetail;
use app\models\OrderRefund;
use app\services\RefundService;
use Yii;
use yii\helpers\Json;

class RefundDetailForm extends BaseModel
{
    public $order_detail_id;

    public function rules()
    {
        return [
            [['order_detail_id'], 'required'],
            [['order_detail_id'], 'integer'],
        ];
    }

    public function getDetail()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        /** @var OrderRefund $refundDetail */
        $refundDetail = OrderRefund::find()
            ->where([
                'order_detail_id' => $this->order_detail_id,
                'user_id' => Yii::$app->user->id,
                'is_delete' => 0
            ])
            ->with(['order', 'mch', 'orderDetail.goods'])
            ->orderBy(['id' => SORT_DESC])
            ->one(); // 也就是得到对象，方便后续处理

        if (!$refundDetail) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '售后单不存在');
        }

        // 转数组用于输出
        $data = $refundDetail->toArray();

        // --- 1. 数据格式化 ---
        $timeFields = ['created_at', 'updated_at', 'user_express_at'];
        foreach ($timeFields as $field) {
            $data[$field] = $data[$field] ? date('Y-m-d H:i:s', $data[$field]) : '';
        }

        $data['pic_list'] = !empty($data['pic_list']) ? Json::decode($data['pic_list']) : [];

        // 处理商品信息
        if ($refundDetail->orderDetail) {
            $detail = $refundDetail->orderDetail;
            $data['orderDetail']['attr'] = !empty($detail->attr) ? Json::decode($detail->attr) : [];

            // 提炼顶层字段方便前端
            // 下单那一刻的商品(快照),老数据回退商品表
            $data['goods_name'] = $detail->snapshotName();
            $data['goods_pic'] = $detail->snapshotPic();
            $data['num'] = $detail->num;
            $data['price'] = $detail->total_price;

            $attrValues = array_column($data['orderDetail']['attr'], 'value');
            $data['attr_text'] = implode('; ', $attrValues);
        }

        // 这张售后单最新一笔退款的进度(说法见 RefundProgressService:不说失败原因,不把线下退、人工登记的说成原路退回)
        $progress = \app\services\RefundProgressService::forAfterSale((int)$refundDetail->id, (int)$refundDetail->mall_id);
        $data['refund_progress'] = $progress;

        // --- 2. 【新增】构建进度流程 Process List ---
        // 前端可以直接循环展示 Timeline
        $process = [];
        // 节点1: 提交申请 (肯定存在)
        $process[] = ['title' => '提交申请', 'desc' => $data['created_at'], 'active' => true];

        // 节点2: 商家处理
        if ($refundDetail->status == OrderRefund::STATUS_PENDING) {
            $process[] = ['title' => '等待商家处理', 'desc' => '商家审核中', 'active' => true];
        } else {
            // 已过审核阶段
            $auditTitle = $refundDetail->status == OrderRefund::STATUS_REJECTED ? '商家拒绝' : '商家同意';
            $process[] = ['title' => $auditTitle, 'desc' => $data['updated_at'], 'active' => true];
        }

        // 节点3: 退货/打款 (根据类型和状态动态展示)
        if ($refundDetail->status != OrderRefund::STATUS_REJECTED && $refundDetail->status != OrderRefund::STATUS_CLOSED) {
            // 退货退款、换货都要把货寄回来
            if (in_array((int)$refundDetail->type, [RefundService::TYPE_RETURN_AND_REFUND, RefundService::TYPE_EXCHANGE], true)) {
                if ($refundDetail->status >= OrderRefund::STATUS_USER_SHIPPED) {
                    $process[] = ['title' => '用户已发货', 'desc' => $data['user_express_at'], 'active' => true];
                } elseif($refundDetail->status == OrderRefund::STATUS_AGREED) {
                    $process[] = ['title' => '等待用户发货', 'desc' => '', 'active' => false];
                }
            }

            // 退款中:商家已确认打款,钱由系统按原支付方式去退 —— 按这笔退款的实际进度显示
            if ($refundDetail->status == OrderRefund::STATUS_PROCESSING) {
                $process[] = $progress
                    ? ['title' => $progress['state_text'], 'desc' => $progress['desc'], 'active' => true]
                    : ['title' => '退款中', 'desc' => '正在按原支付方式退回,请耐心等待', 'active' => true];
            }

            // 完成节点(换货不退钱,没有退款时间,用商家点完成的时间)
            if ($refundDetail->status == OrderRefund::STATUS_COMPLETED) {
                $process[] = (int)$refundDetail->type === RefundService::TYPE_EXCHANGE
                    ? ['title' => '换货完成', 'desc' => $data['updated_at'], 'active' => true]
                    : ['title' => '退款完成', 'desc' => $data['refund_time'], 'active' => true];
            }
        }

        $data['process_list'] = $process;

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', ['detail' => $data]);
    }

    public function cancel()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            /** @var OrderRefund $refund */
            $refund = OrderRefund::find()
                ->where([
                    'order_detail_id' => $this->order_detail_id,
                    'user_id' => Yii::$app->user->id,
                    'is_delete' => 0
                ])
                ->orderBy(['id' => SORT_DESC])
                ->forUpdate()
                ->one();

            if (!$refund) {
                throw new \Exception('售后单不存在');
            }

            // 【优化】允许撤销的状态：待审核(0) OR 已同意(1)且未发货
            // 如果已经发货了(5)或者已经退款完成(3)或者拒绝(2)，则不能撤销
            $canCancel = false;
            if ($refund->status == OrderRefund::STATUS_PENDING) {
                $canCancel = true;
            } elseif ($refund->status == OrderRefund::STATUS_AGREED) {
                // 如果是“退货退款”，但在用户发货前，也可以撤销
                // 如果是“仅退款”，在商家打款前（status还是1时），也可以撤销
                $canCancel = true;
            }

            if (!$canCancel) {
                throw new \Exception('当前状态无法撤销申请');
            }

            $refund->status = OrderRefund::STATUS_CLOSED;
            if (!$refund->save()) {
                throw new \Exception($this->getErrorMsg($refund));
            }

            // 恢复订单详情
            $orderDetail = OrderDetail::findOne($this->order_detail_id);
            if ($orderDetail) {
                $orderDetail->refund_status = OrderDetail::REFUND_STATUS_NORMAL;
                $orderDetail->save();
            }

            // 同步主订单
            $refundService = new RefundService();
            $refundService->updateOrderStatusAfterAction($refund->order);

            $transaction->commit();
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '撤销成功');

        } catch (\Exception $e) {
            $transaction->rollBack();
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }
}