<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-09-30
 * Time: 23:45
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
use app\forms\common\express\ExpressQueryForm;
use app\helpers\JsonHelper;
use app\helpers\OptionHelper;
use app\helpers\ResponseHelper;
use app\helpers\SerializeHelper;
use app\models\BaseModel;
use app\models\ExpressLog;
use app\models\Order;
use app\models\OrderDetail;
use app\models\OrderRefund;
use app\models\UserCoupon;
use app\services\OrderService;
use app\services\RefundProgressService;
use yii\helpers\ArrayHelper;
use Yii;
use yii\helpers\Json;

class OrderForm extends BaseModel
{
    public $order_id;
    public $express_id;

    public function rules()
    {
        return [
            [['order_id', 'express_id'], 'integer'],
            [['order_id'], 'required'],
        ];
    }

    /**
     * 获取订单详情 (性能优化版)
     * @return array
     */
    /**
     * [最终版] 获取订单详情，适配多种配送方式
     * @return array
     */
    public function detail()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // 1. 【性能优化】使用 with() 预加载所有基础关联数据
        $orderArray = Order::find()->where([
            'id' => $this->order_id,
            'is_delete' => 0,
            'user_id' => \Yii::$app->user->identity->id
        ])->with([
            // ⛔ asArray() 查出来的关联整行原样给顾客,不走模型的隐藏字段 —— 只取页面要用的列:
            //    商户整行有证件照片、让利比例,商品整行有成本价、采购价(订单详情页 / 评价页用到的就这些)
            'orderDetails',
            'orderDetails.goods' => fn($q) => $q->select(['id', 'name', 'cover_pic', 'is_refund', 'mch_id']),
            'mch' => fn($q) => $q->select(['id', 'mch_name', 'tel', 'mobile']),
        ])->asArray()->one();

