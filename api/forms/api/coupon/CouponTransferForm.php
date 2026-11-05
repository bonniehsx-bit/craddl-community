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

namespace app\forms\api\coupon;

use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\models\BaseModel;
use app\models\User;
use app\services\CouponService;
use Yii;

class CouponTransferForm extends BaseModel
{
    public $user_coupon_id;
    public $target_user_id; // 接收方ID，或者可以是手机号，这里先按ID处理

    public function rules()
    {
        return [
            [['user_coupon_id', 'target_user_id'], 'required'],
            [['user_coupon_id', 'target_user_id'], 'integer'],
        ];
    }

    public function transfer()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        try {
            $service = new CouponService();
            // 获取当前登录用户ID
            $currentUserId = Yii::$app->user->id;

            $service->transfer(
                $this->user_coupon_id,
                $currentUserId,
                $this->target_user_id
            );

            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '转赠成功');

        } catch (\Exception $e) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }

    /**
     * 辅助接口：搜索目标用户 (用于转赠前确认信息)
     * @param string $keyword 用户ID或手机号
     */
    public function searchTargetUser($keyword)
    {
        if (empty($keyword)) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '请输入搜索关键词');
        }

        $myId = Yii::$app->user->id;
        if ($keyword == $myId) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '不能搜索自己');
        }

        $user = User::find()
            ->where(['mall_id' => Yii::$app->mall->id, 'is_delete' => 0])
            ->andWhere(['or', ['id' => $keyword], ['mobile' => $keyword]])
            ->select(['id', 'nickname', 'avatar_url', 'mobile'])
            ->one();

        if (!$user) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '用户不存在');
        }

        // 隐藏手机号中间部分
        if ($user->mobile) {
            $user->mobile = substr_replace($user->mobile, '****', 3, 4);
        }

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '查找成功', [
            'user' => $user
        ]);
    }
}