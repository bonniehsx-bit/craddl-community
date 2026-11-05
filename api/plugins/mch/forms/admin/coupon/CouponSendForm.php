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
use app\models\User;
use app\models\UserCoupon;
use Yii;

/**
 * Class CouponSendForm
 * @package app\plugins\mch\forms\admin
 * @desc 商家后台用于手动发放优惠券的表单
 */
class CouponSendForm extends BaseModel
{
    public $coupon_id;
    public $num_per_user;
    public $user_ids_str; // 接收前端的逗号分隔字符串

    public function rules()
    {
        return [
            [['coupon_id', 'num_per_user', 'user_ids_str'], 'required'],
            [['coupon_id', 'num_per_user'], 'integer', 'min' => 1],
            [['user_ids_str'], 'string'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'num_per_user' => '发放张数',
            'user_ids_str' => '发放对象',
        ];
    }

    public function send()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $currentMch = Yii::$app->mch;

        // 1. 解析和清洗用户ID
        $userIds = array_unique(array_filter(array_map('intval', explode(',', $this->user_ids_str))));
        if (empty($userIds)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '请输入有效的会员ID');
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // 2. 校验优惠券
            $coupon = Coupon::findOne([
                'id' => $this->coupon_id,
                'mch_id' => $currentMch->id, // [安全] 必须是本店的优惠券
                'is_delete' => 0
            ]);
            if (!$coupon) {
                throw new \Exception('优惠券不存在或无权操作');
            }
            if (!$coupon->is_enabled) {
                throw new \Exception('优惠券已关闭，无法发放');
            }

            // 3. 校验用户是否存在
            $existUserCount = User::find()->where(['id' => $userIds, 'is_delete' => 0])->count();
            if ($existUserCount != count($userIds)) {
                throw new \Exception('包含无效的会员ID，请检查');
            }

            // 4. [核心] 循环发放
            foreach ($userIds as $userId) {
                for ($i = 0; $i < $this->num_per_user; $i++) {
                    $userCoupon = new UserCoupon();
                    $userCoupon->mall_id = Yii::$app->mall->id;
                    $userCoupon->mch_id = $currentMch->id;
                    $userCoupon->user_id = $userId;
                    $userCoupon->coupon_id = $coupon->id;

                    // 规则快照
                    $userCoupon->type = $coupon->type;
                    $userCoupon->min_price = $coupon->min_price;
                    $userCoupon->sub_price = $coupon->sub_price;
                    $userCoupon->discount = $coupon->discount;
                    $userCoupon->appoint_type = $coupon->appoint_type;
                    $userCoupon->appoint_relation = $coupon->appoint_relation;

                    // 计算有效期
                    $userCoupon->start_at = time();
                    $userCoupon->end_at = strtotime("+{$coupon->day} day");

                    if (!$userCoupon->save()) {
                        throw new \Exception("为用户 {$userId} 发放优惠券失败");
                    }
                }
            }

            $transaction->commit();
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '发放成功');

        } catch (\Exception $e) {
            $transaction->rollBack();
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }
}