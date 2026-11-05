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

namespace app\models;

use app\interfaces\Payable;
use app\payments\BalancePayment;
use app\payments\FastPayAliJsPayment;
use app\payments\FastPayMpwxPayment;
use app\payments\FastPayWechatJsPayment;
use app\payments\MpwxPayment;
use app\payments\SystemPayment;
use app\plugins\mch\models\Mch;
use app\services\OrderService;
use Yii;

/**
 * This is the model class for table "{{%order}}".
 *
 * @property int $id 主键ID
 * @property int $mall_id 商城ID
 * @property int $user_id 下单用户ID
 * @property int $user_level_id 下单时用户会员等级ID(Level.id 快照)
 * @property int $user_level 下单时用户会员等级权重(level 快照)
 * @property int $referrer_id 推荐人快照:下单时买家的直属上级 user.id,0=无
 * @property string|null $order_no 订单号
 * @property int $mch_id 商户ID, 0为平台自营
 * @property string $type 订单类型 normal-普通订单 cashier-收银台订单
 * // --- 核心状态 ---
 * @property int $status 核心流程状态: 10=待付款, 20=待发货, 30=待收货, 40=已完成, 0=已关闭
 * @property int $settlement_status 结算状态: 0=待结算, 1=已结算, 2=无需结算/已失效
 * @property int $pay_status 支付状态: 0=未支付, 1=已支付, 2=部分退款, 3=全部退款
 * @property int $delivery_status 发货状态: 0=未发货, 1=部分发货, 2=全部发货
 * @property int $receipt_status 收货状态: 0=未收货, 1=已收货
 * @property int $refund_status 售后状态: 0=未售后, 10=申请售后,20=售后处理中, 30=售后完成,40=售后关闭
 * @property int $comment_status 评价状态: 0=未评价, 1=已评价
 *
 * // --- 金额相关 ---
 * @property float $total_price 订单总金额 (goods_total_price + express_price)
 * @property float $goods_total_price 商品真实总额 (优惠前,不含运费)
 * @property float $express_price 运费
 * @property int $express_income_mch_id 运费收入归属的实体ID, 0=平台, >0为商家或服务商ID
 * @property float $pay_price 用户实付金额
 * @property float $coupon_cut_price 优惠券抵扣金额 (订单级总计)
 * @property float $integral_cut_price 积分抵扣金额 (订单级总计)
 * @property float $first_order_discount_price 首单优惠金额 (订单级总计)
 * @property float $pickup_discount_price 自提优惠金额 (订单级总计)
 * @property float $platform_subsidy_cut_price 平台券补贴抵扣金额 (订单级总计)
 * @property float $platform_subsidy_random_minus 平台随机减免补贴金额
 * @property float $refund_price 已退款总金额
 *
 * // --- 优惠与支付方式 ---
 * @property int $is_use_integral 是否使用积分
 * @property float|null $use_integral 使用积分数量
 * @property int|null $user_coupon_id 用户优惠券ID
 * @property int $pay_type 支付方式: 0=微信, 1=余额, ...
 * @property string|null $payment_class 实际支付方式的类名
 * @property string|null $payment_name 实际支付方式的名称(用于展示)
 *
 * // --- 收货与配送信息 ---
 * @property string|null $name 收货人
 * @property string|null $mobile 收货人电话
 * @property string|null $address 收货地址
 * @property string|null $province_code 省份编码
 * @property string|null $city_code 城市编码
 * @property string|null $county_code 区县编码
 * @property string|null $town_code 乡镇编码
 * @property string $deliver_type 配送方式: express, pickup, city,cashier
 * @property string|null $pickup_code 自提核销码
 * @property string|null $city_delivery_info 同城配送信息(JSON)
 * @property int $is_immediately 是否立即配送: 1=是, 0=否(预约)
 * @property string|null $delivery_time 预约配送时间段
 * @property float|null $distance 配送距离(公里)
 * @property int|null $express_log_id 快递记录ID
 *
 * // --- 时间戳 ---
 * @property int|null $pay_at 支付时间
 * @property int|null $cancel_at 取消时间
 * @property int|null $send_at 发货时间
 * @property int|null $confirm_at 确认收货时间
 * @property int|null $finish_at 订单完成时间(履约完成,即确认收货)
 * @property int|null $settled_at 结算时间
 *
 * // --- 其他 ---
 * @property string|null $seller_remarks 商家备注
 * @property string|null $remarks 买家备注
 * @property string|null $extra 订单扩展信息(JSON):各插件存业务附加数据(如账单ID),系统用,不作为买家备注展示
 * @property int $clerk_id 核销员用户ID
 * @property string|null $clerk_name 核销员昵称快照
 * @property int $is_auto_complete 是否是自动完成订单
 * @property int $send_type 0=整单发货, 1=分包发货
 * @property int $is_delete 是否删除
 *
 * @property OrderDetail[] $orderDetails 关联的订单详情
 * @property OrderDetail $detail 仅仅只有一个详情的可以用这个
 * @property User $user 关联的用户
 * @property Mch $mch 关联的商户
 * @property int|mixed|null $is_express_settled
 * @property string|null $expected_arrival_time 预计送达时间
 * @property float|null $latitude
 * @property float|null $longitude
 * @property float|null $platform_income 平台收入
 * @property float|null $mch_income  商家收入
 * @property float|null $discount_price 会员折扣金额
 * @property float|null $member_discount_price 会员折扣金额
 * @property string $plugin 所属插件
 */
