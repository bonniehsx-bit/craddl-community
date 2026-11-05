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

namespace app\plugins\mch\forms\api\index;

use app\core\ApiCode;
use app\helpers\UserHelper;
use app\models\BaseModel;
use app\models\Coupon;
use app\models\UserCoupon;
use app\plugins\mch\models\Mch;
use app\plugins\mch\models\MchFavorite;
use app\services\MiniProgramCodeService;
use Yii;
use yii\helpers\Json;
use yii\helpers\Url;

class MchInfoForm extends BaseModel
{
    public $id; // 接收 mch_id

    public $from_scan; //是否通过扫码;

    public function rules()
    {
        return [
            [['from_scan'], 'string'],
            [['id'], 'required'],
            [['id'], 'integer'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => '商户ID',
        ];
    }

    /**
     * 查询单个商户的详细信息 (用户端)
     * @return array
     */
    public function search()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        try {
            // [核心] 只查询已审核通过且未被删除的商户
            $mch = Mch::find()->where([
                'id' => $this->id,
                'mall_id' => \Yii::$app->mall->id,
                'is_delete' => 0,
                'status' => 1, // 1=已通过
            ])->asArray()->one();
            $mch['is_favorite'] = false;

            if (!$mch) {
                return $this->apiResponse(ApiCode::CODE_FAIL, '店铺不存在或已打烊');
            }
            if (!Yii::$app->user->isGuest) {
                $exist = MchFavorite::find()->where(['mch_id' => $this->id, 'user_id' => UserHelper::getMyId(), 'is_delete' => 0])->exists();
                $mch['is_favorite'] = $exist ? true : false;
            }
            // [核心] 数据格式化，以适配前端需求
            // 1. 解码 JSON 字段
            $mch['banner_images'] = isset($mch['banner_images']) ? Json::decode($mch['banner_images']) : [];
            $mch['delivery_options'] = isset($mch['delivery_options']) ? Json::decode($mch['delivery_options']) : [];

            $mch_user_id = $mch['user_id'];
            // 2. [可选] 清理掉不需要返回给用户端的敏感字段
            unset(
                $mch['user_id'], $mch['password'],
                $mch['legal_person_name'], $mch['id_card_front'], $mch['id_card_back'],
                $mch['is_delete'], $mch['deleted_at'], $mch['deleted_time']
            );
            // ======================= [核心新增] 开始 =======================
            // 2. 查询该商家的所有可领取优惠券
            $couponList = Coupon::find()->where([
                'mall_id' => \Yii::$app->mall->id,
                'mch_id' => $this->id, // [关键] 只查当前商家的券
                'is_delete' => 0,
                'is_enabled' => 1,
                'can_be_claimed' => 1,
            ])
                ->andWhere(['or', ['total_count' => -1], ['>', 'total_count', 0]]) // 数量无限或还有剩余
                ->orderBy('id DESC')
                ->asArray()
                ->all();

            // 3. 检查当前登录用户的领取状态 (如果用户已登录)
            if (!Yii::$app->user->isGuest) {
                $userId = Yii::$app->user->id;
                $couponIds = array_column($couponList, 'id');
                if (!empty($couponIds)) {
                    $userCoupons = UserCoupon::find()
                        ->where(['user_id' => $userId, 'coupon_id' => $couponIds, 'is_delete' => 0])
                        ->select('coupon_id, COUNT(id) as count')
                        ->groupBy('coupon_id')
                        ->asArray()
                        ->all();
                    $userCouponMap = array_column($userCoupons, 'count', 'coupon_id');

                    foreach ($couponList as &$item) {
                        $claimedCount = $userCouponMap[$item['id']] ?? 0;
                        $item['is_claimed'] = $claimedCount > 0;
                        $item['can_claim_more'] = true;

                        if ($item['limit_per_user'] > 0 && $claimedCount >= $item['limit_per_user']) {
                            $item['can_claim_more'] = false;
                        }
                        if ($item['total_count'] != -1) {
                            $sentCount = UserCoupon::find()->where(['coupon_id' => $item['id']])->count();
                            if ($sentCount >= $item['total_count']) {
                                $item['can_claim_more'] = false;
                            }
                        }
                    }
                }

                //用户已经登录
                if ($this->from_scan) {
                    $user = UserHelper::getUser();
                    // 只给还没有上级的人绑:-1 平台直属是锁定的(bindParent 也会再挡一次)。
                    // 店主 id 用上面留存的 $mch_user_id —— $mch['user_id'] 已经从返回数据里 unset 掉了,
                    // 原来读它直接报「Undefined array key」,未绑用户带 from_scan=1 打开店铺页就整页报错
                    if ((int)$user->parent_id === 0 && $mch_user_id && $mch_user_id != $user->id) {
                        $user->bind_mch_id = $this->id;
                        $user->bindParent((int)$mch_user_id);
                    }
                }
            } else {
                // 如果用户未登录，默认都可以领取
                foreach ($couponList as &$item) {
                    $item['is_claimed'] = false;
                    $item['can_claim_more'] = true;
                }
            }
            unset($item);
            // ======================= [核心新增] 结束 =======================
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
                'info' => $mch,
                'coupons' => $couponList // [新增] 将优惠券列表一并返回
            ]);
        } catch (\Exception $e) {
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }

    public function getQrcode()
    {

        // [核心] 只查询已审核通过且未被删除的商户
        $mch = Mch::find()->where([
            'id' => $this->id,
            'mall_id' => \Yii::$app->mall->id,
            'is_delete' => 0,
            'status' => 1, // 1=已通过
        ])->one();

        if (!$mch) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '店铺不存在或已打烊');
        }
        // --- 步骤 1: 获取小程序码的 URL ---
        try {
            $pagePath = 'plugins/mch/index'; // 店铺主页路径
            $scene = 'id=' . $mch->id . '&pid=' . $mch->user_id;
            $codeService = new MiniProgramCodeService();
            $qrCodeRelativeUrl = $codeService->getUnlimitedSceneCode($pagePath, $scene, $this->mall_id);
            $qrCodeFullUrl = Url::to($qrCodeRelativeUrl, true);
        } catch (\Exception $e) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '小程序码生成失败，请稍后重试');
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'qrcode' => $qrCodeFullUrl
        ]);
    }
}