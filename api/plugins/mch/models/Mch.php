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

namespace app\plugins\mch\models;

use app\models\BaseActiveRecord;
use app\models\Goods;
use app\models\User;
// ⚠ 不要在这里 `use app\plugins\hotel\models\Hotel;`
//   mch 是基础设施层，反向 use 业务插件会导致 hotel 被卸载时整个 mch 加载失败
//   下面 getHotel() 用 FQN + class_exists 守卫，使依赖关系仅在 hotel 已装时生效

/**
 * This is the model class for table "{{%plugin_mch}}".
 *
 * --- 核心字段 ---
 * @property int $id 商户ID
 * @property int $mall_id
 * @property int $user_id 店长的用户ID
 * @property string $mch_name 商户/门店名称
 * @property int $cat_id 商家分类ID
 * @property int $type 商家类型: 1=线上电商, 2=本地生活 3=酒店民宿  <--- [新增]
 * @property int $status 状态：0=待审核, 1=已通过, 2=已驳回, 3=已禁用
 * @property int $is_hide 是否隐藏
 * --- 基础信息 ---
 * @property string|null $logo 门店Logo
 * @property string|null $tel 联系电话 (座机)
 * @property string|null $mobile 联系手机号
 * @property string|null $address 详细地址
 * @property string|null $location_address 地理位置
 * @property int|null $province_code
 * @property int|null $city_code
 * @property int|null $county_code
 * @property int|null $town_code 省市区镇代码
 * @property float|null $latitude 纬度
 * @property float|null $longitude 经度
 * @property string|null $opening_hours 营业时间
 * @property string|null $banner_images Banner图 (JSON数组)
 * @property string|null $description 门店介绍
 * @property string|null $remarks 备注 (可用于存储驳回理由)
 *
 * @property string|null $refund_name 售后退货联系人     <-- [新增]
 * @property string|null $refund_mobile 售后退货联系电话   <-- [新增]
 * @property string|null $refund_address 售后退货详细地址  <-- [新增]
 *
 *
 * --- 资质信息 ---
 * @property string|null $business_license 营业执照图片
 * @property string|null $legal_person_name 法人姓名
 * @property string|null $id_card_front, $id_card_back 法人身份证正反面
 *
 * --- 分润与业务配置 ---
 * @property string|null $delivery_options 配送方式 (JSON数组)
 * @property string|null $city_delivery_config 同城配送完整配置(JSON)
 * @property float $pickup_discount_rate 自提优惠折扣(%)
 * @property float|null $pickup_discount_max_amount 自提优惠最大金额
 * @property float|null $city_delivery_distance 同城配送最大距离(km)
 * @property float $concession_rate 让利比例
 *
 * --- [核心] 评分相关字段 ---
 * @property float $rating 店铺综合评分(5分制)
 * @property float $good_rating_rate 店铺好评率(%)
 * @property int $rating_count 总评价数
 *
 * --- [新增] 首单优惠 ---
 * @property int $is_first_order_discount_enabled 是否开启首单优惠
 * @property float $first_order_discount_amount 首单优惠金额
 * @property int $first_order_discount_total_stock 总优惠桶数
 * @property string|null $first_order_discount_start_time 优惠开始时间
 * @property string|null $first_order_discount_end_time 优惠结束时间
 *
 * --- [新增] 微信智慧商圈 ---
 * @property string $wechat_mchid 微信支付商户号
 * @property string $wechat_shop_number 微信商圈门店编号
 *
 * ... (其他属性)
 * @property string $floor 楼层
 *
 * --- 通用字段 ---
 * @property int $is_delete
 * @property int|null $created_at, $updated_at, $deleted_at
 * @property string $created_time, $updated_time, $deleted_time
 * @property string $other_application_id 第三方平台应用ID
 * @property string $other_user_id 第三方平台用户ID
 * @property string $other_certs 其他证件图片
 * --- 关联关系 ---
 * @property User $user 店长关联的用户模型
 * @property MchCat $category 门店分类关联模型
 * @property MchCat $cat
 */
class Mch extends BaseActiveRecord
{

