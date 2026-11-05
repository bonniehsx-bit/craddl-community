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

namespace app\services\order\actions;

use app\events\OrderEvent;
use app\services\order\OrderBehaviorRegistry;
use app\models\Admin;
use app\models\BaseModel;
use app\models\ExpressLog;
use app\models\Order;
use app\models\OrderDetail;
use app\models\User;
use Yii;

/**
 * 订单发货与履约服务类
 * 职责：负责订单的发货（快递、同城、自提）、骑手取货确认、核销等物流相关状态流转。
 */
class OrderShipmentService extends BaseModel
{
    /**
     * [私有] 事务内对订单行加排他锁并重载,实现与 cancel/confirm/orderSettle 的【跨方法互斥】。
     * 发货/收货方法与 OrderSettlementService 是兄弟类、无法共用其私有 lockOrderRow;但锁是锁在同一个 DB 订单行上,
     * 此处用与结算服务【完全相同】的 SELECT ... FOR UPDATE 即可与之互斥串行。必须在已开启的事务内调用。
     * @return bool true=已持锁并重载(后续 status 判定基于锁内权威值);false=订单不存在
     */
    private function lockOrderRow(Order $order): bool
    {
        // ⛔ 必须用「锁定读」拿锁内数据,不能 SELECT id FOR UPDATE 之后再 refresh():
        //    可重复读下外层事务里的普通 SELECT 读的是事务快照(如 AutoCancelOrderTask 先 findOne 过订单),
        //    看不见并发刚提交的支付(status 已是待发货)→ 把已付款的单当未付关掉、还不建退款。
        $fresh = Order::find()->where(['id' => $order->id])->forUpdate()->one();
        if (!$fresh) {
            return false;
        }
        foreach (array_keys($order->getRelatedRecords()) as $name) {
            unset($order->$name); // 与 refresh() 一致:丢掉已加载的关联,后面按锁内数据重新取
        }
        Order::populateRecord($order, $fresh->getAttributes());
        $order->afterRefresh();
        return true;
    }

