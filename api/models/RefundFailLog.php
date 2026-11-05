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
 * This is the model class for table "{{%refund_fail_log}}".
 *
 * @property int $id
 * @property int|null $mall_id mall_id
 * @property string|null $refund_no 退款单号
 * @property string|null $order_no 订单号
 * @property float|null $pay_price 支付金额
 * @property float|null $refund_price 退款金额
 * @property string|null $remark 备注
 * @property int $status 0 待处理 1 已退款 2 退款失败
 * @property int $is_join_bank_pay 是否是汇聚银行支付
 * @property int $is_delete 是否删除
 * @property int|null $created_at 创建时间戳
 * @property int|null $updated_at 更新时间
 * @property int|null $deleted_at 删除时间
 * @property string $created_time 创建时间
 * @property string|null $updated_time 更新时间
 * @property string|null $deleted_time 删除时间
 */
class RefundFailLog extends BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%refund_fail_log}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id','is_join_bank_pay', 'is_delete', 'created_at', 'updated_at', 'deleted_at','status'], 'integer'],
            [['pay_price', 'refund_price'], 'number'],
            [['created_time', 'updated_time', 'deleted_time'], 'safe'],
            [['refund_no', 'order_no'], 'string', 'max' => 64],
            [['remark'], 'string', 'max' => 512],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'mall_id' => 'mall_id',
            'refund_no' => '退款单号',
            'order_no' => '订单号',
            'pay_price' => '支付金额',
            'refund_price' => '退款金额',
            'remark' => '备注',
            'status' => '0 待处理 1 已退款 2 退款失败',
            'is_join_bank_pay'=>'是否是汇聚银行支付',
            'is_delete' => '是否删除',
            'created_at' => '创建时间戳',
            'updated_at' => '更新时间',
            'deleted_at' => '删除时间',
            'created_time' => '创建时间',
            'updated_time' => '更新时间',
            'deleted_time' => '删除时间',
        ];
    }

    public static function saveLog($order_no, $refund_no, $pay_price, $refund_price, $remark, $mall_id = 1)
    {
        $log = new self();
        $log->order_no = $order_no;
        $log->pay_price = $pay_price;
        $log->refund_no = $refund_no;
        $log->refund_price = $refund_price;
        $log->remark = $remark;
        $log->mall_id = $mall_id;
        $log->status=0;

        $log->save();
    }
    public static function saveJoinBankLog($order_no, $refund_no, $pay_price, $refund_price, $remark, $mall_id = 1)
    {
        $log = new self();
        $log->order_no = $order_no;
        $log->pay_price = $pay_price;
        $log->refund_no = $refund_no;
        $log->refund_price = $refund_price;
        $log->remark = $remark;
        $log->mall_id = $mall_id;
        $log->status=0;
        $log->is_join_bank_pay=1;

        $log->save();
    }
}
