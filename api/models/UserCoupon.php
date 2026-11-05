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

/**
 * This is the model class for table "{{%user_coupon}}".
 *
 * --- 核心关系 ---
 * @property int $id
 * @property int $mall_id
 * @property int $user_id
 * @property int $coupon_id 优惠券模板ID
 * @property int $mch_id 商户ID, 0为平台
 *
 * --- 状态与时间 ---
 * @property int $status 状态: 0=未使用, 1=已使用, 2=已过期
 * @property int|null $start_at 有效期开始时间
 * @property int|null $end_at 有效期结束时间
 * @property int $used_at 核销使用时间
 *
 * --- [核心] 规则完整冗余 ---
 * @property int $type 类型快照: 0=满减券, 1=折扣券
 * @property int $is_transferable 是否允许转赠
 * @property float $min_price 门槛金额快照
 * @property float $sub_price 优惠金额快照
 * @property float $discount 折扣率快照
 * @property string $appoint_type 指定范围类型快照
 * @property string|null $appoint_relation 范围关联ID快照 (JSON)
 *
 * --- 关联关系 ---
 * @property Coupon $coupon 关联的优惠券模板 (可能已被删除)
 * @property User $user 关联的用户
 */
class UserCoupon extends BaseActiveRecord
{
    const STATUS_UNUSE = 0;
    const STATUS_USED = 1;
    const STATUS_EXPIRE = 2;

    public static function tableName()
    {
        return '{{%user_coupon}}';
    }

    public function rules()
    {
        return [
            [['mall_id', 'user_id', 'coupon_id'], 'required'],
            [['mall_id', 'mch_id', 'user_id', 'coupon_id', 'type', 'is_goods_limit', 'start_at', 'end_at', 'status', 'used_at', 'updated_at', 'created_at', 'deleted_at', 'is_delete'], 'integer'],
            [['price', 'discount', 'min_price', 'sub_price'], 'number'],
            [['appoint_type'], 'string', 'max' => 50],
            [['is_transferable'], 'integer'], // 加入验证
            [['appoint_relation', 'remarks', 'code'], 'string'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => '用户ID',
            'coupon_id' => '优惠券模板ID',
            'status' => '状态',
            'start_at' => '有效期开始',
            'end_at' => '有效期结束',
            'min_price' => '使用门槛',
            'sub_price' => '优惠金额',
            'is_transferable' => '是否允许转赠',
        ];
    }

    /**
     * 定义与优惠券模板的关联关系。
     * 注意：由于模板可能被删除，使用此关联前最好做存在性检查。
     */
    public function getCoupon()
    {
        return $this->hasOne(Coupon::class, ['id' => 'coupon_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function beforeSave($insert)
    {
        // 在保存前，检查是否已过期
        if ($this->status == self::STATUS_UNUSE && $this->end_at > 0 && $this->end_at <= time()) {
            $this->status = self::STATUS_EXPIRE;
        }
        return parent::beforeSave($insert);
    }

    public function getMch()
    {
        return $this->hasOne(Mch::class, ['id' => 'mch_id']);
    }
}