    /**
     * [核心] 后台手动发货 (快递/无需物流)
     *
     * @param Order $order 要发货的订单对象
     * @param int $sendType 发货类型: 0=整单, 1=分包, 2=无需物流
     * @param array $detailIds 分包发货时，指定的订单详情ID列表
     * @param string $expressName 快递公司名称
     * @param string $expressCode 快递公司代码
     * @param string $expressNo 快递单号
     * @return void
     * @throws \Exception
     * @throws \Throwable
     */
    public function ship(Order $order, int $sendType, array $detailIds = [], string $expressName = '', string $expressCode = '', string $expressNo = '', string $noExpressRemark = '')
    {
        // --- 1. 前置校验 ---
        $this->assertShippable($order);

        $shipped_context = ['express_log_id' => null, 'order_detail_ids' => []];
        $isFirstTimeAllShipped = false;

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // 行锁 + 锁内复检:与 cancel/confirm/orderSettle 抢同一把订单行锁,防"发货 vs 并发取消"等跨方法竞态
            if (!$this->lockOrderRow($order)) {
                throw new \Exception('订单不存在');
            }
            if ($order->status != Order::STATUS_TO_BE_SHIPPED) {
                throw new \Exception('订单不是待发货状态!');
            }

            // --- 2. 根据发货类型，执行不同逻辑 ---

            // 要发的是这单里还没退的商品。部分退款后,已退的那件(明细作废)不再发:
            // 不标已发货、不进发货通知,分包发货算「发完没有」也不算它 —— 否则它永远发不出去,订单一直卡在待发货
            $shippable = ['and', ['order_id' => $order->id, 'is_delete' => 0], ['!=', 'status', OrderDetail::STATUS_INVALID]];

            // 场景一：无需物流
            if ($sendType == 2) {
                $order->send_type = 2;
                $order->delivery_status = Order::DELIVERY_STATUS_SHIPPED;
                $order->status = Order::STATUS_SHIPPED;
                $order->send_at = time();
                // 无需物流备注:独立列 no_express_remark(核心发货属性),与买家备注 remarks、插件 extra 均隔离
                if ($noExpressRemark !== '') {
                    $order->no_express_remark = $noExpressRemark;
                }
                if (!$order->save()) {
                    throw new \Exception($this->getErrorMsg($order));
                }
                $all_detail_ids = OrderDetail::find()->where($shippable)->select('id')->column();
                $shipped_context['order_detail_ids'] = $all_detail_ids;
                $isFirstTimeAllShipped = true;
            } else { // 场景二 & 三：需要物流
                if (empty($expressNo) || empty($expressName)) {
                    throw new \Exception('快递公司和快递单号不能为空');
                }

                // 创建物流记录
                $log = new ExpressLog();
                $log->mall_id = $order->mall_id;
                $log->express_name = $expressName;
                $log->express_no = $expressNo;
                $log->express_code = $expressCode;
                $log->name = $order->name;
                $log->mobile = $order->mobile;
                $log->address = $order->address;
                $log->order_id = $order->id;
                $log->order_no = $order->order_no;
                $log->is_all = ($sendType == 0) ? 1 : 0;
                if (!$log->save()) {
                    throw new \Exception($this->getErrorMsg($log));
                }
                $shipped_context['express_log_id'] = $log->id;

                // 整单发货
                if ($sendType == 0) {
                    $order->send_type = 0;
                    $order->express_log_id = $log->id;
                    $order->delivery_status = Order::DELIVERY_STATUS_SHIPPED;
                    $order->status = Order::STATUS_SHIPPED;
                    $order->send_at = time();
                    if (!$order->save()) {
                        throw new \Exception($this->getErrorMsg($order));
                    }
                    $all_detail_ids = OrderDetail::find()->where($shippable)->select('id')->column();
                    OrderDetail::updateAll(['is_send' => 1, 'express_log_id' => $log->id], ['id' => $all_detail_ids]);
                    $shipped_context['order_detail_ids'] = $all_detail_ids;
                    $isFirstTimeAllShipped = true;
                } // 分包发货
                else if ($sendType == 1) {
                    if (empty($detailIds)) {
                        throw new \Exception('请选择要发货的商品');
                    }
                    // 只认这单里还没退的商品:已退款的、别的订单的,一律不发
                    $detailIds = array_values(array_unique(array_map('intval', $detailIds)));
                    $validIds = array_map('intval', OrderDetail::find()->where($shippable)->andWhere(['id' => $detailIds])->select('id')->column());
                    if (count($validIds) !== count($detailIds)) {
                        throw new \Exception('所选商品里有已退款或不属于该订单的,请重新选择');
                    }
                    $order->send_type = 1;
                    OrderDetail::updateAll(['is_send' => 1, 'express_log_id' => $log->id], ['id' => $validIds]);

                    $shipped_context['order_detail_ids'] = $validIds;

                    $unshippedCount = OrderDetail::find()->where($shippable)->andWhere(['is_send' => 0])->count();

                    // 检查是否在这次分包发货中，刚好发完了所有商品
                    $oldDeliveryStatus = $order->delivery_status;
                    $order->delivery_status = ($unshippedCount == 0) ? Order::DELIVERY_STATUS_SHIPPED : Order::DELIVERY_STATUS_PARTIAL;

                    if ($order->delivery_status === Order::DELIVERY_STATUS_SHIPPED) {
                        $order->status = Order::STATUS_SHIPPED;
                        $order->send_at = time();
                        if ($oldDeliveryStatus !== Order::DELIVERY_STATUS_SHIPPED) {
                            $isFirstTimeAllShipped = true;
                        }
                    }
                    if (!$order->save()) {
                        throw new \Exception($this->getErrorMsg($order));
                    }
                }
            }

            // 【核心修复】必须先提交事务，再触发事件
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error("订单 #{$order->id} 发货失败: " . $e->getMessage());
            throw $e;
        }

