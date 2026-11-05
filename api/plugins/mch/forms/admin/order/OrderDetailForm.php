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

namespace app\plugins\mch\forms\admin\order;

use app\core\ApiCode;
use app\helpers\JsonHelper;
use app\helpers\OptionHelper;
use app\models\BaseModel;
use app\models\Order;
use app\models\OrderDetail;
use app\services\order\OrderBehaviorRegistry;
use Yii;

// [新增]

class OrderDetailForm extends BaseModel
{
    public $order_id;

    public function rules()
    {
        return [
            [['order_id'], 'required'],
            [['order_id'], 'integer'],
        ];
    }

// 文件: app/plugins/mch/forms/admin/OrderDetailForm.php

    public function getDetail()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $currentMch = Yii::$app->mch;

        $query = Order::find()->where([
            'o.id' => $this->order_id,
            'o.mall_id' => Yii::$app->mall->id,
            'o.mch_id' => $currentMch->id,
            'o.is_delete' => 0
        ])->alias('o');

        // 预加载所有需要的数据
        // 顾客只给头像、昵称:asArray() 连表出来的是用户整行(含登录令牌、密码哈希),不走模型的隐藏字段
        $query->with([
            'user' => fn($q) => $q->select(['id', 'nickname', 'avatar_url']),
            'orderDetails.goods',
            'mch',
            'expressLog',
            'expressList',
        ]);

        $order = $query->asArray()->one();

        if (!$order) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '订单不存在或无权限查看');
        }

        // --- 处理订单详情列表 ---
        $list = $order['orderDetails'] ?? [];
        foreach ($list as &$item) {
            OrderDetail::applySnapshotToRow($item);   // 显示下单时的商品(快照),老数据回退商品表
            if (!empty($item['attr'])) {
                $item['attr'] = JsonHelper::decode($item['attr']);
            }
            if (!empty($item['mch_income_breakdown'])) {
                try {
                    $item['mch_income_breakdown'] = JsonHelper::decode($item['mch_income_breakdown']);
                } catch (\Exception $e) {
                    $item['mch_income_breakdown'] = [];
                }
            } else {
                $item['mch_income_breakdown'] = [];
            }
        }
        unset($item);

        // --- 处理用户信息 ---
        $user = $order['user'] ?? null;

        if (!empty($order['city_delivery_info'])) {
            $order['city_delivery_info'] = JsonHelper::decode($order['city_delivery_info']);
        }

        // 将期望送达时间格式化成更友好的文本
        if ($order['is_immediately']) {
            $order['expected_delivery_text'] = '立即送出';
        } elseif ($order['delivery_time']) {
            $order['expected_delivery_text'] = $order['delivery_time'];
        } else {
            $order['expected_delivery_text'] = '尽快送达';
        }

        // --- 动态获取插件展示面板 (Plugin Data) 与 操作权限控制 (Action Config) ---
        $orderModel = Order::findOne($order['id']);

        // 结算进度:商家最关心「钱什么时候到」,不给的话同城配送单在自提单旁边看着就像丢了
        $order['settle_info'] = $this->buildSettleInfo($orderModel);

        $pluginData = null;
        try {
            $realOrder = clone $orderModel; // 克隆一份避免影响后续逻辑

            // 确保没有 plugin 字段依然会实例化通用处理器
            $pluginCode = !empty($realOrder->plugin) ? $realOrder->plugin : 'normal';
            $handler = \app\services\order\factory\OrderHandlerFactory::create($pluginCode, $realOrder->type);
            
            $panels = $handler->getFrontendDisplayPanels($realOrder);
            $actionConfig = $handler->getActionConfig($realOrder); // 获取底层定义的控制配置
            
            // 只要有其中一个配置，就注入到前端
            if (!empty($panels) || !empty($actionConfig)) {
                $pluginData = [
                    'type' => $realOrder->plugin,
                    'display_panels' => $panels,
                    'action_config' => $actionConfig 
                ];
            }
        } catch (\Exception $e) {
            \Yii::error("商户端获取订单插件展示配置失败: " . $e->getMessage());
        }

        // 有售后还在处理时不能发货、不能核销:按钮显隐与后端拦截用同一个判断(与平台端一致)
        $order['after_sale_block_reason'] = \app\services\OrderService::afterSaleBlockReason($order);

        // order.orderDetails 是没加工过的原始明细,也盖上快照,和 list 显示的商品一致(与平台端一致)
        foreach (array_keys($order['orderDetails'] ?? []) as $i) {
            OrderDetail::applySnapshotToRow($order['orderDetails'][$i]);
        }

        // 与平台端保持一致的返回结构
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'user' => $user,
            'order' => $order,
            'list' => $list,
            'plugin_data' => $pluginData,
        ]);
    }

    /**
     * 结算进度提示。
     *
     * ⚠ 到期时间的算法必须和 OrderSettleTask 保持同一口径,否则页面上写的时间
     * 和钱真正到账的时间对不上,比不显示更糟:
     *     到期 = finish_at + mall_setting.order_settle_day * 86400
     *
     * 之所以要做这个:无物流的订单(自提/核销/卡密)走 OrderService::directSettle
     * 当场就结算了,有物流的要等售后期由 cron 结。商家同一批单子看到一半秒结、
     * 一半没动静,又没有任何时间提示,只能理解成「漏结了」——实际只是没到点。
     *
     * @param Order|null $order
     */
    private function buildSettleInfo($order): array
    {
        if (!$order) {
            return [];
        }

        $settlementStatus = (int)$order->settlement_status;
        if ($settlementStatus === Order::SETTLEMENT_STATUS_SETTLED) {
            $at = (int)$order->settled_at;
            return [
                'status' => 'settled',
                'at'     => $at,
                'text'   => $at > 0 ? ('已于 ' . date('m-d H:i', $at) . ' 结算') : '已结算',
            ];
        }
        if ($settlementStatus === Order::SETTLEMENT_STATUS_INVALID) {
            return ['status' => 'invalid', 'at' => 0, 'text' => '无需结算'];
        }

        // 自主结算的业态(收银台等)由自己推进 settlement_status,核心 cron 根本不碰它,
        // 按核心口径算出来的时间是假的 —— 宁可不显示。
        if (OrderBehaviorRegistry::isSelfSettled($order)) {
            return ['status' => 'self', 'at' => 0, 'text' => ''];
        }

        $setting = OptionHelper::get('mall_setting', Yii::$app->mall->id);
        $days = (int)($setting['order_settle_day'] ?? 0);
        if ($days < 0) {
            $days = 0;
        }

        // 订单还没完成就没有 finish_at,算不出具体时间,只能把规则讲清楚
        if ((int)$order->status !== Order::STATUS_COMPLETED || (int)$order->finish_at <= 0) {
            return [
                'status' => 'pending_finish',
                'at'     => 0,
                'text'   => $days > 0 ? "订单完成 {$days} 天后结算" : '订单完成后结算',
            ];
        }

        $dueAt = (int)$order->finish_at + $days * 86400;
        if ($dueAt > time()) {
            return [
                'status' => 'waiting',
                'at'     => $dueAt,
                'text'   => '预计 ' . date('m-d H:i', $dueAt) . ' 结算',
            ];
        }
        // 已过点还没结上:cron 是定时跑的,这一轮没轮到很正常。
        // 说「等待系统结算」而不是甩个过去的时间,免得商家以为卡住了。
        return ['status' => 'due', 'at' => $dueAt, 'text' => '等待系统结算'];
    }
}