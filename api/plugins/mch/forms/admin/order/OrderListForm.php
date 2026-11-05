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
use app\models\BaseModel;
use app\models\Goods;
use app\models\Order;
use app\models\OrderDetail;
use yii\helpers\Json;

class OrderListForm extends BaseModel
{
    // 基础分页和状态
    public $page;
    public $limit;
    public $status;
    
    // 老版时间与关键字保持兼容
    public $date_range;
    public $keyword;
    public $keyword_type;
    
    // 新版平台化检索参数
    public $search_date;
    public $search_date_type;
    public $payment;
    public $deliver_type;
    public $type;
    public $plugin;
    public $mobile;
    public $buyer_mobile;
    public $order_no;
    public $user_id;

    public function rules()
    {
        return [
            [['page', 'limit', 'user_id'], 'integer'],
            [['page'], 'default', 'value' => 1],
            [['limit'], 'default', 'value' => 20],
            [['status', 'keyword', 'keyword_type', 'payment', 'deliver_type', 'type', 'plugin', 'mobile', 'buyer_mobile', 'order_no', 'search_date_type'], 'string'],
            [['keyword'], 'trim'],
            [['date_range', 'search_date'], 'safe'],
            [['search_date_type'], 'default', 'value' => 'created_at'],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        
        $currentMch = \Yii::$app->mch;

        $query = Order::find()->alias('o')
            // 顾客只给头像、昵称:asArray() 连表出来的是用户整行(含登录令牌、密码哈希),不走模型的隐藏字段
            ->with(['user' => fn($q) => $q->select(['id', 'nickname', 'avatar_url']), 'orderDetails.goods'])
            ->where([
                'o.is_delete' => 0,
                'o.mall_id' => \Yii::$app->mall->id,
                'o.mch_id' => $currentMch->id // [核心安全] 确保商家只能查询到自己的订单
            ]);
            
        // --- 1. 新增：支付方式筛选 ---
        $paymentMap = Order::paymentMap();
        if ($this->payment && isset($paymentMap[$this->payment])) {
            $query->andWhere(['o.payment_class' => $paymentMap[$this->payment]['payments']]);
        }

        // --- 2. 新增：买家手机号关联检索 ---
        if ($this->buyer_mobile) {
            $query->innerJoin(['u' => \app\models\User::tableName()], 'u.id = o.user_id');
            $query->andWhere(['like', 'u.mobile', $this->buyer_mobile]);
        }

        // --- 3. 基础维度筛选 ---
        $query->andFilterWhere(['o.user_id' => $this->user_id])
            ->andFilterWhere(['o.mobile' => $this->mobile])
            ->andFilterWhere(['o.deliver_type' => $this->deliver_type])
            ->andFilterWhere(['like', 'o.order_no', $this->order_no]);

        // --- 4. 彻底解绑 TYPE_NORMAL 限制，支持动态检索 ---
        if ($this->type !== null && $this->type !== '') {
            $query->andFilterWhere(['o.type' => $this->type]);
        }
        if ($this->plugin !== null && $this->plugin !== '') {
            $query->andFilterWhere(['o.plugin' => $this->plugin]);
        }

        // --- 5. 订单核心状态检索 ---
        if (isset($this->status) && $this->status !== '' && $this->status != -1) {
            // [关键调整] 保留旧版 refund 处理，兼顾前端历史标签
            if ($this->status === 'refund') {
                $query->andWhere([
                    'OR',
                    ['>', 'o.refund_status', 0],         // 条件一：所有售后订单
                    ['o.status' => Order::STATUS_CLOSED] // 条件二：所有已关闭订单
                ]);
            } else {
                $query->andWhere(['o.status' => $this->status]);
            }
        }

        // --- 6. 日期范围检索 ---
        // 兼容旧版 date_range
        if ($this->date_range && is_array($this->date_range) && count($this->date_range) == 2) {
            $startTime = strtotime($this->date_range[0] . ' 00:00:00');
            $endTime = strtotime($this->date_range[1] . ' 23:59:59');
            $query->andWhere(['between', 'o.created_at', $startTime, $endTime]);
        }
        // 支持新版多字段时间筛选
        if ($this->search_date && is_array($this->search_date) && count($this->search_date) == 2) {
            $begin_at = strtotime($this->search_date[0] . ' 00:00:00');
            $end_at = strtotime($this->search_date[1] . ' 23:59:59');

            // ⛔ 「支付」必须是 pay_at,口径同平台后台的 OrderListForm。以前前端「支付」传的是
            //    settled_at(结算时间):已付款还没结算的单一张都筛不出,超时关闭的单反而被筛进来。
            //    未知值会静默回退成下单时间,新增类型前后端要一起加。
            $dateFields = [
                'created_at' => 'o.created_at',
                'pay_at' => 'o.pay_at',
                'finish_at' => 'o.finish_at',
                'settled_at' => 'o.settled_at',
            ];
            $field = $dateFields[$this->search_date_type] ?? 'o.created_at';
            $query->andWhere(['between', $field, $begin_at, $end_at]);
        }

        // --- 7. 关键词快捷检索 ---
        if ($this->keyword && $this->keyword_type) {
            switch ($this->keyword_type) {
                case 'order_no':
                    $query->andWhere(['like', 'o.order_no', $this->keyword]);
                    break;
                case 'user_id':
                    $query->andWhere(['o.user_id' => (int)$this->keyword]);
                    break;
                case 'goods_name':
                    // 下单时的商品名(快照)和商品现在的名称都能搜到:改过名的商品新旧名都找得到,商品删了按快照也找得到
                    // ⛔ 用子查询不用 join:一单多件时 join 会让同一张订单重复出现,分页条数和顶部统计的 SUM 都会翻倍
                    $query->andWhere(['o.id' => OrderDetail::find()->alias('d')->select('d.order_id')
                        ->leftJoin(['g' => Goods::tableName()], 'g.id = d.goods_id')
                        ->where(['d.is_delete' => 0, 'd.mall_id' => \Yii::$app->mall->id])
                        ->andWhere(OrderDetail::hasGoodsSnapshot()
                            ? ['or', ['like', 'd.goods_name', $this->keyword], ['like', 'g.name', $this->keyword]]
                            : ['like', 'g.name', $this->keyword])]);
                    break;
            }
        }

        // --- 8. 【核心功能】聚合统计 ---
        // 两个收入只算已付款、没关闭的单(待发货/待收货/已完成):待付款的还没收到钱;
        // 已关闭的要么从没付过、要么已全额退款,关单不重算收入,字段里还留着下单时的预估值,
        // 加进来就是虚高。实付总额、各项优惠仍按当前筛选出的全部订单统计。
        $incomeScope = 'o.status IN (' . implode(',', [Order::STATUS_TO_BE_SHIPPED, Order::STATUS_SHIPPED, Order::STATUS_COMPLETED]) . ')';
        // 克隆查询对象进行金额核算（在 limit / offset 之前）
        $statsData = (clone $query)->select([
            'total_mch_income' => "COALESCE(SUM(CASE WHEN {$incomeScope} THEN o.mch_income ELSE 0 END), 0)",
            'total_platform_income' => "COALESCE(SUM(CASE WHEN {$incomeScope} THEN o.platform_income ELSE 0 END), 0)",
            'total_pay_price' => 'COALESCE(SUM(o.pay_price), 0)',
            'total_goods_price' => 'COALESCE(SUM(o.goods_total_price), 0)',
            'total_express_price' => 'COALESCE(SUM(o.express_price), 0)',
            'total_coupon_cut' => 'COALESCE(SUM(o.coupon_cut_price), 0)',
            'total_member_discount' => 'COALESCE(SUM(o.member_discount_price), 0)',
            'total_integral_cut' => 'COALESCE(SUM(o.integral_cut_price), 0)',
            'total_platform_subsidy_random_minus' => 'COALESCE(SUM(o.platform_subsidy_random_minus), 0)',
            'total_pickup_discount_price' => 'COALESCE(SUM(o.pickup_discount_price), 0)',
            'total_first_order_discount_price' => 'COALESCE(SUM(o.first_order_discount_price), 0)',
        ])->asArray()->one();

        $stats = [];
        if ($statsData) {
            foreach ($statsData as $k => $v) {
                $stats[$k] = number_format((float)$v, 2, '.', '');
            }
        }

        // --- 9. 数据分页及执行 ---
        $count = $query->count();
        $pagination = new \yii\data\Pagination(['totalCount' => $count, 'pageSize' => $this->limit, 'page' => $this->page - 1]);

        $list = $query->orderBy(['o.created_at' => SORT_DESC])
            ->limit($pagination->limit)
            ->offset($pagination->offset)
            ->asArray()
            ->all();

        // --- 10. 数据字典与时间格式化 ---
        $virtualOrder = new Order(); // [优化] 在循环外只实例化一次 AR 对象，极大幅度降低内存开销
        $handlerCache = []; // [优化] Handler 工厂单例缓存池

        foreach ($list as &$item) {
            // 对齐平台端标准的时间字段返回格式
            $item['created_time'] = isset($item['created_at']) ? date('Y-m-d H:i:s', $item['created_at']) : '';
            $item['settled_time'] = (isset($item['settled_at']) && $item['settled_at'] > 0)
                ? date('Y-m-d H:i:s', $item['settled_at']) : '';
            $item['finish_time'] = (isset($item['finish_at']) && $item['finish_at'] > 0)
                ? date('Y-m-d H:i:s', $item['finish_at']) : '';
                
            if (!empty($item['orderDetails'])) {
                foreach ($item['orderDetails'] as &$detail) {
                    OrderDetail::applySnapshotToRow($detail);   // 显示下单时的商品(快照),老数据回退商品表
                    if (!empty($detail['attr'])) {
                        $detail['attr'] = Json::decode($detail['attr']);
                    }
                }
            }
            
            // [新增] 动态追加 Handler 权限配置到列表页，供移动端列表的按钮控制使用
            $item['plugin_data'] = null;
            try {
                // 复用外部的单例 Order 实例
                $virtualOrder->attributes = (array)$item;
                $pluginCode = !empty($virtualOrder->plugin) ? $virtualOrder->plugin : 'normal';
                
                // 复用 Handler 实例缓存
                $cacheKey = $pluginCode . '_' . $virtualOrder->type;
                if (!isset($handlerCache[$cacheKey])) {
                    $handlerCache[$cacheKey] = \app\services\order\factory\OrderHandlerFactory::create($pluginCode, $virtualOrder->type);
                }
                
                $handler = $handlerCache[$cacheKey];
                $actionConfig = $handler->getActionConfig($virtualOrder);
                if (!empty($actionConfig)) {
                    $item['plugin_data'] = [
                        'type' => $virtualOrder->plugin,
                        'action_config' => $actionConfig
                    ];
                }
            } catch (\Exception $e) { }
        }
        unset($item);

        // 有售后还在处理的单:移动端列表的「取消 / 去发货 / 开始配送 / 去核销」按它收起,与后端拦截同一个判断(一次查完)
        $blocked = \app\services\OrderService::afterSaleBlockReasons($list);
        foreach ($list as &$item) {
            $item['after_sale_block_reason'] = $blocked[(int)$item['id']] ?? '';
        }
        unset($item);

        // 整理可选的支付方式列表
        $frontPaymentList = [];
        foreach ($paymentMap as $key => $val) {
            $frontPaymentList[] = [
                'label' => $val['label'],
                'value' => $key,
            ];
        }

        // 结算说明弹窗要用的两个时长。**必须从配置读、不能在前端写死** ——
        // 每个商城的自动确认天数与售后期都不一样,写死会让说明和实际对不上,
        // 而商家正是拿着这个说明来质疑"为什么还没结算"的。
        $mallSetting = \app\helpers\OptionHelper::get('mall_setting', (int) \Yii::$app->mall->id);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'list' => $list,
            'pagination' => $pagination,
            'stats' => $stats,
            'payment_list' => $frontPaymentList,
            'settle_help' => [
                'confirm_day' => (int) ($mallSetting['auto_confirm_day'] ?? 7),
                'settle_day'  => (int) ($mallSetting['order_settle_day'] ?? 0),
            ],
        ]);
    }
}