    const TYPE_UP_STORE = 1; // 线上电商
    const TYPE_DOWN_STORE = 2; // 本地生活
    /**
     * @deprecated 业务能力请通过"商户中心 → 业务能力开通"流程进入对应插件，
     *             mch 不再代表具体业务类型。此常量仅为兼容历史 type=3 数据保留，
     *             新流程下不应再写入此值（前端 apply.vue 已不暴露该选项，
     *             MchApplyForm 也已删除酒店专属保存逻辑）。
     */
    const TYPE_HOTEL = 3; // 酒店民宿
    const TYPE_INDIVIDUAL = 4; // 个人租户（商业地产场景）
    // 收款码进件状态常量已随收银台拆分移除；进件真实判据是 other_application_id / other_user_id。

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%plugin_mch}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'user_id', 'mch_name'], 'required'],
            // [修正] 将 'in' 验证器从属性数组中分离出来
            [['type'], 'in', 'range' => [self::TYPE_UP_STORE, self::TYPE_DOWN_STORE, self::TYPE_HOTEL, self::TYPE_INDIVIDUAL]],
            [['is_hide'], 'integer'],
            [['is_hide'], 'in', 'range' => [0, 1]],
            [['mall_id', 'user_id', 'cat_id', 'status', 'is_delete', 'created_at', 'updated_at', 'deleted_at'], 'integer'],
            [['province_code', 'city_code', 'county_code', 'town_code'], 'integer'],
            [['latitude', 'longitude', 'pickup_discount_rate', 'pickup_discount_max_amount', 'city_delivery_distance', 'concession_rate'], 'number'],
            [['banner_images', 'description'], 'string'],
            [['is_first_order_discount_enabled', 'first_order_discount_total_stock'], 'integer'],
            [['first_order_discount_amount'], 'number'],
            [['first_order_discount_amount', 'first_order_discount_total_stock'], 'compare', 'compareValue' => 0, 'operator' => '>='],
            [['created_time', 'updated_time', 'deleted_time', 'city_delivery_config', 'first_order_discount_start_time', 'first_order_discount_end_time'], 'safe'],
            [['mch_name', 'logo', 'tel', 'mobile', 'address', 'location_address', 'opening_hours', 'business_license', 'legal_person_name', 'id_card_front', 'id_card_back', 'remarks', 'delivery_options', 'wechat_mchid', 'wechat_shop_number', 'floor'], 'string', 'max' => 255],
            [['rating', 'good_rating_rate'], 'number'],
            [['rating_count'], 'integer'],
            [['refund_name', 'refund_mobile', 'other_application_id', 'other_user_id'], 'string', 'max' => 255],
            [['refund_address'], 'string', 'max' => 512],
            [['other_certs'], 'string'], // JSON 存为字符串
            [['other_certs'], 'default', 'value' => '[]'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '商户ID',
            'mall_id' => '商城ID',
            'user_id' => '店长用户ID',
            'mch_name' => '商户名称',
            'cat_id' => '门店分类',
            'status' => '状态',
            'logo' => '门店Logo',
            'tel' => '联系电话',
            'mobile' => '联系手机',
            'address' => '详细地址',
            'location_address' => '地理位置',
            'latitude' => '纬度',
            'longitude' => '经度',
            'opening_hours' => '营业时间',
            'banner_images' => 'Banner图',
            'description' => '门店介绍',
            'remarks' => '备注/驳回理由',
            'business_license' => '营业执照',
            'legal_person_name' => '法人姓名',
            'id_card_front' => '身份证正面',
            'id_card_back' => '身份证背面',

            'delivery_options' => '配送方式',
            'pickup_discount_rate' => '自提优惠折扣',
            'pickup_discount_max_amount' => '自提优惠最大金额',
            'city_delivery_distance' => '同城配送距离',
            'type' => '商家类型', // <--- [新增]
            'is_hide' => '是否隐藏',
            'is_delete' => 'Is Delete',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'deleted_at' => 'Deleted At',
            'created_time' => '创建时间',
            'updated_time' => '更新时间',
            'deleted_time' => '删除时间',
            'is_first_order_discount_enabled' => '首单优惠开关',
            'first_order_discount_amount' => '首单优惠金额',
            'first_order_discount_total_stock' => '总优惠桶数',
            // ======================= [核心新增] 开始 =======================
            'first_order_discount_start_time' => '优惠开始时间',
            'first_order_discount_end_time' => '优惠结束时间',

            'wechat_mchid' => '微信支付商户号(商圈)',
            'wechat_shop_number' => '微信智慧商圈门店编号',
            // ======================= [核心新增] 结束 =======================
            'rating' => '综合评分',
            'good_rating_rate' => '好评率',
            'rating_count' => '评价总数',
            'concession_rate' => '让利比例',

            // 【新增】为退货地址字段添加标签
            'refund_name' => '售后联系人',
            'refund_mobile' => '售后联系电话',
            'refund_address' => '售后退货地址',
            'other_user_id' => '用户ID',
            'other_application_id' => '应用ID',
            'other_certs' => '其他证件图',
            'floor' => '楼层',
        ];
    }

    /**
     * 定义与店长用户的一对一关联
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * 定义与门店分类的一对一关联
     * @return \yii\db\ActiveQuery
     */
    public function getCategory()
    {
        return $this->hasOne(MchCat::class, ['id' => 'cat_id']);
    }

    /**
     * 定义与热门商品的一对多关联关系 (只取前N个)
     * @return \yii\db\ActiveQuery
     */
    public function getHotGoods()
    {
        return $this->hasMany(Goods::class, ['mch_id' => 'id'])
            ->where(['is_delete' => 0, 'status' => 1])
            ->orderBy(['is_recommend' => SORT_DESC, 'sales' => SORT_DESC, 'id' => SORT_DESC])
            ->limit(5);
    }

    public function getCat()
    {
        return $this->hasOne(MchCat::class, ['id' => 'cat_id']);
    }

    /**
     * [新增] 获取酒店扩展信息
     * @return \yii\db\ActiveQuery
     */
    /**
     * hasOne: 一个商户对应一个酒店扩展信息
     *
     * 关键：用字符串类名 + class_exists 守卫，避免 hotel 插件被卸载后整个 mch
     * 模型加载失败。
     *
     * ⚠ 不能返回 null —— Yii AR 的 `with(['hotel'])` 内部走 `getRelation()`，
     *   会强制 `instanceof ActiveQueryInterface` 检查，返回 null 会抛
     *   InvalidArgumentException("Mch has no relation named 'hotel'")，导致
     *   普通商户列表也跟着挂（NearbyMchListForm 等地方无条件 with(['hotel'])）。
     *
     * 所以未安装 hotel 时返回一个永远空结果的安全查询：仍是 ActiveQuery，
     * SQL 执行但 `WHERE 0=1` 短路，性能开销可忽略。
     */
    public function getHotel()
    {
        $cls = '\app\plugins\hotel\models\Hotel';
        if (!class_exists($cls)) {
            return $this->hasOne(static::class, ['id' => 'id'])->andWhere('0=1');
        }
        return $this->hasOne($cls, ['mch_id' => 'id']);
    }
}