        if (!$orderArray) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '订单不存在!');
        }

        // 3. 格式化时间戳
        $timeFields = ['created_at', 'pay_at', 'send_at', 'confirm_at', 'finish_at'];
        foreach ($timeFields as $field) {
            if (!empty($orderArray[$field])) {
                $orderArray[$field] = date('Y-m-d H:i:s', $orderArray[$field]);
            }
        }

        // 4. 从预加载的数据中处理详情
        $list = $orderArray['orderDetails'] ?? [];
        // 哪些商品真有售后单:取消订单退款成功后明细也会标成「售后已完成」,但没有售后单,
        // 前端据此不显示「售后详情」(点进去只会报「售后单不存在」)
        // 值 = 这件商品最近一张售后单的状态:被拒绝后明细 refund_status 会回到 0,
        // 前端靠它照样给出「查看原因」,顾客能看到拒绝原因
        $afterSale = [];
        foreach (OrderRefund::find()->select(['order_detail_id', 'status'])
            ->where(['order_id' => (int)$orderArray['id'], 'is_delete' => 0])
            ->orderBy(['id' => SORT_ASC])->asArray()->all() as $r) {
            $afterSale[(int)$r['order_detail_id']] = (int)$r['status'];
        }
        foreach ($list as &$item) {
            // 显示下单那一刻的商品(快照),并去掉成本价 / 商家货款等内部数据 —— 这里是明细整行给顾客
            OrderDetail::applySnapshotToRow($item);
            OrderDetail::scrubForCustomer($item);
            $item['has_after_sale'] = isset($afterSale[(int)$item['id']]);
            $item['last_refund_status'] = $afterSale[(int)$item['id']] ?? null;
            // 能不能申请售后:认下单时的快照(与 OrderDetail::canRefund 同一规则),前端据此显示「申请售后」
            $snap = $item['is_can_refund'] ?? null;
            $item['can_refund'] = ($snap !== null && $snap !== '') ? (int)$snap === 1 : !empty($item['goods']['is_refund']);
            if ($item['attr']) {
                try {
                    $item['attr'] = Json::decode($item['attr']);
                } catch (\Exception $e) {
                    $item['attr'] = [];
                }
            } else {
                $item['attr'] = [];
            }
        }
        unset($item);

        // 5. 单独查询物流信息（通常一个订单物流信息不多，此查询开销小）
        $express_list = [];
        if (in_array($orderArray['deliver_type'], ['express'])) {
            $express_list = ExpressLog::find()->where(['order_no' => $orderArray['order_no'], 'is_delete' => 0])->asArray()->all();
        }
        if ($orderArray['deliver_type'] == 'city') {
            $orderArray['city_delivery_info'] = $orderArray['city_delivery_info'] ? JsonHelper::decode($orderArray['city_delivery_info']) : null;
        }

        // 调用 Handler 获取【小程序前端】所需的 3 种动态数据
        //   · mobile_panels        —— 插件特有面板数据（Panel-Driven）
        //   · mobile_actions       —— 底部按钮 + 状态文案（由后端决定）
        //   · override_template    —— 整页接管组件名（如 yuda 等特殊订单）
        $pluginData = null;
        $mobileActions = null;
        $overrideTemplate = null;
        try {
            $orderModel = Order::findOne($orderArray['id']); // 获取Order模型实例
            if ($orderModel) {
                $handler = \app\services\order\factory\OrderHandlerFactory::create($orderModel->plugin, $orderModel->type);

                // Panel-Driven 动态面板（小程序专属，不走 PC 后台的 getFrontendDisplayPanels）
                $mobilePanels = $handler->getMobileDetailPanels($orderModel);
                if (!empty($mobilePanels)) {
                    $pluginData = [
                        'type' => $orderModel->plugin,
                        'display_panels' => $mobilePanels,
                    ];
                }

                // 底部按钮 + 状态文案
                $mobileActions = $handler->getMobileActions($orderModel);

                // 整页接管（逃生舱口）
                $overrideTemplate = $handler->getMobileOverrideTemplate($orderModel);
            }
        } catch (\Exception $e) {
            \Yii::error("获取订单 mobile handler 数据失败: " . $e->getMessage());
        }

        // 下发「禁止取消/退款」开关(mall_setting.is_ban_cancel):供前端隐藏用户端「申请售后」按钮,后台不受限
        $orderArray['is_ban_cancel'] = (int)(OptionHelper::get('mall_setting', $orderArray['mall_id'], [])['is_ban_cancel'] ?? 0);
        // 这类订单能不能走通用「申请售后」:由插件在 getOrderBehaviors 声明,前端不再按插件名判断;
        // 订单结算后也不能再申请(与 RefundService::apply 同一条规则)
        $orderArray['allow_after_sale'] = empty(\app\services\order\OrderBehaviorRegistry::ofOrder(
            (object)['plugin' => (string)($orderArray['plugin'] ?? ''), 'type' => (string)($orderArray['type'] ?? '')]
        )['no_user_after_sale']) && (int)($orderArray['settlement_status'] ?? 0) !== \app\models\Order::SETTLEMENT_STATUS_SETTLED;

        // 退款进度:这个订单的每一笔退款现在到哪一步了(说法见 RefundProgressService,不给顾客看失败原因)
        $orderArray['refund_progress'] = RefundProgressService::forOrder((int)$orderArray['id'], (int)$orderArray['mall_id']);

        // 6. 组装最终返回数据
        $responseData = [
            'order' => $orderArray,
            'list' => $list,
            'express_list' => $express_list,
            'plugin_data' => $pluginData,         // Panel-Driven 面板
            'mobile_actions' => $mobileActions,   // 底部按钮 / 状态文案配置
            'override_template' => $overrideTemplate, // 整页接管组件名（null 走通用 Shell）
        ];

        // 清理掉不再需要的嵌套关联数据，保持API返回干净
        unset($responseData['order']['orderDetails']);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', $responseData);
    }

    /**
     * 取消订单 (调用服务层)
     * @return array
     */
    public function cancel()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $order = $this->getOrder();
        if (!$order) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '订单不存在!');
        }
        if ($order->status != Order::STATUS_UNPAID) {
            // 如果是待发货，提示更具体
            if ($order->status == Order::STATUS_TO_BE_SHIPPED) {
                return $this->apiResponse(ApiCode::CODE_FAIL, '订单已付款，请申请售后进行退款');
            }
            return $this->apiResponse(ApiCode::CODE_FAIL, '当前订单状态不允许取消');
        }
        $orderService = new OrderService();
        // 第三个参数:锁住订单行后再确认一次仍是待付款(上面的判断读的不是锁内数据,支付可能恰好在这之间落地)
        if ($orderService->cancel($order, false, true)) {
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '订单已取消');
        } else {
            return $this->apiResponse(ApiCode::CODE_FAIL, $this->getErrorMsg($orderService));
        }
    }

    /**
     * 确认收货 (调用服务层)
     * @return array
     */
    public function confirm()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $order = $this->getOrder();
        if (!$order) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '订单不存在或无法操作!');
        }

        $orderService = new OrderService();
        if ($orderService->confirm($order)) {
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '确认收货成功');
        } else {
            return $this->apiResponse(ApiCode::CODE_FAIL, $this->getErrorMsg($orderService));
        }
    }

    /**
     * 删除订单 (调用服务层)
     * @return array
     */
    public function delete()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $order = $this->getOrder();
        if (!$order) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '订单不存在!');
        }
        $orderService = new OrderService();
        if ($orderService->delete($order)) {
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '删除成功');
        } else {
            return $this->apiResponse(ApiCode::CODE_FAIL, $this->getErrorMsg($orderService));
        }
    }

    /**
     * 获取订单物流信息 (此方法逻辑不涉及状态，保持原样)
     * @return array
     */
    public function getExpress()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $order = $this->getOrder();
        if (!$order) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '订单不存在!');
        }

        $express_detail = null;

        // 支持通过传入特定的包裹/物流记录ID，查询拆分的某个具体包裹。否则默认取最新的包裹
        $query = ExpressLog::find()->where(['order_no' => $order->order_no, 'is_delete' => 0]);
        if ($this->express_id) {
            $query->andWhere(['id' => $this->express_id]);
        }
        $log = $query->orderBy(['id' => SORT_DESC])->one();
        if ($log) {
            try {
                $form = new ExpressQueryForm();
                $form->attributes = $log->attributes;
                $res = $form->search();
                if ($res['code'] == 0) {
                    $express_detail = $res['data'];
                }
            } catch (\Exception $exception) {
                // 可记录日志
            }
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['express_detail' => $express_detail]);
    }

    /**
     * 用户完成订单 (已废弃，逻辑合并到 confirm)
     * @return array
     */
    public function finish()
    {
        // 直接调用 confirm 方法，因为在新状态模型下，用户确认收货即代表订单流程完成
        return $this->confirm();
    }

    /**
     * 封装一个获取订单的私有方法，减少重复代码
     * @return Order|null
     */
    private function getOrder()
    {
        return Order::findOne([
            'id' => $this->order_id,
            'is_delete' => 0,
            'user_id' => \Yii::$app->user->identity->id
        ]);
    }
}