class Order extends BaseActiveRecord implements Payable
{
    const TYPE_NORMAL = 'normal';
    const TYPE_CASHIER = 'cashier';
    const TYPE_HOTEL = 'hotel';

    // --- 核心流程状态 ---
    const STATUS_CLOSED = 0;
    const STATUS_UNPAID = 10;
    const STATUS_TO_BE_SHIPPED = 20;
    const STATUS_SHIPPED = 30;
    const STATUS_COMPLETED = 40;

    // --- 支付状态 ---
    const PAY_STATUS_UNPAID = 0;
    const PAY_STATUS_PAID = 1;
    const PAY_STATUS_PARTIAL_REFUND = 2;
    const PAY_STATUS_REFUNDED = 3;

    // --- 发货状态 ---
    const DELIVERY_STATUS_UNSHIPPED = 0;
    const DELIVERY_STATUS_PARTIAL = 1;
    const DELIVERY_STATUS_SHIPPED = 2;

    // --- 收货状态 ---
    const RECEIPT_STATUS_UNRECEIVED = 0;
    const RECEIPT_STATUS_RECEIVED = 1;

    // --- 售后状态 ---
    const REFUND_STATUS_NO_REFUND = 0;
    const REFUND_STATUS_APPLIED = 10;
    const REFUND_STATUS_PROCESSING = 20;
    const REFUND_STATUS_COMPLETED = 30;

    const REFUND_STATUS_CANCEL = 40;

    // --- 评价状态 ---
    const COMMENT_STATUS_UNCOMMENTED = 0;
    const COMMENT_STATUS_COMMENTED = 1;

    // --- 结算状态 ---
    const SETTLEMENT_STATUS_PENDING = 0;   // 待结算
    const SETTLEMENT_STATUS_SETTLED = 1;   // 已结算
    const SETTLEMENT_STATUS_INVALID = 2;   // 无需结算/已失效

    // --- 事件常量定义 ---
    const EVENT_ORDER_PAID = 'order.paid';
    const EVENT_ORDER_CANCELED = 'order.canceled';
    const EVENT_ORDER_SHIPPED = 'order.shipped';
    const EVENT_ORDER_CONFIRMED = 'order.confirmed';
    const EVENT_ORDER_CREATED = 'order.created';
    const EVENT_ORDER_SETTLED = 'order.settled';
    const EVENT_ORDER_PACKAGE_SHIPPED = 'order.package.shipped';

    // --- 配送方式 ---
    const DELIVER_TYPE_CITY = 'city'; //同城
    const DELIVER_TYPE_EXPRESS = 'express'; //快递
    const DELIVER_TYPE_PICKUP = 'pickup'; //自提
    const DELIVER_TYPE_CASHIER = 'cashier'; //收银台
    const DELIVER_TYPE_NONE = 'none'; //无需物流/虚拟发货