        // --- 3. 事务安全结束后，触发副作用事件 ---
        try {
            // 触发“包裹已发出”事件，用于发送包裹通知（短信/模板消息）
            Yii::$app->trigger(Order::EVENT_ORDER_PACKAGE_SHIPPED, new OrderEvent([
                'order' => $order,
                'express_log_id' => $shipped_context['express_log_id'],
                'order_detail_ids' => $shipped_context['order_detail_ids']
            ]));

            // 触发“整单已发货”事件
            if ($isFirstTimeAllShipped) {
                Yii::$app->trigger(Order::EVENT_ORDER_SHIPPED, new OrderEvent(['order' => $order]));
            }
        } catch (\Throwable $e) {
            // 仅仅记录日志，不要因为发短信失败而抛出异常影响已经成功的数据库发货
            Yii::error("订单 #{$order->id} 发货后触发事件异常: " . $e->getMessage());
        }
    }

    /**
     * 发货后修改快递公司/单号(填错了单号的补救)
     *
     * 只允许:订单已发货(含分包发货中)、买家未收货、未关闭。收货后单号已经没有纠错意义,
     * 再改只会让售后/对账对不上。电子面单的单号是快递公司下发的,改了就和面单对不上,
     * 只能作废后重新打印。
     *
     * 不通知买家(用户定的);每次修改追加到 order.extra['express_changes'] 留痕。
     * 已推送过微信发货信息的单,提交后把新单号重新同步给微信,结果随返回值交给操作人。
     *
     * @param array $operator ['type' => 'admin'|'mch', 'id' => int, 'name' => string]
     * @return array ['wechat' => ['status' => skipped|synced|failed, 'message' => string]]
     * @throws \Exception
     */
    public function updateExpress(Order $order, int $expressLogId, string $expressName, string $expressCode, string $expressNo, array $operator): array
    {
        $expressName = trim($expressName);
        $expressCode = trim($expressCode);
        $expressNo = trim($expressNo);
        if ($expressName === '' || $expressNo === '') {
            throw new \Exception('快递公司和快递单号不能为空');
        }
        if (mb_strlen($expressNo) > 64) {
            throw new \Exception('快递单号过长');
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!$this->lockOrderRow($order)) {
                throw new \Exception('订单不存在');
            }
            if ((int)$order->status === Order::STATUS_CLOSED) {
                throw new \Exception('订单已关闭，不能修改物流');
            }
            if ((int)$order->receipt_status === Order::RECEIPT_STATUS_RECEIVED || (int)$order->status === Order::STATUS_COMPLETED) {
                throw new \Exception('买家已收货，不能再修改物流');
            }
            if (!in_array((int)$order->status, [Order::STATUS_TO_BE_SHIPPED, Order::STATUS_SHIPPED], true)) {
                throw new \Exception('订单当前状态不能修改物流');
            }

            $log = ExpressLog::find()->where([
                'id' => $expressLogId,
                'order_id' => $order->id,
                'mall_id' => $order->mall_id,
                'is_delete' => 0,
            ])->one();
            if (!$log) {
                throw new \Exception('物流记录不存在');
            }
            if ((int)$log->eorder_detail_id > 0) {
                throw new \Exception('电子面单生成的单号不能手动修改，请作废后重新打印');
            }

            $old = ['express_name' => (string)$log->express_name, 'express_code' => (string)$log->express_code, 'express_no' => (string)$log->express_no];
            $new = ['express_name' => $expressName, 'express_code' => $expressCode, 'express_no' => $expressNo];
            if ($old === $new) {
                throw new \Exception('物流信息没有变化');
            }

            $log->express_name = $expressName;
            $log->express_code = $expressCode;
            $log->express_no = $expressNo;
            if (!$log->save()) {
                throw new \Exception($this->getErrorMsg($log));
            }

            // 只解析 extra 本身:getExtraData() 会回退读 remarks,写回会把买家备注里的旧 JSON 搬进 extra
            $extra = json_decode((string)$order->extra, true);
            if (!is_array($extra)) {
                $extra = [];
            }
            $extra['express_changes'][] = [
                'time' => date('Y-m-d H:i:s'),
                'express_log_id' => (int)$log->id,
                'operator_type' => (string)($operator['type'] ?? ''),
                'operator_id' => (int)($operator['id'] ?? 0),
                'operator_name' => (string)($operator['name'] ?? ''),
                'old' => $old,
                'new' => $new,
            ];
            $order->extra = json_encode($extra, JSON_UNESCAPED_UNICODE);
            if (!$order->save(false, ['extra'])) {
                throw new \Exception('保存修改记录失败');
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // 定时推送给微信的是 $order->expressLog(按 order_id 取的一条),只有改的正是那条才需要重推;
        // 分包发货的其它包裹微信那边本来就没有
        $wechat = ['status' => 'skipped', 'message' => ''];
        $pushedLog = $order->expressLog;
        if ($pushedLog && (int)$pushedLog->id === (int)$log->id) {
            try {
                $wechat = (new \app\cron\PushRealFulfillmentTask())->repushExpress($order);
            } catch (\Throwable $e) {
                Yii::error("订单 #{$order->id} 改单号后同步微信异常: " . $e->getMessage());
                $wechat = ['status' => 'failed', 'message' => '同步微信失败：' . $e->getMessage()];
            }
        }
        return ['wechat' => $wechat];
    }

    /**
     * 通用发货前置校验,对**所有**调用方成立。
     *
     * ⚠ 这里**不**含「插件按单封印」。ship() 不只是后台发货按钮的后端,插件自己
     *   也在用它推进自家订单(艾灸技师扫码开始服务、xwy 云仓自动发货都是
     *   ship($order, 2) 无需物流式发货)。把只针对人工发货的封印放进来,
     *   会把插件自己的正常流程一起掐死 —— 那个封印在 assertManualShipAllowed()。
     *
     * 独立成 public 是给批量入口用的:Excel 导入要先把整表校验一遍再动手,
     * 否则发到一半才报错,前面的货已经出去了。
     *
     * @param Order $order
     * @throws \Exception
     */
    public function assertShippable(Order $order)
    {
        if ($order->status != Order::STATUS_TO_BE_SHIPPED) {
            throw new \Exception('订单不是待发货状态!');
        }
        if ($order->deliver_type == Order::DELIVER_TYPE_CITY) {
            throw new \Exception('同城配送订单不可后台操作');
        }
        // 专有业态订单禁止走基础物流体系发货。名单由各插件 Plugin::getOrderBehaviors() 的
        // block_ship 声明（当前 yuda / hotel），核心不再硬编码插件名。
        // 注：checkout 虚拟商品 deliver_type=NONE，本来就进不到这里，故未声明 block_ship。
        if (OrderBehaviorRegistry::blocksShip($order)) {
            throw new \Exception('特殊业务插件的订单，不可在基础物流体系中进行发货操作，请前往应用专有后台或使用专门设备处理！');
        }
    }

    /**
     * 人工发货入口的守门人。**后台/商家端每一个「发货」按钮的后端都必须先调它。**
     *
     * 为什么单独一个方法,而不是塞进 ship():
     *   ship() 是底层动作,插件自己也在调它推进自家订单(艾灸扫码开始服务、
     *   xwy 云仓自动发货)。而这里拦的是「人手动点发货」这件事 —— 艾灸插件
     *   把 show_ship 设成 false,原话就是「请勿走通用发货流程」,针对的是人,
     *   不是它自己那条腿。两者混在一起就会误伤。
     *
     * 为什么必须有:各插件早就在 getActionConfig() 里写好了这个判断,但过去
     *   只有后台订单详情抽屉读它 —— 等于**只是把按钮藏起来**。老订单详情页、
     *   Excel 批量导入发货、以及任何直接打 /mall/order/send 的调用全都绕得过去。
     *   对 lucky 来说,绕过去就是实打实的资损:货发了,而这个买家本轮没中奖,
     *   他照样会来要退款或佣金,平台两头亏。
     *
     * 默认放行(BaseOrderHandler 返回 show_ship = true),只有插件显式置 false
     * 才拦,对普通订单零影响。
     *
     * @param Order $order
     * @throws \Exception 不允许人工发货时抛出,消息可直接给用户看
     */
    public function assertManualShipAllowed(Order $order)
    {
        $shipGate = $this->checkPluginShipGate($order);
        if ($shipGate !== null) {
            throw new \Exception($shipGate);
        }
        // 有售后还在处理就不能发:那件东西可能正在退(待审核、已同意、退款中)。
        // 处理完了照常发 —— 部分退款后剩下的商品也能发(已退的那件发货时自动跳过,见 ship())
        $afterSale = \app\services\OrderService::afterSaleBlockReason($order);
        if ($afterSale !== '') {
            throw new \Exception($afterSale);
        }
    }

    /**
     * 问插件:这一单现在允许走基础物流发货吗?
     *
     * @param Order $order
     * @return string|null 不允许时返回给用户看的原因;允许返回 null
     */
    private function checkPluginShipGate(Order $order)
    {
        try {
            $handler = \app\services\order\factory\OrderHandlerFactory::create($order->plugin, $order->type);
            $config = $handler->getActionConfig($order);
        } catch (\Throwable $e) {
            // 插件 Handler 自己炸了不能连累发货 —— 那会让一个插件的 bug 变成全站发不了货。
            // 记日志放行,与 getActionConfig 在后台的既有容错口径一致。
            Yii::error("订单 #{$order->id} 发货前插件校验异常,已放行: " . $e->getMessage());
            return null;
        }

        if (!isset($config['show_ship']) || $config['show_ship'] !== false) {
            return null;
        }
        // custom_tip 是插件写给运营看的原话(例:「该买家本轮未中奖」),直接透出最有用
        $tip = trim((string)($config['custom_tip'] ?? ''));
        return $tip !== '' ? $tip : '当前订单业务状态不允许发货';
    }

    /**
     * [核心] 商家后台“自行配送”发货 (同城配送)
     * @param Order $order
     * @param string $expectedArrivalTime 商家填写的预计送达时间
     * @return bool
     * @throws \Throwable
     */
    public function shipByMerchantSelf(Order $order, string $expectedArrivalTime): bool
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            // 锁定订单
            $order = Order::find()->where(['id' => $order->id])->forUpdate()->one();

            if ($order->status !== Order::STATUS_TO_BE_SHIPPED) {
                throw new \Exception('订单状态不正确，无法发货');
            }

            $order->expected_arrival_time = $expectedArrivalTime;
            $order->status = Order::STATUS_SHIPPED;
            $order->delivery_status = Order::DELIVERY_STATUS_SHIPPED;
            $order->send_at = time();

            if (!$order->save()) {
                throw new \Exception($this->getErrorMsg($order));
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error("商家自配送发货失败，订单ID #{$order->id}: " . $e->getMessage());
            throw $e;
        }

        // 事务安全结束后触发事件
        try {
            Yii::$app->trigger(Order::EVENT_ORDER_SHIPPED, new OrderEvent(['order' => $order]));
        } catch (\Throwable $e) {
            Yii::error("商家自配送事件触发异常，订单ID #{$order->id}: " . $e->getMessage());
        }

        return true;
    }

    /**
     * [核心] 核销自提订单 (用户到店自提)
     * @param Order $order 要核销的订单对象
     * @param User $clerk 核销员的用户对象
     * @return bool
     * @throws \Exception
     */
    /**
     * 自提核销。
     *
     * $clerk 只用到 id / nickname 两个字段:C 端核销员是 User,总后台强制核销的操作人是 Admin,
     * 两者都满足。此前硬限定 User,导致总后台核销直接 TypeError(Admin given)。
     * order.clerk_id 仅作留痕(全仓无处 join 回 user 表),展示统一读 clerk_name。
     */
    public function verifyPickup(Order $order, User|Admin $clerk, $force = false): bool
    {
        // 退款拦截:订单有进行中的售后一律禁止核销,防「既退款又提货」资损。
        // 即便强制核销也不放行——须先处理完售后。与发货、结算、详情按钮同一个判断(OrderService::afterSaleBlockReason)
        $afterSale = \app\services\OrderService::afterSaleBlockReason($order);
        if ($afterSale !== '') {
            $this->addError('verify', $afterSale);
            return false;
        }
        if (!$force) {
            // 状态校验
            $allowedStatus = [Order::STATUS_TO_BE_SHIPPED, Order::STATUS_SHIPPED];
            if (!in_array($order->status, $allowedStatus) || ($order->deliver_type != 'pickup' && empty($order->plugin))) {
                $this->addError('verify', '订单状态异常(非待核销状态)，无法核销');
                return false;
            }
        }
        if ($order->status == Order::STATUS_COMPLETED || $order->status == Order::STATUS_SHIPPED && $order->receipt_status == Order::RECEIPT_STATUS_RECEIVED) {
            $this->addError('verify', '订单已核销');
            return false;
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            // 行锁 + 锁内复检:核销直达 COMPLETED 并极速结算,必须与 cancel/orderSettle 互斥;并防并发重复核销。
            // 复检失败 throw,由下方 catch 统一转 addError + return false(与事务外快路径口径一致)。
            if (!$this->lockOrderRow($order)) {
                throw new \Exception('订单不存在');
            }
            if (!$force && !in_array($order->status, [Order::STATUS_TO_BE_SHIPPED, Order::STATUS_SHIPPED])) {
                throw new \Exception('订单状态异常(非待核销状态)，无法核销');
            }
            if ($order->status == Order::STATUS_COMPLETED || ($order->status == Order::STATUS_SHIPPED && $order->receipt_status == Order::RECEIPT_STATUS_RECEIVED)) {
                throw new \Exception('订单已核销');
            }
            $order->status = Order::STATUS_COMPLETED;
            $order->receipt_status = Order::RECEIPT_STATUS_RECEIVED;
            $order->confirm_at = time();
            $order->finish_at = time();
            $order->clerk_id = $clerk->id;
            $order->clerk_name = $clerk->nickname;

            // 如果该订单直接被核销而没有发货记录，为了保证前端显示闭环，统一将其标记为已发货时间
            if (empty($order->send_at)) {
                $order->send_at = time();
                OrderDetail::updateAll(['is_send' => 1], ['order_id' => $order->id, 'is_delete' => 0]);
            }

            // 自提核销等同于确认收货，直接进入“待结算”状态
            $order->settlement_status = Order::SETTLEMENT_STATUS_PENDING;

            if (!$order->save()) {
                Yii::warning('OrderSaveError: ' . json_encode($order->getErrors(), JSON_UNESCAPED_UNICODE));
                throw new \Exception("订单属性保存失败，字段异常: " . json_encode($order->getErrors(), JSON_UNESCAPED_UNICODE));
            }

            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            $this->addError('verify', $e->getMessage());
            return false;
        }
        // --- 事务安全结束后触发事件 ---
        try {
            // 【核心修复】同步触发“确认收货”事件，防止分销/返佣体系遗漏自提订单
            Yii::$app->trigger(Order::EVENT_ORDER_CONFIRMED, new OrderEvent(['order' => $order]));

            // 【极速结算】核销订单立刻完成资金沉淀，不等待7天售后期
            $settlementService = new OrderSettlementService();
            $settlementService->orderSettle($order, true);
        } catch (\Exception $e) {
            Yii::error("核销自提事件触发及结算异常，订单ID #{$order->id}: " . $e->getMessage());
        }

        return true;
    }
}
