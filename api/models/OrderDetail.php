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

use Yii;

/**
 * 这是订单详情模型类，对应 `order_detail` 数据表。
 * 每一条记录代表订单中的一个具体的商品项。
 *
 * @property int $id
 * @property int $mall_id 商城ID
 * @property int $user_id 用户ID
 * @property int $order_id 关联的主订单ID
 * @property string|null $order_no 订单号 (冗余字段，便于查询)
 * @property int $goods_id 商品ID
 * @property int|null $attr_id 规格ID
 * @property int $num 商品数量
 * @property float $price 商品快照单价 (下单时的价格)
 * @property string|null $attr 规格快照 (JSON格式)
 * @property int $status 状态: 0=正常, 1=有效(售后窗口期后), 2=无效(已退款)
 * @property int $is_can_refund 是否支持退款 (商品快照)
 *
 * // --- 财务快照字段 ---
 * // 这些字段记录了下单瞬间，经过所有优惠分摊后的精确金额，是财务计算和退款的基石。
 * @property float|null $total_price 商品真实总额 (price * num)
 * @property float|null $coupon_cut_price 分摊到此商品上的优惠券抵扣金额
 * @property float|null $pickup_discount_price 分摊到此商品上的自提优惠金额
 * @property float|null $platform_subsidy_cut_price 分摊到此商品上的平台补贴金额(已废弃，写0)
 * @property float|null $platform_subsidy_random_minus 分摊到此商品上的支付随机减(平台补贴)
 * @property float|null $first_order_discount_price 首单立减
 * @property float|null $integral_cut_price 分摊到此商品上的积分抵扣金额
 * @property float|null $pay_price 用户为此商品实际支付的金额 (total_price - 所有优惠)
 * @property float|null $concession_amount 商家为此商品承担的让利金额
 * @property float|null $mch_income_price 商家最终的商品货款收入 (total_price - 商家承担的成本)
 * @property int $is_mch_settled 商家货款是否已结算: 0=否, 1=是
 *
 *
 * // --- 其他关联字段 ---
 * @property int|null $user_coupon_id 用户优惠券ID (如果该商品参与了优惠券抵扣)
 * @property float $use_integral 分摊到此商品上的抵扣积分数量
 * @property int $is_send 是否已发货 (冗余字段，通常以主订单为准)
 * @property int $express_log_id 发货记录ID
 * @property int $is_send_integral 是否赠送积分 (商品快照)
 * @property float $send_integral 赠送积分数量
 * @property float|null $member_discount_price 会员折扣金额
 *
 * // --- 售后与状态 ---
 * @property int $refund_status 售后状态: 0=未售后, 10=售后中, 30=售后完成
 * @property int $is_delete 是否删除
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 *
 * @property Goods $goods 关联的商品对象
 * @property Order $order 关联的主订单对象
 * @property Attr $attrModel
 * @property mixed|string|null $mch_income_breakdown 商家货款收入计算明细(JSON)
 *
 * 商品快照(下单那一刻的商品信息,迁移 m261014_120000;新订单在 ProcessOrderDetailsStage 写入):
 * @property string $goods_name 商品名称快照
 * @property string $goods_pic 商品图片快照(规格图优先)
 * @property string $goods_no 商品编码快照(SKU 编码优先)
 * @property float $original_price 原售价快照:会员折扣 / 活动价之前的单价(price 是成交单价)
 * @property float $cost_price 成本价快照(规格成本优先)——⛔ 商家敏感,买家接口要用 scrubForCustomer() 去掉
 */
class OrderDetail extends BaseActiveRecord
{
    // 订单详情项的状态
    const STATUS_NORMAL = 0; // 正常状态，刚下单，在售后窗口期内
    const STATUS_VALID = 1;  // 有效状态，通常在售后窗口期结束后标记，可用于最终结算
    const STATUS_INVALID = 2;// 无效状态，通常在退款成功后标记，不再参与结算

    // 售后状态
    const REFUND_STATUS_NORMAL = 0;   // 未售后
    const REFUND_STATUS_APPLIED = 10;   // 售后申请中/处理中
    const REFUND_STATUS_PROCESSING = 20;
    const REFUND_STATUS_COMPLETE = 30;  // 售后已完成（无论是退款成功还是换货成功）
    const REFUND_STATUS_NO_REFUND = 0;


    public static function tableName()
    {
        return '{{%order_detail}}';
    }

