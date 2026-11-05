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
use app\models\Goods;
use app\models\Order;
use app\models\User;
use app\plugins\mch\models\Mch;
use Yii;
use yii\db\Expression;
use yii\helpers\Json;

class AvailableMchListForm extends BaseModel
{
    public $goods_id;
    public $latitude;
    public $longitude;

    public function rules()
    {
        return [
            [['goods_id'], 'required'],
            [['goods_id'], 'integer'],
            [['latitude', 'longitude'], 'number'],
        ];
    }

    public function search()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $goods = Goods::findOne($this->goods_id);
        if (!$goods) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '商品不存在');
        }
        $is_sale_mch = 0;
        if ($goods->mch_id > 0) { //表示是商家的商品
            $mchIdsWithStock[] = $goods->mch_id;
            $is_sale_mch = 1;
        } else {
            // 1. 查询所有在售该平台商品的商家ID
            $mchIdsWithStock = Goods::find()
                ->select('mch_id')
                ->where([
                    'platform_goods_id' => $this->goods_id,
                    'is_delete' => 0,
                    'status' => 1,
                    'is_check' => 1,
                ])
                ->andWhere(['>', 'mch_id', 0])
                ->andWhere(['>', 'stock', 0])
                ->column();
        }
        if (empty($mchIdsWithStock)) {
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '暂无在售门店', ['list' => [], 'recommended' => null]);
        }


        $buyMchIds = Order::find()->where(['user_id' => UserHelper::getMyId(), 'is_delete' => 0])->select('mch_id')->distinct()->column();

        // 2. 查询这些商家的详细信息
        $query = Mch::find()
            ->with(['cat'])
            ->alias('m')
            ->where([
                'm.id' => $mchIdsWithStock,
                'm.is_delete' => 0,
                'm.status' => 1
            ]);

        // 3. 准备查询字段，如果提供了经纬度，则计算距离
        $selectFields = [
            'm.id',
            'm.cat_id',
            'm.mch_name',
            'm.logo',
            'm.address',
            'm.opening_hours',
            'm.latitude',
            'm.longitude',
            'delivery_options'
        ];

        if ($this->latitude && $this->longitude) {
            $distanceFormula = sprintf(
                "6371 * acos(cos(radians(%s)) * cos(radians(m.latitude)) * cos(radians(m.longitude) - radians(%s)) + sin(radians(%s)) * sin(radians(m.latitude)))",
                $this->latitude,
                $this->longitude,
                $this->latitude
            );
            $query->addSelect(array_merge($selectFields, [new Expression("{$distanceFormula} AS distance")]));
            $query->orderBy('distance ASC');
        } else {
            $query->addSelect($selectFields);
            $query->orderBy('m.id DESC');
        }

        $list = $query->asArray()->all();

        // 格式化距离文本
        foreach ($list as &$item) {
            if (isset($item['distance'])) {
                $item['distance_text'] = (float)$item['distance'] < 1
                    ? (round($item['distance'] * 1000) . 'm')
                    : (round($item['distance'], 1) . 'km');
            } else {
                $item['distance_text'] = '距离未知';
            }
        }
        unset($item);

        // 4. 确定推荐门店
        $recommendedMch = null;
        if (!Yii::$app->user->isGuest) {
            /** @var User $user */
            $user = Yii::$app->user->identity;

            // 优先查找用户绑定的门店
            if ($user->bind_mch_id) {
                foreach ($list as $mch) {
                    if ($mch['id'] == $user->bind_mch_id) {
                        $recommendedMch = $mch;
                        break;
                    }
                }
            }
        }
        foreach ($list as &$item) {
            $item['delivery_options'] = $item['delivery_options'] ? Json::decode($item['delivery_options']) : [];
        }
        unset($item);
        // 如果没有绑定门店，或者绑定的门店不卖这个商品，则推荐列表中的第一家（最近的或ID最大的）
        if (!$recommendedMch && !empty($list)) {
            $recommendedMch = $list[0];
            if (in_array($recommendedMch['id'], $buyMchIds)) {
                $recommendedMch['show_label'] = '买过';
            } else {
                $recommendedMch['show_label'] = '最近';
            }
        }


        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'list' => $list,
            'is_sale_mch' => $is_sale_mch,
            'recommended' => $recommendedMch
        ]);
    }
}