    const SEND_TYPE_ALL = 0;
    const SEND_TYPE_SPLIT = 1;
    const SEND_TYPE_NONE = 2;
    const TYPE_INTEGRAL = 'integral';


    public static function tableName()
    {
        return '{{%order}}';
    }

    public static function generateOrderNo($user_id = null)
    {
        $datePart = date('ymd');
        $secondsPart = str_pad(time() - strtotime(date('Y-m-d')), 5, '0', STR_PAD_LEFT);
        $microtimePart = substr(microtime(), 2, 3);
        $randomPart = mt_rand(10, 99);
        return 'C' . $datePart . $secondsPart . $microtimePart . $randomPart;
    }

    public function rules()
    {
        return [
            [['mall_id', 'user_id'], 'required'],
            [['member_discount_price', 'platform_subsidy_cut_price'], 'number'],
            [['member_discount_price', 'platform_subsidy_cut_price'], 'default', 'value' => '0.00'],
            // 整数类型
            [
                [
                    'mall_id',
                    'user_id',
                    'mch_id',
                    'express_income_mch_id',
                    'is_express_settled',
                    'is_express_paid',
                    'status',
                    'settlement_status',
                    'pay_status',
                    'delivery_status',
                    'receipt_status',
                    'refund_status',
                    'comment_status',
                    'pay_type',
                    'send_type',
                    'user_coupon_id',
                    'is_use_integral',
                    'clerk_id',
                    'express_log_id',
                    'is_immediately',
                    'is_auto_complete',
                    'user_level_id',
                    'user_level',
                    'referrer_id',
                    'is_delete',

                ],
                'integer'
            ],
            // 时间戳
            [['pay_at', 'finish_at', 'send_at', 'confirm_at', 'cancel_at', 'settled_at'], 'integer'],
            // 金额/数字类型
            [
                [
                    'platform_income',
                    'mch_income',
                    'total_price',
                    'goods_total_price',
                    'express_price',
                    'pay_price',
                    'coupon_cut_price',
                    'platform_subsidy_cut_price',
                    'integral_cut_price',
                    'first_order_discount_price',
                    'pickup_discount_price',
                    'platform_subsidy_random_minus',
                    'refund_price',
                    'distance',
                    'use_integral',
                    'latitude',
                    'longitude'
                ],
                'number'
            ],
            [['type'], 'string', 'max' => 45],
            ['type', 'default', 'value' => self::TYPE_NORMAL],
            // 字符串类型
            [['order_no', 'name', 'payment_name', 'delivery_time', 'deliver_type', 'pickup_code'], 'string', 'max' => 45],
            [['mobile'], 'string', 'max' => 20],
            [['province_code', 'city_code', 'county_code', 'town_code'], 'safe'],
            [['address', 'remarks', 'clerk_name', 'payment_class', 'expected_arrival_time'], 'string', 'max' => 255],
            [['city_delivery_info'], 'string'],
            [['extra'], 'string'],
            [['seller_remarks'], 'string', 'max' => 255],
            [['seller_remarks'], 'trim'],
            [['plugin'], 'string', 'max' => 64],
            [['plugin'], 'default', 'value' => ''],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => '主键ID',
            'mall_id' => '商城ID',
            'user_id' => '下单用户ID',
            'order_no' => '订单号',
            'mch_id' => '商户ID',
            'status' => '核心流程状态',
            'settlement_status' => '结算状态',
            'pay_status' => '支付状态',
            'delivery_status' => '发货状态',
            'member_discount_price' => '会员折扣优惠金额',
            'receipt_status' => '收货状态',
            'refund_status' => '售后状态',
            'comment_status' => '评价状态',
            'total_price' => '总金额',
            'goods_total_price' => '商品总额',
            'express_price' => '运费',
            'express_income_mch_id' => '运费收入归属ID',
            'pay_price' => '支付金额',
            'coupon_cut_price' => '优惠券抵扣',
            'platform_subsidy_cut_price' => '平台券补贴',
            'integral_cut_price' => '积分抵扣',
            'first_order_discount_price' => '首单优惠',
            'pickup_discount_price' => '自提优惠',
            'platform_subsidy_random_minus' => '平台随机减免',
            'refund_price' => '退款金额',
            'name' => '收货人',
            'mobile' => '收货人电话',
            'address' => '收货地址',
            'remarks' => '买家备注',
            'clerk_id' => '核销员ID',
            'clerk_name' => '核销员',
            'deliver_type' => '配送方式',
            'pickup_code' => '自提核销码',
            'city_delivery_info' => '同城配送信息',
            'created_at' => '创建时间',
            'is_express_settled' => '是否结算运费',
            'is_express_paid' => '运费是否已打款(给商家)',
            'mch_income' => '商家收入',
            'platform_income' => '平台收入',
            'expected_arrival_time' => '预计送达时间',
            'settled_at' => '结算时间',
            'seller_remarks' => '商家备注',
            'plugin' => '所属插件'
        ];
    }