    public function rules()
    {
        return [
            [['mall_id', 'order_id', 'user_id', 'price', 'goods_id'], 'required'],
            // 整数类型
            [
                [
                    'refund_status',
                    'mall_id', 'user_id', 'goods_id', 'order_id', 'attr_id',
                    'num', 'status', 'is_mch_settled',
                    'is_can_refund', 'is_send', 'express_log_id',
                    'is_send_integral', 'user_coupon_id',
                    'is_delete', 'created_at', 'updated_at', 'deleted_at'
                ],
                'integer'
            ],
            // 金额/数字类型
            [
                [
                    'price', 'total_price', 'pay_price',
                    'coupon_cut_price', 'pickup_discount_price', 'platform_subsidy_cut_price',
                    'platform_subsidy_random_minus',
                    'first_order_discount_price',
                    'integral_cut_price', 'use_integral',
                    'concession_amount', 'mch_income_price',
                    'send_integral'
                ],
                'number'
            ],
            [['member_discount_price'], 'number'],
            [['member_discount_price'], 'default', 'value' => '0.00'],
            // 字符串类型
            [['order_no'], 'string', 'max' => 45],
            [['attr'], 'string', 'max' => 512],
            [['mch_income_breakdown'], 'string']
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'mall_id' => '商城ID',
            'user_id' => '用户ID',
            'order_id' => '订单ID',
            'order_no' => '订单号',
            'goods_id' => '商品ID',
            'attr_id' => '规格ID',
            'refund_status' => '售后状态',
            'attr' => '规格快照',
            'num' => '数量',
            'price' => '商品快照单价',
            'total_price' => '商品真实总额',
            'coupon_cut_price' => '分摊的优惠券',
            'pickup_discount_price' => '分摊的自提优惠',
            'platform_subsidy_cut_price' => '分摊的平台补贴(已废弃)',
            'platform_subsidy_random_minus' => '分摊的支付随机减',
            'first_order_discount_price' => '首单立减',
            'integral_cut_price' => '分摊的积分抵扣',
            'pay_price' => '用户实付商品金额',
            'concession_amount' => '商家让利金额',
            'mch_income_price' => '商家商品货款',
            'is_mch_settled' => '商家货款已结算',
            'member_discount_price' => '会员折扣优惠金额',
            'status' => '状态',
            'is_can_refund' => '支持退款',
            'is_send' => '是否发货',
            'express_log_id' => '发货记录ID',
            'user_coupon_id' => '用户优惠券ID',
            'use_integral' => '使用积分',
            'is_send_integral' => '是否赠送积分',
            'send_integral' => '赠送积分',
            'is_delete' => '是否删除',
            'created_at' => '创建时间戳',
            'updated_at' => '更新时间',
            'deleted_at' => '删除时间',
            'mch_income_breakdown' => '商家货款收入计算明细(JSON)'
        ];
    }

    /**
     * 定义与商品表 (Goods) 的关联关系
     * @return \yii\db\ActiveQuery
     */
    /**
     * 这件商品能不能走通用售后 —— 认**下单时**的快照 is_can_refund,不认商品现在的「支持退款」开关。
     *
     * 原来读 goods->is_refund:用户买时能退、商家后来关掉,这单就退不了了(反过来也一样)。
     * 快照下单时就写好了:普通商品取当时的商品开关(BaseOrderHandler),插件订单按自己的规则写
     * (充值、缴费、卡券等写 0 —— 它们有自己的取消退款流程,不能再走通用售后多退一次)。
     * 快照为空的老数据才退回看商品开关。
     */
    public function canRefund(): bool
    {
        if ($this->is_can_refund !== null && $this->is_can_refund !== '') {
            return (int)$this->is_can_refund === 1;
        }
        return $this->goods && (int)$this->goods->is_refund === 1;
    }

    public function getGoods()
    {
        return $this->hasOne(Goods::class, ['id' => 'goods_id']);
    }

    /**
     * 定义与订单主表 (Order) 的关联关系
     * @return \yii\db\ActiveQuery
     */
    public function getOrder()
    {
        return $this->hasOne(Order::class, ['id' => 'order_id']);
    }

    public function getAttrModel()
    {
        return $this->hasOne(Attr::class, ['id' => 'attr_id']);
    }

    /**
     * 定义与积分换购商品表 (ExchangeGoods) 的关联关系
     * @return \yii\db\ActiveQuery
     */
    public function getExchangeGoods()
    {
        return $this->hasOne(\app\plugins\exchange\models\ExchangeGoods::class, ['goods_id' => 'goods_id']);
    }

    // =========================================================================
    // 商品快照:订单是交易凭证,显示的应该是下单那一刻的商品,而不是商品表现在的样子
    // =========================================================================

