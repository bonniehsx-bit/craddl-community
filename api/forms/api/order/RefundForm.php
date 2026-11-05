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
use app\helpers\OptionHelper;
use app\models\BaseModel;
use app\models\Order;
use app\models\OrderDetail;
use app\services\RefundService;
use yii\helpers\Json;
use Yii;

class RefundForm extends BaseModel
{
    public $order_detail_id;

    // 提交参数
    public $type;
    public $refund_price;
    public $content; // 退款说明
    public $reason;  // 退款原因
    public $pic_list;

    public function rules()
    {
        return [
            [['order_detail_id'], 'required'],
            [['type'], 'integer'],
            [['refund_price'], 'number', 'min' => 0],
            [['content', 'reason'], 'string', 'max' => 255],
            [['pic_list'], 'safe'],
        ];
    }

    /**
     * 商城是否「禁止取消/退款」(mall_setting.is_ban_cancel=1)。
     * 该开关只约束【用户端】申请退款/售后;后台关闭订单 / 后台退款走各自入口,不受此限制。
     */
    private function isRefundBanned(): bool
    {
        $setting = OptionHelper::get('mall_setting', (int)Yii::$app->mall->id, []);
        return (int)($setting['is_ban_cancel'] ?? 0) === 1;
    }

    /**
     * 获取售后预览数据 (前端加载售后页时调用)
     */
    public function preview()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        if ($this->isRefundBanned()) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '商家已关闭退款/售后功能,如需帮助请联系客服');
        }

        /** @var OrderDetail $orderDetail */
        $orderDetail = OrderDetail::find()
            ->with(['goods', 'order'])
            ->where([
                'id' => $this->order_detail_id,
                'user_id' => Yii::$app->user->id,
                'is_delete' => 0
            ])
            ->one();

        if (!$orderDetail) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '订单商品不存在');
        }

        // 基础校验
        if ($orderDetail->refund_status != 0 && $orderDetail->refund_status != OrderDetail::REFUND_STATUS_NORMAL) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '该商品已在售后中');
        }
        if (!$orderDetail->canRefund()) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '该商品不支持售后');
        }
        // 订单结算后不能再申请售后(与 RefundService::apply 同一条规则,在这里就拦下,不让顾客填完才报错)
        if ($orderDetail->order && (int)$orderDetail->order->settlement_status === Order::SETTLEMENT_STATUS_SETTLED) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '订单已结算，不能再申请售后');
        }

        // 1. 调用 Service 计算最大可退金额 (包含运费逻辑)。售后只能整件退,这就是本次的退款金额
        $service = new RefundService();
        $maxPrice = $service->getMaxRefundPrice($orderDetail);

        // 2. 【新增】计算其中包含的运费金额
        // 逻辑：总可退 - 商品本金 = 包含的运费
        // 商品本金优先取 pay_price，如果没有则按比例计算 (保持与Service一致的降级逻辑)
        $itemRealPrice = 0.00;
        if (isset($orderDetail->pay_price) && $orderDetail->pay_price > 0) {
            $itemRealPrice = (float)$orderDetail->pay_price;
        } else {
            $order = $orderDetail->order;
            // 订单实付减去运费 = 纯商品实付
            $actualPaidGoodsTotal = bcsub($order->pay_price, $order->express_price, 2);
            if ($actualPaidGoodsTotal > 0 && $order->goods_total_price > 0) {
                $ratio = bcdiv($orderDetail->total_price, $order->goods_total_price, 8);
                $itemRealPrice = (float)bcmul($actualPaidGoodsTotal, $ratio, 2);
            }
        }

        // 计算差值：如果 Service 返回的 maxPrice 大于 商品本金，说明Service把运费加进去了
        $refundExpressPrice = 0.00;
        if ($maxPrice > $itemRealPrice) {
            $refundExpressPrice = bcsub($maxPrice, $itemRealPrice, 2);
        }

        // 3. 构建售后类型列表
        $typeList = [];
        // 状态20(待发货)：只能仅退款
        if ($orderDetail->order->status == Order::STATUS_TO_BE_SHIPPED) {
            $typeList[] = [
                'name' => '仅退款 (未发货)',
                'value' => RefundService::TYPE_REFUND_ONLY,
                'desc' => '商家未发货，申请后直接退款'
            ];
        } else {
            // 已发货/已完成
            $typeList[] = [
                'name' => '仅退款',
                'value' => RefundService::TYPE_REFUND_ONLY,
                'desc' => '未收到货，或协商一致不用退货'
            ];
            $typeList[] = [
                'name' => '退货退款',
                'value' => RefundService::TYPE_RETURN_AND_REFUND,
                'desc' => '已收到货，需要退还货物'
            ];
        }

        // 退款原因配置
        $reasonList = ['拍多/拍错/不想要', '缺货', '商品描述不符', '质量问题', '少件/漏发', '包装/商品破损'];

        // 处理商品属性显示
        $attrText = '';
        if (!empty($orderDetail->attr)) {
            $attrArr = Json::decode($orderDetail->attr);
            $attrText = implode('; ', array_column($attrArr, 'value'));
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'goods' => [
                // 下单那一刻的商品(快照),老数据回退商品表
                'name' => $orderDetail->snapshotName(),
                'cover_pic' => $orderDetail->snapshotPic(),
                'attr_text' => $attrText,
                'num' => $orderDetail->num,
                'total_price' => $orderDetail->total_price, // 显示原价/总价供参考
            ],
            'max_refund_price' => sprintf("%.2f", $maxPrice),
            'refund_express_price' => sprintf("%.2f", $refundExpressPrice), // 【新增】告诉前端退了多少运费
            'refund_type_list' => $typeList,
            'reason_list' => $reasonList
        ]);
    }

    /**
     * 提交售后申请
     */
    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        if ($this->isRefundBanned()) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '商家已关闭退款/售后功能,如需帮助请联系客服');
        }

        $refundService = new RefundService();

        $params = [
            'type' => $this->type,
            'refund_price' => $this->refund_price,
            'reason' => $this->reason,   // 对应下拉选择的原因
            'content' => $this->content, // 对应备注文本
            'pic_list' => $this->pic_list,
        ];

        $refund = $refundService->apply($this->order_detail_id, Yii::$app->user->id, $params);

        if ($refund) {
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '申请提交成功，请等待商家审核');
        } else {
            return $this->apiResponse(ApiCode::CODE_FAIL, $this->getErrorMsg($refundService));
        }
    }
}