    public function isPaid()
    {
        return $this->pay_status >= self::PAY_STATUS_PAID;
    }

    public function isShipped()
    {
        return $this->delivery_status >= self::DELIVERY_STATUS_SHIPPED;
    }

    public function isReceived()
    {
        return $this->receipt_status === self::RECEIPT_STATUS_RECEIVED;
    }

    public function isSaleOut()
    {
        return $this->refund_status !== self::REFUND_STATUS_NO_REFUND;
    }

    public function isCompleted()
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isClosed()
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * 事务内给这张订单加行锁,并把锁内的最新数据装回本实例。先判断状态、再改状态的地方,判断前都要调它。
     *
     * save() 只按 id 更新、不带旧状态条件,而且每次都补写时间戳、永远写成功 —— 判断用的若是锁外读到的旧状态,
     * 核销 / 完成 / 补发货与取消、退款撞在一起时,会把刚关掉的单写回已完成、接着结算。
     * 锁定读才看得到别人刚提交的改动(可重复读下事务里的普通 SELECT、refresh() 读的是事务快照)。
     * 按主键锁:别在核销码之类的普通索引上 FOR UPDATE,那会连带锁住索引间隙,挡住别人下单。
     *
     * @return bool false = 订单行已不存在
     */
    public function lockForUpdate(): bool
    {
        $fresh = self::find()->where(['id' => $this->id])->forUpdate()->one();
        if (!$fresh) {
            return false;
        }
        foreach (array_keys($this->getRelatedRecords()) as $name) {
            unset($this->$name); // 与 refresh() 一致:丢掉已加载的关联,之后按锁内数据重新取
        }
        self::populateRecord($this, $fresh->getAttributes());
        $this->afterRefresh();
        return true;
    }

