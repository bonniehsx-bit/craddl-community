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

namespace app\plugins\mch\forms\admin\coupon;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Coupon;
use app\models\Goods;
use app\models\UserCoupon;
use Yii;
use yii\helpers\Json;

// 引入Goods模型用于查询商品信息

class CouponEditForm extends BaseModel
{
    public $id;

    // --- 对应 uni-app 前端 form 对象的所有字段 ---
    public $name;
    public $is_enabled;
    public $min_price;
    public $day; // 有效天数
    public $sub_price;
    public $appoint_type;
    public $can_be_claimed;
    public $limit_per_user;
    public $total_count;
    public $appoint_ids; // 前端传过来的指定商品ID数组

    public function rules()
    {
        return [
            [['name', 'day', 'sub_price'], 'required'],
            [['id', 'is_enabled', 'day', 'can_be_claimed', 'limit_per_user', 'total_count'], 'integer'],
            [['min_price', 'sub_price'], 'number', 'min' => 0],
            [['day'], 'integer', 'min' => 1, 'message' => '有效期天数必须大于0'],
            [['sub_price'], 'number', 'min' => 0.01, 'message' => '优惠金额必须大于0'],
            [['name'], 'string', 'max' => 255],
            // [核心] 商家只能创建 'all' 或 'goods' 类型的券
            [['appoint_type'], 'in', 'range' => ['all', 'goods']],
            [['appoint_ids'], 'safe'], // 接收数组
        ];
    }

    public function attributeLabels()
    {
        return [
            'name' => '优惠券名称',
            'day' => '有效期天数',
            'sub_price' => '优惠金额',
        ];
    }

    /**
     * 获取单张优惠券详情 (用于编辑)
     * @return array
     */
    public function getDetail()
    {
        if (!$this->id) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '缺少优惠券ID');
        }

        // [核心安全] 只能查询自己店铺(mch_id)下的优惠券
        $coupon = Coupon::findOne(['id' => $this->id, 'mch_id' => Yii::$app->mch->id, 'is_delete' => 0]);
        if (!$coupon) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '优惠券不存在或无权限查看');
        }

        $detail = $coupon->toArray();
        $appointIds = $coupon->getAppointRelationIds();
        $detail['appoint_ids'] = [];

        // 如果是指定商品，则查询商品信息一并返回给前端
        if ($coupon->appoint_type === 'goods' && !empty($appointIds)) {
            $detail['appoint_ids'] = Goods::find()->where(['id' => $appointIds, 'is_delete' => 0])
                ->select('id, name, cover_pic')
                ->asArray()
                ->all();
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', ['detail' => $detail]);
    }

    /**
     * 保存 (新建/编辑) 优惠券
     * @return array
     */
    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $currentMch = Yii::$app->mch;
        $coupon = null;

        if ($this->id) {
            // [核心安全] 只能编辑自己店铺的优惠券
            $coupon = Coupon::findOne(['id' => $this->id, 'mch_id' => $currentMch->id, 'is_delete' => 0]);
            if (!$coupon) {
                return $this->apiResponse(ApiCode::CODE_FAIL, '优惠券不存在或无权限修改');
            }
        } else {
            $coupon = new Coupon();
            $coupon->mall_id = Yii::$app->mall->id;
            $coupon->mch_id = $currentMch->id;
            // [核心业务规则] 商家只能创建满减券、领取后生效类型
            $coupon->type = 0;
            $coupon->time_type = 0;
        }

        // --- 数据赋值 ---
        $coupon->name = $this->name;
        $coupon->is_enabled = $this->is_enabled ? 1 : 0;
        $coupon->min_price = $this->min_price ?: 0.00;
        $coupon->day = $this->day;
        $coupon->sub_price = $this->sub_price;
        $coupon->appoint_type = $this->appoint_type;
        $coupon->appoint_relation = ($this->appoint_type == 'goods' && !empty($this->appoint_ids)) ? Json::encode($this->appoint_ids) : null;
        $coupon->can_be_claimed = $this->can_be_claimed ? 1 : 0;
        $coupon->limit_per_user = $this->limit_per_user >= 0 ? (int)$this->limit_per_user : 0; // 0代表不限制
        $coupon->total_count = ($this->total_count === '' || $this->total_count === null) ? -1 : (int)$this->total_count; // 空或null视为不限制

        if ($coupon->save()) {
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '保存成功');
        } else {
            return $this->responseErrorInfo($coupon);
        }
    }

    /**
     * 删除优惠券
     * @return array
     */
    public function delete()
    {
        if (!$this->id) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '缺少优惠券ID');
        }

        // [核心安全] 只能删除自己店铺的优惠券
        $coupon = Coupon::findOne(['id' => $this->id, 'mch_id' => Yii::$app->mch->id]);
        if (!$coupon) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '优惠券不存在或无权限删除');
        }

        $coupon->is_delete = 1;
        if ($coupon->save()) {
            // [可选] 软删除已发放的未使用券
            UserCoupon::updateAll(['is_delete' => 1], ['coupon_id' => $this->id]);
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '删除成功');
        } else {
            return $this->responseErrorInfo($coupon);
        }
    }
}