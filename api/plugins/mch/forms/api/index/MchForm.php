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
use app\helpers\ResponseHelper;
use app\helpers\UserHelper;
use app\models\BaseModel;
use app\models\Goods;
use app\plugins\mch\models\Mch;
use app\plugins\mch\models\MchFavorite;
use Yii;
use yii\helpers\Json;

class MchForm extends BaseModel
{

    public $latitude;
    public $longitude;

    public function rules()
    {
        return [

            [['latitude', 'longitude'], 'number'],
        ];
    }

    public function attributeLabels()
    {
        return [

            'latitude' => '纬度',
            'longitude' => '经度'
        ];
    }

    /**
     * 查询单个商户的详细信息 (用户端)
     * @return array
     */
    public function getRecommend()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        //
        try {
            //首先要找跟自己有关系的 否则找近一点

            $mch = $this->getMch();
        } catch (\Exception $e) {
            \Yii::warning($e->getMessage());
        }
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', ['mch' => $mch]);
    }

    private function getMch()
    {
        $favIds = [];
        $query = Mch::find()->alias('m')
            ->with([
                'category' => function ($query) {
                    /** @var \yii\db\ActiveQuery $query */
                    // 在 category 关联查询中，只选择 id 和 cat_name 两个字段
                    $query->select(['id', 'cat_name']);
                },
                // --- [核心优化] 直接预加载热门商品 ---
                'hotGoods' => function ($q) {
                    /** @var \yii\db\ActiveQuery $q */
                    // 在商品关联查询中，只选择需要的字段
                    $q->andWhere(['type' => Goods::TYPE_NORMAL]);
                    $q->select(['id', 'mch_id', 'name', 'cover_pic', 'price', 'origin_price', 'sales']);
                }
            ])
            ->where([
                'm.mall_id' => Yii::$app->mall->id,
                'm.is_delete' => 0,
                'm.status' => 1
            ]);
        if (!Yii::$app->user->isGuest) {
            $user = UserHelper::getUser();
            $favIds = MchFavorite::find()->andWhere(['user_id' => $user->id, 'is_delete' => 0])->select('mch_id')->column();
        }

        $selectFields = [
            'm.id',
            'm.mch_name',
            'm.logo',
            'm.address',
            'm.delivery_options',
            'm.rating',
            'm.cat_id', // 把 cat_id 选出来，用于后续处理
            'm.tel', 'm.mobile', 'm.opening_hours', 'm.location_address', 'm.latitude', 'm.longitude'
        ];

        if ($this->latitude && $this->longitude) {
            $distanceFormula = sprintf(
                "6371 * acos(cos(radians(%s)) * cos(radians(m.latitude)) * cos(radians(m.longitude) - radians(%s)) + sin(radians(%s)) * sin(radians(m.latitude)))",
                $this->latitude,
                $this->longitude,
                $this->latitude
            );
            $query->addSelect(array_merge($selectFields, ["{$distanceFormula} AS distance"]));
            $query->orderBy('distance ASC');
        } else {
            $query->addSelect($selectFields);
            $query->orderBy('m.id DESC');
        }

        $mch = $query->asArray()->one();
        if ($mch) {
            $mch['distance'] = (float)$mch['distance'] < 1
                ? (round($mch['distance'] * 1000) . 'm')
                : (round($mch['distance'], 1) . 'km');
            if ($mch['delivery_options']) {
                $mch['services'] = $this->formatServices(Json::decode($mch['delivery_options']));
            }
            if (in_array($mch['id'], $favIds)) {
                $mch['is_favorite'] = 1;
            } else {
                $mch['is_favorite'] = 0;
            }
        }
        return $mch;
    }

    // 辅助方法，将配送方式 code 格式化为前端需要的数据结构
    private function formatServices($services)
    {
        if (!is_array($services)) return [];
        $map = [
            'express' => ['icon' => 'transport', 'text' => '快递'],
            'pickup' => ['icon' => 'bag', 'text' => '到店自提'],
            'city' => ['icon' => 'home', 'text' => '同城配送'],
        ];
        $result = [];
        foreach ($services as $serviceCode) {
            if (isset($map[$serviceCode])) {
                $result[] = $map[$serviceCode];
            }
        }
        return $result;
    }
}