    /**
     * 读取订单扩展业务数据(JSON → 数组)。
     * 优先 extra(订单通用扩展位);回退 remarks(兼容历史:旧订单把业务 JSON 塞在买家备注里)。
     * 解析失败返回空数组。各插件给订单挂附加数据一律走 extra,禁止再塞 remarks(见 开发规范 §3.1)。
     */
    public function getExtraData(): array
    {
        $raw = '';
        if (!empty($this->extra)) {
            $raw = (string)$this->extra;
        } elseif (!empty($this->remarks)) {
            $raw = (string)$this->remarks;
        }
        if ($raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * 这张单的「下单时会员等级快照」(user_level_id / user_level)是否可信。
     *
     * 快照是 m260618_100000 迁移加的列,在它执行之前下的单两列都是 0 —— 分不清「当时没等级」还是「没快照」。
     * 所以:快照 > 0 一定可信;为 0 时只有迁移执行之后下的单才算(那就是当时确实没等级)。
     * 迁移执行时间从本环境的迁移记录表读,线上 / 测试服各按各的。
     * 用法:按下单时等级算钱的地方(级差起点等),可信就用 $order->user_level,否则回退当前等级。
     */
    public function hasLevelSnapshot(): bool
    {
        if ((int)$this->user_level_id > 0) {
            return true;
        }
        static $appliedAt = null;
        if ($appliedAt === null) {
            $appliedAt = (int)(\Yii::$app->db->createCommand(
                "SELECT apply_time FROM {{%migration}} WHERE version = 'm260618_100000_add_user_level_snapshot_to_order'"
            )->queryScalar() ?: 0);
        }
        return $appliedAt > 0 && (int)$this->created_at >= $appliedAt;
    }

    public function getOrderDetails()
    {
        return $this->hasMany(OrderDetail::class, ['order_id' => 'id']);
    }

    public function getMch()
    {
        return $this->hasOne(Mch::class, ['id' => 'mch_id']);
    }

    public function pay(CommonPayOrder $commonPayOrder): bool
    {
        Yii::warning('===================================');
        $orderService = new OrderService();
        $order = self::findOne(['id' => $commonPayOrder->order_id]);
        if (!$order) {
            throw new \Exception('订单不存在');
        }
        Yii::warning('==================================');
        return $orderService->pay($order, $commonPayOrder);
    }

    public function getExpressLog()
    {
        return $this->hasOne(ExpressLog::class, ['order_id' => 'id']);
    }

    public function getExpressList()
    {
        return $this->hasMany(ExpressLog::class, ['order_id' => 'id']);
    }

    /**
     * 获取状态文案
     * 逻辑说明：如果传入了 status，则使用传入的值（适用于列表循环中 new 空模型调用的场景）。
     * 如果未传入，则使用模型自身的 $this->status 属性。
     */
    public function getStatusText($status = null, $delivery_type = null)
    {
        $statusCode = $status === null ? $this->status : $status;
        $deliveryType = $delivery_type === null ? $this->deliver_type : $delivery_type;

        $map = [
            self::STATUS_CLOSED => '已关闭',
            self::STATUS_UNPAID => '待付款',
            self::STATUS_TO_BE_SHIPPED => '待发货',
            self::STATUS_SHIPPED => '待收货',
            self::STATUS_COMPLETED => '已完成',
        ];
        if (self::STATUS_TO_BE_SHIPPED == $statusCode) {
            if ($deliveryType == self::DELIVER_TYPE_PICKUP) {
                return '待核销';
            }
            if ($deliveryType == self::DELIVER_TYPE_CITY) {
                return '待配送';
            }
        }
        return $map[$statusCode] ?? '未知状态';
    }

    /**
     * 获取配送方式名称
     * 逻辑说明：优先使用传入参数，否则使用模型属性。
     */
    public function getDeliverTypeText($deliver_type = null)
    {
        $deliver_type = $deliver_type === null ? $this->deliver_type : $deliver_type;
        $map = [
            self::DELIVER_TYPE_EXPRESS => '快递配送',
            self::DELIVER_TYPE_PICKUP => '到店核销',
            self::DELIVER_TYPE_CITY => '同城配送',
            self::DELIVER_TYPE_CASHIER => '收银台',
            self::DELIVER_TYPE_NONE => '无需物流',
        ];
        return $map[$deliver_type] ?? '未知方式';
    }

    public function getCommonPayOrder()
    {
        return $this->hasOne(CommonPayOrder::class, ['order_no' => 'order_no']);
    }

    public function cancel(CommonPayOrder $commonPayOrder)
    {
        // 仅当业务订单仍为「待付款」才需要取消。已支付/已取消/已删 → 视为无需处理,返回 true。
        $order = self::findOne(['id' => $commonPayOrder->order_id, 'status' => Order::STATUS_UNPAID]);
        if (!$order) {
            return true;
        }
        // 传播业务取消的真实结果,供调用方(AutoCancelOrderTask)感知失败、暴露"支付单已关、业务单未取消"的悬挂态。
        $service = new OrderService();
        return $service->cancel($order);
    }

    public function getDetail()
    {
        return $this->hasOne(OrderDetail::class, ['order_id' => 'id']);
    }

    public static function paymentMap(): array
    {
        return [
            'wechat' => [
                'label' => '微信支付',
                'payments' => [
                    MpwxPayment::class,
                    FastPayMpwxPayment::class,
                    FastPayWechatJsPayment::class
                ]
            ],
            'alipay' => [
                'label' => '支付宝支付',
                'payments' => [
                    FastPayAliJsPayment::class
                ]
            ],

            'balance' => [
                'label' => '余额支付',
                'payments' => [
                    BalancePayment::class
                ]
            ],

            'system' => [
                'label' => '系统支付',
                'payments' => [
                    SystemPayment::class
                ]
            ],
        ];
    }
}
