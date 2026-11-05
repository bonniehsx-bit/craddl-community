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
 * This is the model class for table "{{%common_pay_refund}}".
 *
 * @property int $id
 * @property int $mall_id 商城ID
 * @property int $user_id 用户ID
 * @property int $common_pay_order_id 关联的支付主单ID
 * @property string $order_no 商户订单号(冗余字段)
 * @property string $out_refund_no 商户退款单号(每次退款唯一)
 * @property string|null $out_trade_no 商户支付交易号
 * @property float $refund_price 本次退款金额
 * @property string $source_type 来源: cancel_order=取消订单, after_sales=售后
 * @property int $source_id 来源ID (如order_refund_id)
 * @property int $status 0=待处理, 1=处理中, 2=退款成功, 3=退款失败
 * @property string|null $error_msg 失败原因
 * @property int $is_delete 是否删除
 * @property int $created_at 创建时间
 * @property int $updated_at 更新时间
 * @property int $success_at 退款成功时间
 * @property int $retry_count 重试次数
 * @property int $next_retry_at 失败后下次自动重试的时间;0=不再自动重试(要人处理,见 RefundAttentionService)
 *
 * @property CommonPayOrder $commonPayOrder 关联的支付订单
 */
class CommonPayRefund extends BaseActiveRecord
{
    // --- 状态常量 ---
    const STATUS_PENDING = 0;    // 待处理
    const STATUS_PROCESSING = 1; // 处理中 (调用支付接口中)
    const STATUS_SUCCESS = 2;    // 退款成功
    const STATUS_FAIL = 3;       // 退款失败 (如余额不足、证书过期)

    // --- 来源常量 ---
    const SOURCE_CANCEL_ORDER = 'cancel_order'; // 订单取消/关闭
    const SOURCE_AFTER_SALES = 'after_sales';   // 售后退款
    const SOURCE_LUCKY_FAIL = 'lucky_fail'; // 手气拼团失败退款
    const SOURCE_YUDA_CANCEL = 'yuda_cancel'; // 遇搭同城流局退款
    const SOURCE_PAID_AFTER_CANCEL = 'paid_after_cancel'; // 订单已取消后才收到的支付,原路退回(source_id = 支付单 id)

    /** 来源的中文名(后台「退款流水记录」「退款失败」两页共用) */
    const SOURCE_TEXT = [
        self::SOURCE_CANCEL_ORDER => '订单取消',
        self::SOURCE_AFTER_SALES => '售后退款',
        self::SOURCE_LUCKY_FAIL => '手气拼团失败退款',
        self::SOURCE_YUDA_CANCEL => '遇搭流局退款',
        self::SOURCE_PAID_AFTER_CANCEL => '取消后到账退回',
    ];

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%common_pay_refund}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'user_id', 'common_pay_order_id', 'out_refund_no', 'refund_price'], 'required'],
            [['mall_id', 'user_id', 'common_pay_order_id', 'source_id', 'status', 'is_delete', 'created_at', 'updated_at', 'success_at', 'retry_count', 'next_retry_at'], 'integer'],
            [['refund_price'], 'number'],
            [['order_no', 'out_refund_no', 'out_trade_no'], 'string', 'max' => 64],
            [['source_type'], 'string', 'max' => 32],
            [['error_msg'], 'string', 'max' => 512],
            [['status'], 'default', 'value' => self::STATUS_PENDING],
            [['is_delete'], 'default', 'value' => 0],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'mall_id' => '商城ID',
            'user_id' => '用户ID',
            'common_pay_order_id' => '支付主单ID',
            'order_no' => '订单号',
            'out_trade_no' => '商户支付交易号',
            'out_refund_no' => '退款单号',
            'refund_price' => '退款金额',
            'source_type' => '来源类型',
            'source_id' => '来源ID',
            'status' => '状态',
            'error_msg' => '错误信息',
            'is_delete' => '是否删除',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
        ];
    }

    /**
     * 关联支付主表
     * @return \yii\db\ActiveQuery
     */
    public function getCommonPayOrder()
    {
        return $this->hasOne(CommonPayOrder::class, ['id' => 'common_pay_order_id']);
    }

    /**
     * 生成唯一的退款单号
     * 格式: R + 年月日时分秒 + 4位随机数
     * 例如: R202401301200001234
     * @return string
     */
    public static function generateRefundNo()
    {
        return 'R' . date('YmdHis') . mt_rand(1000, 9999);
    }
}
