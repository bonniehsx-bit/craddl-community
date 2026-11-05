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
 * This is the model class for table "{{%order_goods_complete_log}}".
 *
 * @property int $id 主键ID
 * @property int|null $mall_id mall_id
 * @property float|null $pay_price 支付金额
 * @property float|null $total_price 商品总额
 * @property int|null $user_id 用户ID
 * @property int|null $goods_id 商品ID
 * @property int|null $num 数量
 * @property int|null $order_detail_id 订单详情ID
 * @property int|null $y 年
 * @property int|null $m 月
 * @property int|null $d 日
 * @property int|null $w 周
 * @property int|null $q 季度
 * @property int $is_delete 是否删除
 * @property int|null $created_at 创建时间戳
 * @property int|null $updated_at 更新时间
 * @property int|null $deleted_at 删除时间
 * @property string $created_time 创建时间
 * @property string|null $updated_time 更新时间
 * @property string|null $deleted_time 删除时间
 */
class OrderGoodsCompleteLog extends BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%order_goods_complete_log}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'user_id', 'goods_id', 'num', 'order_detail_id', 'y', 'm', 'd', 'w', 'q', 'is_delete', 'created_at', 'updated_at', 'deleted_at'], 'integer'],
            [['pay_price', 'total_price'], 'number'],
            [['created_time', 'updated_time', 'deleted_time'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键ID',
            'mall_id' => 'mall_id',
            'pay_price' => '支付金额',
            'total_price' => '商品总额',
            'user_id' => '用户ID',
            'goods_id' => '商品ID',
            'num' => '数量',
            'order_detail_id' => '订单详情ID',
            'y' => '年',
            'm' => '月',
            'd' => '日',
            'w' => '周',
            'q' => '季度',
            'is_delete' => '是否删除',
            'created_at' => '创建时间戳',
            'updated_at' => '更新时间',
            'deleted_at' => '删除时间',
            'created_time' => '创建时间',
            'updated_time' => '更新时间',
            'deleted_time' => '删除时间',
        ];
    }
}
