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

use app\plugins\mch\models\Mch;
/**
 * This is the model class for table "{{%order_refund}}".
 *
 * @property int $id 售后单ID
 * @property int $mall_id 商城ID
 * @property int $user_id 申请售后-用户ID
 * @property int $order_id 关联的主订单ID
 * @property int $order_detail_id 关联的订单详情ID
 * @property int $goods_id 售后商品ID
 * @property string|null $pic_list 图片凭证(JSON格式)
 * @property string|null $content 用户详细说明(补充描述)
 * @property string|null $reason 用户退款原因(下拉选项) 【新增】
 * @property string|null $remark 商家拒绝原因/备注 【新增】
 * @property int $type 售后类型: 1=仅退款, 2=退货退款
 * @property int $status 售后状态
 * @property int $is_delete 是否删除
 * @property int|null $updated_at 更新时间戳
 * @property int|null $created_at 创建时间戳
 * @property int|null $deleted_at 删除时间戳
 * @property float $refund_price 申请退款金额
 * @property string|null $order_no 售后单号
 * @property int $is_refund 是否已打款: 0=否, 1=是
 * @property string|null $refund_time 实际退款时间 【新增】
 * @property int $mch_id 商户ID, 0为平台自营
 *
 * @property string|null $return_name 退货联系人
 * @property string|null $return_mobile 退货联系电话
 * @property string|null $return_address 退货地址详情
 * @property string|null $user_express 用户发货快递公司
 * @property string|null $user_express_no 用户发货快递单号
 * @property int|null $user_express_at 用户发货时间戳
 *
 * @property Order $order 关联的主订单模型
 * @property OrderDetail $orderDetail 关联的订单详情模型
 * @property User $user 关联的用户模型
 * @property Goods $goods 关联的商品模型
 * @property Mch $mch 关联的商家模型
 */
class OrderRefund extends BaseActiveRecord
{

    // --- 售后状态常量 ---
    const STATUS_PENDING = 0; // 待审核
    const STATUS_AGREED = 1; // 同意(处理中/待用户发货)
    const STATUS_REJECTED = 2; // 已拒绝
    const STATUS_COMPLETED = 3; // 已完成 (退款成功)
    const STATUS_CLOSED = 4; // 已关闭 (用户取消)
    const STATUS_USER_SHIPPED = 5; // 用户已发货
    const STATUS_PROCESSING = 6; // 商家已打款，退款处理中

    /**
     * 还在处理中的售后:这几种状态下,那件商品可能正在退。
     * 有这样的售后时不能发货、不能核销、已完成的单也先不结算(OrderService::afterSaleBlockReason);
     * 订单上的「售后中」标记也按它算(RefundService::updateOrderStatusAfterAction)。
     */
    const IN_PROGRESS_STATUS = [
        self::STATUS_PENDING,
        self::STATUS_AGREED,
        self::STATUS_USER_SHIPPED,
        self::STATUS_PROCESSING,
    ];

    /** 状态叫法,与后台售后列表的页签一致 */
    const STATUS_TEXT = [
        self::STATUS_PENDING => '待审核',
        self::STATUS_AGREED => '处理中',
        self::STATUS_REJECTED => '已拒绝',
        self::STATUS_COMPLETED => '已完成',
        self::STATUS_CLOSED => '已关闭',
        self::STATUS_USER_SHIPPED => '用户已发货',
        self::STATUS_PROCESSING => '退款中',
    ];

    /** 售后类型叫法(类型值见 RefundService::TYPE_*) */
    const TYPE_TEXT = [
        1 => '仅退款',
        2 => '退货退款',
        3 => '换货',
    ];

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%order_refund}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'user_id', 'order_id', 'order_detail_id', 'goods_id'], 'required'],
            [['mall_id', 'user_id', 'mch_id', 'order_id', 'order_detail_id', 'goods_id', 'type', 'status', 'is_delete', 'updated_at', 'created_at', 'deleted_at', 'is_refund', 'user_express_at'], 'integer'],
            [['refund_price'], 'number'],
            [['order_no'], 'string', 'max' => 45],
            [['pic_list'], 'string', 'max' => 2048],
            // 【修改】加入 reason, remark
            [['content', 'return_name', 'user_express', 'user_express_no', 'reason', 'remark'], 'string', 'max' => 255],
            [['return_address'], 'string', 'max' => 512],
            [['return_mobile'], 'string', 'max' => 32],
            // 【修改】加入 refund_time
            [['refund_time'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'mall_id' => 'Mall ID',
            'user_id' => 'User ID',
            'order_id' => 'Order ID',
            'order_detail_id' => 'Order Detail ID',
            'goods_id' => 'Goods ID',
            'pic_list' => '图片凭证',
            'content' => '补充描述',
            'reason' => '退款原因', // 【新增】
            'remark' => '商家备注', // 【新增】
            'type' => '售后类型',
            'status' => '售后状态',
            'is_delete' => 'Is Delete',
            'updated_at' => 'Updated At',
            'created_at' => 'Created At',
            'deleted_at' => 'Deleted At',
            'refund_price' => '退款金额',
            'order_no' => '售后单号',
            'is_refund' => '是否已打款',
            'refund_time' => '实际退款时间', // 【新增】
            'return_name' => '退货联系人',
            'return_mobile' => '退货联系电话',
            'return_address' => '退货地址详情',
            'user_express' => '用户发货快递公司',
            'user_express_no' => '用户发货快递单号',
            'user_express_at' => '用户发货时间',
        ];
    }
    /**
     * 生成售后订单号
     * @param int|null $user_id
     * @return string
     */
    public static function generateOrderNo($user_id = null)
    {
        $datePart = date('ymd');
        $secondsPart = str_pad(time() - strtotime(date('Y-m-d')), 5, '0', STR_PAD_LEFT);
        $microtimePart = substr(microtime(), 2, 3);
        $randomPart = mt_rand(10, 99);
        return 'RF' . $datePart . $secondsPart . $microtimePart . $randomPart;
    }

    /**
     * 关联主订单模型
     * @return \yii\db\ActiveQuery
     */
    public function getOrder()
    {
        return $this->hasOne(Order::class, ['id' => 'order_id']);
    }

    /**
     * 关联用户模型
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * 关联商品模型
     * @return \yii\db\ActiveQuery
     */
    public function getGoods()
    {
        return $this->hasOne(Goods::class, ['id' => 'goods_id']);
    }

    /**
     * 关联订单详情模型
     * @return \yii\db\ActiveQuery
     */
    public function getOrderDetail()
    {
        return $this->hasOne(OrderDetail::class, ['id' => 'order_detail_id']);
    }

    public function getMch()
    {
        return $this->hasOne(Mch::class, ['id' => 'mch_id']);
    }
}