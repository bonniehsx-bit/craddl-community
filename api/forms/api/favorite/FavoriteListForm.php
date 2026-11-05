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

namespace app\forms\api\favorite;

use app\services\goods\StandaloneGoodsGuard;
use app\core\ApiCode;
use app\helpers\UserHelper;
use app\models\BaseModel;
use app\models\Goods;
use app\models\GoodsFavorite;
use app\plugins\mch\models\Mch;
use app\plugins\mch\models\MchFavorite;
use Yii;

class FavoriteListForm extends BaseModel
{
    public $page = 1;
    public $limit = 10;
    public $latitude;
    public $longitude;
    public $tab_type = 'goods'; // 默认获取商品收藏

    public function rules()
    {
        return [
            [['page', 'limit'], 'integer'],
            [['latitude', 'longitude'], 'number'],
            [['tab_type'], 'in', 'range' => ['goods', 'merchant']],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }


        $tab1_count = GoodsFavorite::find()->andWhere(['user_id' => UserHelper::getMyId(), 'is_delete' => 0])->count();
        $tab2_count = MchFavorite::find()->andWhere(['user_id' => UserHelper::getMyId(), 'is_delete' => 0])->select('mch_id')->distinct()->count();


        if ($this->tab_type === 'goods') {
            $res = $this->getGoodsList();
        } else {
            $res = $this->getMchList();
        }

        $res['data']['tabs'] = [
            ['name' => '商品收藏', 'count' => $tab1_count ?? 0],
            ['name' => '商家收藏', 'count' => $tab2_count ?? 0]
        ];


        return $res;
    }

    private function getGoodsList()
    {
        $query = GoodsFavorite::find()->alias('gf')->where([
            'gf.user_id' => Yii::$app->user->id,
            'gf.is_delete' => 0,
        ])->leftJoin(['g' => Goods::tableName()], 'gf.goods_id = g.id');
        // 插件专属商品不进收藏列表(listCondition 已放行 g.type IS NULL,左连接查不到商品的旧收藏照常显示)
        if ($c = StandaloneGoodsGuard::listCondition('g.type')) {
            $query->andWhere($c);
        }

        $count = $query->count();

        $list = $query->select([
            'g.id',
            'favorite_id' => 'gf.id',
            'name' => 'g.name',
            'image_url' => 'g.cover_pic',
            'price_current' => 'g.price',
            'price_original' => 'g.origin_price',
        ])
            ->orderBy(['gf.id' => SORT_DESC])
            ->page($pagination, $this->limit, $this->page)
            ->asArray()
            ->all();

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'list' => $list,
            'total' => $count,
        ]);
    }

    private function getMchList()
    {
        $query = MchFavorite::find()->alias('mf')->where([
            'mf.user_id' => Yii::$app->user->id,
            'mf.is_delete' => 0,
        ])->leftJoin(['m' => Mch::tableName()], 'mf.mch_id = m.id');


        $count = $query->count();
        $selectFields = [
            'm.id',
            'favorite_id' => 'mf.id',
            'name' => 'm.mch_name',
            'logo_url' => 'm.logo',
            'rating' => 'm.rating',
            'address' => 'm.address'
        ];
        $query->select($selectFields);
        if ($this->latitude && $this->longitude) {
            $distanceFormula = sprintf(
                "6371 * acos(cos(radians(%s)) * cos(radians(m.latitude)) * cos(radians(m.longitude) - radians(%s)) + sin(radians(%s)) * sin(radians(m.latitude)))",
                $this->latitude, $this->longitude, $this->latitude
            );
            $query->addSelect(array_merge($selectFields, ["{$distanceFormula} AS distance"]));
            $query->orderBy('distance ASC');
        } else {
            $query->orderBy(['mf.id' => SORT_DESC]);
        }

        $list = $query
            ->page($pagination, $this->limit, $this->page)
            ->asArray()
            ->all();
        // 假设 $list 是您从数据库查询出的列表
        foreach ($list as &$item) {
            // 首先判断是否有有效的经纬度，如果没有则显示'~'
            if (!$this->latitude || !$this->longitude || !isset($item['distance'])) {
                $item['distance'] = '~';
            } else {
                // 将数据库中取出的原始距离（单位是km）转换为浮点数
                $distanceInKm = (float)$item['distance'];

                // 判断距离是否小于1公里
                if ($distanceInKm < 1) {
                    // 小于1km，则转换为米并四舍五入到整数
                    // 例如：0.2km -> round(0.2 * 1000) -> 200
                    $item['distance'] = round($distanceInKm * 1000) . 'm';
                } else {
                    // 大于等于1km，则保留一位小数并四舍五入
                    // 例如：1.58km -> round(1.58, 1) -> 1.6
                    $item['distance'] = round($distanceInKm, 1) . 'km';
                }
            }
        }

        unset($item);
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'list' => $list,
            'total' => $count,
        ]);
    }
}
