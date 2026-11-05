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

/**
 * 支付商户单号历史 —— 记录一张支付单**发出去过的每一个** out_trade_no。
 *
 * 为什么需要它:商户单号每次发起支付都会重算并覆盖主表那一列(换支付方式/超时
 * 重来时多数网关不接受重复单号,是刻意设计)。但回调只认当前那一个,用户若在旧
 * 的预支付单上完成支付,回调带回的旧号就匹配不上任何订单 = 掉单。
 *
 * 有了本表,CommonPayOrder::findByTradeNo() 才能把旧号也认回来。
 * 写入统一走 CommonPayOrder::issueTradeNo(),调用方不要直接 new 本模型。
 *
 * @property int $id
 * @property int $mall_id
 * @property int $pay_order_id common_pay_order.id
 * @property string $order_no 系统单号(冗余,人工对账用)
 * @property string $out_trade_no 发给网关的商户单号(唯一)
 * @property string $payment 本次发起所用的支付类名
 * @property int $is_delete
 * @property int $created_at
 * @property int $updated_at
 */
class CommonPayOrderTradeNo extends BaseActiveRecord
{
    public static function tableName()
    {
        return '{{%common_pay_order_trade_no}}';
    }

    public function rules()
    {
        return [
            [['pay_order_id', 'out_trade_no'], 'required'],
            [['mall_id', 'pay_order_id', 'is_delete', 'created_at', 'updated_at'], 'integer'],
            [['order_no', 'out_trade_no'], 'string', 'max' => 64],
            [['payment'], 'string', 'max' => 120],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id'           => 'ID',
            'mall_id'      => '商城ID',
            'pay_order_id' => '支付单ID',
            'order_no'     => '系统单号',
            'out_trade_no' => '商户单号',
            'payment'      => '支付方式',
        ];
    }

    public function getPayOrder()
    {
        return $this->hasOne(CommonPayOrder::class, ['id' => 'pay_order_id']);
    }
}
