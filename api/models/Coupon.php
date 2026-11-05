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

use app\helpers\JobHelper;
use app\plugins\mch\models\Mch;
use Yii;
use yii\helpers\Json;

/**
 * This is the model class for table "{{%coupon}}".
 *
 * --- 核心字段 ---
 * @property int $id
 * @property int $mall_id
 * @property int $mch_id 商户ID, 0为平台
 * @property int $apply_to_mch 平台券是否可用于商家商品: 1=可(优惠平台买单), 0=仅自营(默认); 仅平台券有意义
 * @property string $name 优惠券名称
 * @property int $is_enabled 是否启用: 1=是, 0=否
 * @property int $type 0=满减券, 1=折扣券
 *
 * --- 规则 ---
 * @property float $min_price 最低消费金额门槛 (新)
 * @property float $sub_price 优惠金额 (用于满减券) (新)
 * @property float $discount 折扣率 (用于折扣券)
 * @property int $time_type 有效期类型: 0=领取后N天, 1=固定范围
 * @property int $day 有效天数 (当time_type=0时)
 * @property int|null $start_at 开始时间 (当time_type=1时)
 * @property int|null $end_at 结束时间 (当time_type=1时)
 * @property string $appoint_type 指定范围类型: all=全场/全店通用, goods=指定商品, cat=指定分类 (新)
 * @property string|null $appoint_relation 范围关联ID (JSON格式的商品或分类ID数组) (新)
 *
 * --- 发放 ---
 * @property int $total_count 发放总数 (-1为不限制) (新)
 * @property int $can_be_claimed 是否可在领券中心被用户领取: 1=是, 0=否 (新)
 * @property int $limit_per_user 每人限领张数 (0为不限制) (新)
 * @property int $is_transferable 是否可转赠
 * --- [兼容] 旧字段 ---
 * @property float $price [废弃] -> min_price
 * @property int $total [废弃] -> total_count
 * @property int $user_total [废弃] -> limit_per_user
 * @property int $is_goods_limit [废弃] -> appoint_type
 * @property int $is_expire
 *
 * @property int $is_delete
 * @property int|null $created_at, $updated_at, $deleted_at
 * @property string $created_time, $updated_time, $deleted_time
 * @property Mch $mch
 */
class Coupon extends BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%coupon}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'name', 'type'], 'required'],
            [['mall_id', 'mch_id', 'apply_to_mch', 'is_enabled', 'type', 'time_type', 'day', 'start_at', 'end_at', 'total_count', 'can_be_claimed', 'limit_per_user', 'is_delete', 'created_at', 'deleted_at', 'updated_at', 'is_expire'], 'integer'],
            [['min_price', 'sub_price', 'discount'], 'number'],
            [['name'], 'string', 'max' => 255],
            [['appoint_type'], 'string', 'max' => 50],
            [['appoint_type'], 'in', 'range' => ['all', 'goods', 'cat']], // 平台可能需要cat，所以保留
            [['appoint_relation'], 'string'],
            [['is_transferable'], 'integer'], // 加入验证
            [['is_transferable'], 'default', 'value' => 0],
            // 保留对旧字段的验证以确保兼容性
            [['price'], 'number'],
            [['total', 'user_total', 'is_goods_limit', 'user_total_limit', 'is_join', 'is_limit_total'], 'integer'],
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
            'is_transferable' => '是否允许转赠',
            'mch_id' => '商户ID, 0为平台',
            'name' => '优惠券名称',
            'is_enabled' => '是否启用',
            'type' => '类型(0=满减, 1=折扣)',
            'min_price' => '最低消费金额',
            'sub_price' => '优惠金额',
            'discount' => '折扣率',
            'time_type' => '有效期类型',
            'day' => '有效天数',
            'start_at' => '开始时间',
            'end_at' => '结束时间',
            'appoint_type' => '指定范围类型',
            'appoint_relation' => '指定范围关联ID',
            'total_count' => '发放总数',
            'can_be_claimed' => '是否可领取',
            'limit_per_user' => '每人限领',
        ];
    }

    /**
     * [兼容性处理] 魔术方法，当访问旧属性时，自动映射到新属性
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        $map = [
            'price' => 'min_price',
            'total' => 'total_count',
            'user_total' => 'limit_per_user',
        ];
        if (array_key_exists($name, $map)) {
            return $this->{$map[$name]};
        }
        if ($name === 'is_goods_limit') {
            return $this->appoint_type === 'all' ? 0 : 1;
        }
        return parent::__get($name);
    }

    /**
     * [兼容性处理] 魔术方法，当设置旧属性时，自动设置到新属性
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        $map = [
            'price' => 'min_price',
            'total' => 'total_count',
            'user_total' => 'limit_per_user',
        ];
        if (array_key_exists($name, $map)) {
            $this->{$map[$name]} = $value;
            return;
        }
        if ($name === 'is_goods_limit') {
            $this->appoint_type = $value ? 'goods' : 'all';
            return;
        }
        parent::__set($name, $value);
    }

    /**
     * [新增] 获取指定商品/分类的ID列表
     * @return array
     */
    public function getAppointRelationIds()
    {
        if ($this->appoint_relation) {
            try {
                $ids = Json::decode($this->appoint_relation);
                return is_array($ids) ? $ids : [];
            } catch (\Exception $e) {
                return [];
            }
        }
        return [];
    }

    public function beforeSave($insert)
    {
        if ($this->time_type == 1) {
            if ($this->end_at <= time()) {
                $this->is_expire = 1;
            }
        }
        return parent::beforeSave($insert);
    }

    public function getMch()
    {
        return $this->hasOne(Mch::class, ['id' => 'mch_id']);
    }
}