    /** 买家接口返回订单明细时要去掉的列:成本、商家货款 / 结算 / 让利这些商家内部数据 */
    const CUSTOMER_HIDDEN = ['cost_price', 'mch_income_price', 'mch_income_breakdown', 'settlement_price', 'concession_amount'];

    /**
     * 快照列建好没有。代码可能比迁移 m261014_120000 先上线,那时没有这些列,写 / 读都要跳过,不能让下单报错。
     * (迁移里用 getTableSchema(..., true) 刷新过表结构缓存,跑完迁移后这里会立刻变成 true)
     */
    public static function hasGoodsSnapshot(): bool
    {
        static $has = null;
        if ($has === null) {
            $schema = static::getTableSchema();
            $has = $schema !== null && $schema->getColumn('goods_name') !== null;
        }
        return $has;
    }

    /**
     * 下单时写商品快照。所有订单(含各插件)的明细都经 ProcessOrderDetailsStage 创建,在那里统一调用,
     * 各插件的 saveOrderDetail 不用改。用 updateAttributes:不触发事件、不重算任何金额。
     *
     * @param Goods     $goods 下单用的商品
     * @param Attr|null $attr  下单选的规格(没有规格传 null)
     */
    public function writeGoodsSnapshot(Goods $goods, $attr = null): void
    {
        if (!static::hasGoodsSnapshot() || !$this->id) {
            return;
        }
        $pic = ($attr && (string) $attr->cover_pic !== '') ? $attr->cover_pic : $goods->cover_pic;
        $no = ($attr && (string) $attr->no !== '') ? $attr->no : $goods->no;
        // 成本:规格配了(哪怕是 0)就用规格的,没配用商品的 —— 与 dealer MemberPriceService 同口径
        $cost = ($attr && $attr->cost_price !== null && $attr->cost_price !== '') ? $attr->cost_price : $goods->cost_price;
        $this->updateAttributes([
            'goods_name'     => mb_substr((string) $goods->name, 0, 255),
            'goods_pic'      => mb_substr((string) $pic, 0, 512),
            'goods_no'       => mb_substr((string) $no, 0, 255),
            // 原售价 = 规格售价 / 商品售价(会员折扣、活动价都在这之后),price 才是成交单价
            'original_price' => bcadd((string) ($attr ? $attr->price : $goods->price), '0', 2),
            'cost_price'     => bcadd((string) ($cost ?? 0), '0', 2),
        ]);
    }

    /**
     * 明细查询只 select 部分列时合进 select:有快照列就多取 goods_name / goods_pic,迁移前返回空数组
     * (这两列不能直接写进 select,迁移前会报列不存在)。取值:($row['goods_name'] ?? '') ?: 商品表的名称
     */
    public static function snapshotSelect(): array
    {
        return static::hasGoodsSnapshot() ? ['goods_name', 'goods_pic'] : [];
    }

    /** 显示用的商品名称:有快照用快照,老数据 / 迁移前回退商品表现在的名称 */
    public function snapshotName(): string
    {
        $name = static::hasGoodsSnapshot() ? (string) $this->goods_name : '';
        return $name !== '' ? $name : (string) ($this->goods->name ?? '');
    }

    /** 显示用的商品图片:同上 */
    public function snapshotPic(): string
    {
        $pic = static::hasGoodsSnapshot() ? (string) $this->goods_pic : '';
        return $pic !== '' ? $pic : (string) ($this->goods->cover_pic ?? '');
    }

    /**
     * asArray() 查出来的明细行:把快照盖到 $row['goods'] 的 name / cover_pic 上。
     * 前端一直读 item.goods.name / item.goods.cover_pic,这样各端不用改就显示下单时的商品;
     * 商品被删掉(goods 为空)也照样有名字和图。
     */
    public static function applySnapshotToRow(array &$row): void
    {
        $name = (string) ($row['goods_name'] ?? '');
        $pic = (string) ($row['goods_pic'] ?? '');
        if ($name === '' && $pic === '') {
            return;
        }
        if (!isset($row['goods']) || !is_array($row['goods'])) {
            $row['goods'] = ['id' => (int) ($row['goods_id'] ?? 0)];
        }
        if ($name !== '') {
            $row['goods']['name'] = $name;
        }
        if ($pic !== '') {
            $row['goods']['cover_pic'] = $pic;
        }
    }

    /** 买家接口返回明细整行前调用:去掉成本、商家货款等内部数据(见 CUSTOMER_HIDDEN) */
    public static function scrubForCustomer(array &$row): void
    {
        foreach (self::CUSTOMER_HIDDEN as $k) {
            unset($row[$k]);
        }
    }
}