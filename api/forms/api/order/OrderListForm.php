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
use app\models\Order;
use app\models\OrderDetail;
use yii\helpers\Json;

class OrderListForm extends BaseModel
{
    public $status;
    public $page;
    public $limit;

    public function rules()
    {
        return [
            [['page', 'limit', 'status'], 'integer'],
            [['page'], 'default', 'value' => 1],
            [['limit'], 'default', 'value' => 10],
            [['status'], 'default', 'value' => 0],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // 1. 基础查询
        $query = Order::find()->where([
            'is_delete' => 0,
            'user_id' => \Yii::$app->user->identity->id
        ]);

        // 2. 状态筛选
        switch ($this->status) {
            case 1:
                $query->andWhere(['status' => Order::STATUS_UNPAID]);
                break;
            case 2:
                $query->andWhere(['status' => Order::STATUS_TO_BE_SHIPPED]);
                break;
            case 3:
                $query->andWhere(['status' => Order::STATUS_SHIPPED]);
                break;
            case 4:
                $query->andWhere(['status' => Order::STATUS_COMPLETED, 'comment_status' => Order::COMMENT_STATUS_UNCOMMENTED]);
                break;
            case 5:
                // 「关闭」tab —— 就是已关闭的订单(取消/超时未付/后台关单)。
                //
                // 这里原先过滤的是 refund_status > 0(有退款),与 tab 名字对不上:
                // 关闭但没退过款的单(未付款超时自动关的占大头)refund_status 恒为 0,
                // 于是点「关闭」空空如也,点「全部」又能看到那些已关闭的单。
                // 反过来,还在途、只是发起了退款的单会被算进「关闭」里。
                $query->andWhere(['status' => Order::STATUS_CLOSED]);
                break;
        }

        // 2.5 类型过滤:后台「订单列表配置」只展示选中的订单类型
        //     included_types 为空 = 不限类型(向后兼容老商城/未配置场景)
        $cfg = \app\services\order\OrderListConfig::get((int)\Yii::$app->mall->id);
        if (!empty($cfg['included_types'])) {
            $plugins = \app\services\order\OrderTypeRegistry::pluginsForKeys($cfg['included_types']);
            if (!empty($plugins)) {
                $query->andWhere(['plugin' => $plugins]);
            }
        }

        // 3. 关联查询
        // 这里的 pagination 变量由 page() 方法通过引用赋值填充
        // ⛔ asArray() 查出来的关联整行原样给前端,不走模型 fields() 的隐藏字段 —— 关联一律只取前端要用的列:
        //    支付单整行里有下单时的网关配置快照(微信支付密钥、快付商户私钥)和 openid;
        //    商户整行里有证件照片、手机号、让利比例;商品整行里有成本价、采购价。都不能给顾客。
        $list = $query->with([
                'orderDetails',
                'orderDetails.goods' => fn($q) => $q->select(['id', 'name', 'cover_pic']),
                'mch' => fn($q) => $q->select(['id', 'mch_name', 'logo']),
                'commonPayOrder' => fn($q) => $q->select(['id', 'order_id', 'push_wechat_params']),
            ])
            ->page($pagination, $this->limit, $this->page)
            ->orderBy('id DESC')
            ->asArray()
            ->all();

        // [优化点] 实例化一个空模型作为“翻译器”
        $orderModel = new Order();

        // 退款进度:这一页订单各自的退款(说法见 RefundProgressService,不给顾客看失败原因),卡片上显示一句话
        $refunds = \app\services\RefundProgressService::forOrders(array_column($list, 'id'), (int)\Yii::$app->mall->id);

        // 4. 数据清洗
        foreach ($list as &$item) {
            // A. [核心] 修复状态文案获取
            // 必须传入 deliver_type，否则模型内部无法判断是“待发货”还是“待自提”
            $item['status_text'] = $orderModel->getStatusText($item['status'], $item['deliver_type'] ?? null);
            $item['deliver_type_text'] = $orderModel->getDeliverTypeText($item['deliver_type'] ?? '');

            // A2. 【新增】由后端 handler 提供列表卡片展示所需的按钮 + 状态文案覆盖
            //     复用详情页的 getMobileActions，但列表只展示一小部分按钮（去除 express/applyRefund 等详情型动作）
            try {
                $orderInstance = Order::findOne($item['id']);
                if ($orderInstance) {
                    $handler = \app\services\order\factory\OrderHandlerFactory::create($orderInstance->plugin, $orderInstance->type);
                    $actions = $handler->getMobileActions($orderInstance);
                    if (is_array($actions) && !empty($actions)) {
                        // 列表允许展示的按钮类型（精简版：只保留需要立即触达的）
                        $allowedInList = ['cancel', 'pay', 'confirm', 'comment', 'applyRefund', 'express', 'rebuy', 'rebook', 'pickup', 'dispute'];
                        $filtered = [];
                        foreach (($actions['actions'] ?? []) as $a) {
                            if (in_array($a['type'] ?? '', $allowedInList, true)) {
                                $filtered[] = $a;
                            }
                        }
                        $item['mobile_actions'] = [
                            'status_text' => $actions['status_text'] ?? $item['status_text'],
                            'status_desc' => $actions['status_desc'] ?? '',
                            'actions' => $filtered,
                        ];
                        // 用后端精细化的状态文案覆盖老版本
                        if (!empty($actions['status_text'])) {
                            $item['status_text'] = $actions['status_text'];
                        }
                    }
                }
            } catch (\Throwable $e) {
                \Yii::warning('[OrderListForm] mobile_actions for order ' . $item['id'] . ' failed: ' . $e->getMessage());
            }

            // B. 处理店铺
            $item['mch'] = $item['mch'] ?? ['id' => 0, 'mch_name' => '平台自营'];

            // C. 处理详情与循环引用清洗
            $item['items'] = $item['orderDetails'] ?? [];
            foreach ($item['items'] as &$detail) {
                // 显示下单那一刻的商品(快照),并去掉成本价 / 商家货款等内部数据 —— 明细整行给顾客
                OrderDetail::applySnapshotToRow($detail);
                OrderDetail::scrubForCustomer($detail);
                if (!empty($detail['attr'])) {
                    try {
                        $detail['attr'] = is_string($detail['attr']) ? Json::decode($detail['attr']) : $detail['attr'];
                    } catch (\Exception $e) {
                        $detail['attr'] = [];
                    }
                } else {
                    $detail['attr'] = [];
                }

                // [关键] 移除子表中的反向引用
                unset($detail['order']);

                // [兜底] 如果 goods 里面也有 order 关联，也建议移除
                if (isset($detail['goods']['order'])) {
                    unset($detail['goods']['order']);
                }
            }
            unset($detail); // 解除引用
            unset($item['orderDetails']); // 移除原数组

            // D. 支付单只给「确认收货」要的商户号和微信支付单号
            $item['commonPayOrder'] = self::payOrderForClient($item['commonPayOrder'] ?? null);

            // E. 退款进度一句话(点卡片进详情看每一笔)
            $item['refund_summary'] = \app\services\RefundProgressService::summary($refunds[(int)$item['id']] ?? []);
        }
        unset($item);

        // 5. [关键] 构造纯数组的分页数据，避免直接返回 $pagination 对象导致的递归
        $paginationData = [
            'total_count' => (int)$pagination->totalCount,
            'page_count' => (int)$pagination->pageCount,
            'pageSize' => (int)$pagination->pageSize,
            'current_page' => (int)$pagination->page + 1,
        ];

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'list' => $list,
            'pagination' => $paginationData // 替换原 $pagination 对象
        ]);
    }

    /**
     * 列表里给前端的支付单:只有微信小程序「确认收货」组件(order.vue openWechatConfirmView)要的商户号和微信支付单号。
     * push_wechat_params 是发货推送给微信的整份报文(含买家 openid),存的是二次编码的 JSON(JSON 列里放了 JSON 字符串),
     * 原样给前端时是一串字符串,前端按对象取 order_key 会报错 —— 这里解开后只挑这两个值。
     */
    private static function payOrderForClient(?array $payOrder): ?array
    {
        if (!$payOrder) {
            return null;
        }
        $params = $payOrder['push_wechat_params'] ?? null;
        for ($i = 0; $i < 2 && is_string($params); $i++) {
            $params = json_decode($params, true);
        }
        $key = is_array($params) ? ($params['order_key'] ?? []) : [];
        if (empty($key['mchid']) || empty($key['transaction_id'])) {
            return ['push_wechat_params' => null];
        }
        return ['push_wechat_params' => ['order_key' => [
            'mchid' => (string)$key['mchid'],
            'transaction_id' => (string)$key['transaction_id'],
        ]]];
    }
}