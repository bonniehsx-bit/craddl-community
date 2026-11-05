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

namespace app\services;

use app\forms\api\order\OrderPreviewForm;
use app\forms\api\order\OrderSubmitForm;
use app\models\Admin;
use app\models\BaseModel;
use app\models\CommonPayOrder;
use app\models\Order;
use app\models\OrderRefund;
use app\models\User;
use app\services\order\actions\OrderCreator;
use app\services\order\factory\OrderHandlerFactory;
use app\services\order\actions\OrderPaymentService;
use app\services\order\actions\OrderSettlementService;
use app\services\order\actions\OrderShipmentService;
use Yii;
use yii\helpers\Json;

class OrderService extends BaseModel
{

    /**
     * 订单预览
     */
    public function preview(OrderPreviewForm $form)
    {
        try {
            // 解析 form_data
            $data = is_array($form->form_data) ? $form->form_data : Json::decode($form->form_data);
            if (!is_array($data))
                throw new \Exception('form_data 格式错误');

            // 自动匹配 Handler 并执行 preview
            $goodsList = $data['goods_list'] ?? [];
            $handler = OrderHandlerFactory::create($form->plugin, $form->type);

            // 可选：Handler 校验
            if (method_exists($handler, 'validateParams')) {
                $handler->validateParams($data);
            }

            $result = $handler->preview($data);

            // 插件扩展钩子：遍历所有已安装插件，收集需要注入下单页的 UI 扩展项
            // 每个插件根据商品内容自行判断是否返回扩展，与 $form->plugin（订单类型）无关
            $extensions = [];
            $mallId     = (int)Yii::$app->mall->id;
            $userId     = (int)Yii::$app->user->id;
            foreach (Yii::$app->plugin->getAllPlugins() as $pluginInstance) {
                $ext = $pluginInstance->getOrderExtensions($goodsList, $mallId, $userId);
                if (!empty($ext)) {
                    $extensions = array_merge($extensions, $ext);
                }
            }
            $result['plugin_extensions'] = $extensions;

            return $result;
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * 创建订单
     * 逻辑：委托给 OrderCreator
     */
    public function createOrder(OrderSubmitForm $form)
    {
        return (new OrderCreator(['form' => $form]))->create();
    }

    /**
     * 支付订单
     */
    public function pay(Order $order, CommonPayOrder $commonPayOrder): bool
    {
        $service = new OrderPaymentService();
        if (!$service->pay($order, $commonPayOrder)) {
            $this->addError('pay', $service->getErrorMsg() ?: '支付处理失败');
            return false;
        }
        return true;
    }

    /**
     * 发货 (通用)
     */
    public function ship(Order $order, int $sendType, array $detailIds = [], $name = '', $code = '', $no = '', $noExpressRemark = '')
    {
        try {
            (new OrderShipmentService())->ship($order, $sendType, $detailIds, $name, $code, $no, $noExpressRemark);
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * 商家自配送发货
     */
    public function shipByMerchantSelf(Order $order, string $expectedArrivalTime): bool
    {
        return (new OrderShipmentService())->shipByMerchantSelf($order, $expectedArrivalTime);
    }

    /**
     * 骑手接单
     */
    /**
     * 核销自提
     */
    // \$clerk:C 端核销员是 User,总后台强制核销是 Admin;二者都只用到 id/nickname
    public function verifyPickup(Order $order, User|Admin $clerk, $force = false): bool
    {
        $service = new OrderShipmentService();
        if (!$service->verifyPickup($order, $clerk, $force)) {
            // 取 service 自身的错误,**不能传 $order**:各 action service 失败时一律
            // $this->addError() 挂在自己身上,$order 此刻没有 errors,
            // getErrorMsg($order) 只会返回兜底「保存失败，发生未知错误。」——
            // 核销失败的真因(售后处理中 / 状态异常 / 已核销 / 订单不存在)全被这句吞掉,
            // 收银员只看到一句无从下手的提示。本文件 orderSettle 处早有同样的教训注释。
            $this->addError('verify', $service->getErrorMsg());
            return false;
        }
        return true;
    }

    /**
     * 用户确认收货
     */
    public function confirm(Order $order, $force = false)
    {
        $service = new OrderSettlementService();
        if (!$service->confirm($order, $force)) {
            $this->addError('confirm', $service->getErrorMsg());
            return false;
        }
        return true;
    }

    /**
     * 结算订单
     */
    public function orderSettle(Order $order, $force = false): bool
    {
        $service = new OrderSettlementService();
        if (!$service->orderSettle($order, $force)) {
            // 取 service 自身的错误(orderSettle 失败时把真实异常 addError 到 service 上);
            // 原来传 $order——而 order 此刻无 errors,getErrorMsg 只能返回兜底"保存失败，发生未知错误"，真因丢失。
            $this->addError('settle', $service->getErrorMsg());
            return false;
        }
        return true;
    }

    /**
     * 直接结算（确认收货 + 结算一步完成）
     * 适用于无需物流的场景：礼品券激活、卡密发放、核销入账等
     */
    public function directSettle(Order $order): bool
    {
        if (!$this->confirm($order, true)) {
            return false;
        }
        if (!$this->orderSettle($order, true)) {
            return false;
        }
        return true;
    }

    /**
     * 强制退款前的「退货在途」提醒文案,没有则返回空串。
     *
     * ============================================================
     * 为什么需要它
     * ============================================================
     * force=true 会绕过下面 cancel() 里的售后单防线(11 处插件调用都传 force:
     * 拼团失败 / 开奖失败 / 酒店拒单 / 门店取消 / yuda B单联动),整单退款照样执行。
     *
     * 而退款额度守卫只认 common_pay_refund 里的流水 —— 售后单如果还没打款,
     * 它就不占额度,**拦不住**。于是会出现:钱全退给用户了,而用户为退货
     * 寄出的包裹还在路上(实测见 refund-audit ⑨)。
     *
     * 这种单**故意不自动关闭** —— 关了商家收到包裹就查不到来历。
     * 但操作的人得当场知道这件事,否则等包裹到仓库才发现就晚了。
     *
     * @return string 空串 = 没有退货在途,可以放心强退
     */
    public static function pendingReturnNotice(int $orderId): string
    {
        try {
            $rows = OrderRefund::find()
                ->where(['order_id' => $orderId, 'is_delete' => 0])
                ->andWhere(['in', 'status', [
                    OrderRefund::STATUS_AGREED,        // 商家已给退货地址,用户可能已在寄
                    OrderRefund::STATUS_USER_SHIPPED,  // 用户已寄并填了单号
                ]])
                ->andWhere(['in', 'type', [
                    \app\services\RefundService::TYPE_RETURN_AND_REFUND,
                    \app\services\RefundService::TYPE_EXCHANGE,
                ]])
                ->all();
            if (!$rows) {
                return '';
            }
            $parts = [];
            foreach ($rows as $r) {
                $parts[] = $r->user_express_no
                    ? "{$r->user_express} {$r->user_express_no}"
                    : '用户尚未填写退货单号';
            }
            return '⚠ 该订单有 ' . count($rows) . ' 笔退货售后在途(' . implode('、', $parts)
                . '),强制退款后钱已退给用户,请自行处理退回的商品。售后单不会自动关闭,可在售后列表中查到。';
        } catch (\Throwable $e) {
            Yii::warning('[pendingReturnNotice] 检测失败 order=' . $orderId . ': ' . $e->getMessage());
            return '';
        }
    }

    /**
     * 取消订单
     */
    public function cancel(Order $order, $force = false, bool $unpaidOnly = false)
    {
        // 有售后还在处理时,禁止后台直接关闭订单:关单会自动退款,与还没结的售后叠加,还会把售后单卡死在「已同意」。
        // 售后请走售后流程(同意→确认打款)。售后处理完了就能关 —— 退过款的单关单只退剩下的(OrderSettlementService::cancel)。
        // force=true(系统/强制场景)可跳过此拦截。
        if (!$force) {
            $reason = self::afterSaleBlockReason($order);
            if ($reason !== '') {
                $this->addError('cancel', $reason);
                return false;
            }
        } else {
            // force=true 绕过了上面那道防线。退款额度守卫只认 common_pay_refund 里的流水,
            // 售后单没打款就不占额度、拦不住 —— 于是可能钱全退了而用户寄回的包裹还在路上。
            // 这种单**故意不自动关闭**(关了商家收到包裹查不到来历),但必须留下痕迹。
            $notice = self::pendingReturnNotice((int) $order->id);
            if ($notice !== '') {
                Yii::warning("[强制关单·退货在途] 订单 #{$order->id} {$notice}", 'order');
            }
        }

        $service = new OrderSettlementService();
        if (!$service->cancel($order, $force, $unpaidOnly)) {
            $this->addError('cancel', $service->getErrorMsg());
            return false;
        }
        return true;
    }

    /**
     * 有售后还在处理时,不能发货、不能核销、不能直接关单、已完成的单先不结算;能做返回空串。
     * 按整单算:发货是整单一起走的,先把售后处理完 —— 同意并退完,那件作废,剩下的照常发 / 结;拒绝了,整单照常。
     *
     * 「在处理」两边任一说是就算:订单上的售后标记(申请中 / 售后中,核销入口一直按它拦),
     * 或售后单本身(待审核 / 处理中 / 用户已发货 / 退款中)。两者由 RefundService 同步维护,
     * 都看是为了哪边没跟上也不放过去。部分退款后标记是「售后已完成」,不挡。
     *
     * 后台关单(cancel)、人工发货(OrderShipmentService::assertManualShipAllowed)、自提核销(verifyPickup)、后台强制结算、
     * 后台 / 商户端订单详情里这几个按钮的显隐,都按它判断;定时结算(OrderSettleTask::dueQuery)是同一口径的 SQL。
     *
     * @param Order|array $order 订单模型或详情接口里的订单数组(用到 id、refund_status)
     */
    public static function afterSaleBlockReason($order): string
    {
        return self::afterSaleBlockReasons([$order])[(int)($order['id'] ?? 0)] ?? '';
    }

    /**
     * 一批订单各自的 afterSaleBlockReason(列表用,售后单一次查完):[订单 id => 原因,能做的是空串]
     *
     * @param array $orders 订单模型或订单数组(用到 id、refund_status)
     */
    public static function afterSaleBlockReasons(array $orders): array
    {
        $ids = [];
        foreach ($orders as $o) {
            if ((int)($o['id'] ?? 0) > 0) {
                $ids[] = (int)$o['id'];
            }
        }
        $busyIds = $ids ? array_flip(array_map('intval', OrderRefund::find()->select('order_id')->distinct()
            ->where(['order_id' => $ids, 'is_delete' => 0, 'status' => OrderRefund::IN_PROGRESS_STATUS])
            ->column())) : [];
        $out = [];
        foreach ($orders as $o) {
            $id = (int)($o['id'] ?? 0);
            $busy = in_array((int)($o['refund_status'] ?? 0), [Order::REFUND_STATUS_APPLIED, Order::REFUND_STATUS_PROCESSING], true)
                || isset($busyIds[$id]);
            $out[$id] = $busy ? '该订单有售后正在处理,请先处理完售后' : '';
        }
        return $out;
    }

    /**
     * 删除订单
     */
    public function delete(Order $order)
    {
        $service = new OrderSettlementService();
        if (!$service->delete($order)) {
            $this->addError('delete', $service->getErrorMsg());
            return false;
        }
        return true;
    }
}