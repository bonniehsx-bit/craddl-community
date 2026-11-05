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
use app\models\BaseModel;
use app\plugins\mch\models\Mch;
use Yii;
use yii\helpers\Json;

class NearbyMchListForm extends BaseModel
{
    public $page;
    public $limit = 10;
    public $latitude;
    public $longitude;
    public $keyword;
    public $cat_id;
    public $type; // [新增] 商家类型：2=门店, 3=酒店

    public function rules()
    {
        return [
            [['page', 'limit', 'cat_id', 'type'], 'integer'],
            [['latitude', 'longitude'], 'number'],
            [['keyword'], 'string'],
            [['page'], 'default', 'value' => 1],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $query = Mch::find()->alias('m')
            ->with([
                'category' => function ($query) {
                    $query->select(['id', 'cat_name']);
                },
                // 加载热门商品 (用于展示和计算起步价)
                'hotGoods' => function ($q) {
                    $q->select(['id', 'mch_id', 'name', 'cover_pic', 'price', 'sales'])
                      ->andWhere(['type' => \app\models\Goods::TYPE_NORMAL])
                      ->orderBy(['sales' => SORT_DESC, 'sort' => SORT_ASC]);
                },
                // 酒店扩展数据通过 mch.hotel 关联懒加载（hotel 插件未装时返回 null）
                // 之所以保留预加载：兼容存量 type=3 商户在此列表里的渲染
                'hotel',
            ])
            ->where([
                'm.mall_id' => Yii::$app->mall->id,
                'm.is_delete' => 0,
                'm.status' => 1,
                'm.is_hide' => 0
            ]);

        // [核心修改] 动态筛选类型
        if ($this->type) {
            $query->andWhere(['m.type' => $this->type]);
        } else {
            // 默认只查门店 (兼容旧逻辑)
            $query->andWhere(['m.type' => Mch::TYPE_DOWN_STORE]);
        }

        if ($this->keyword) {
            $query->andWhere(['like', 'm.mch_name', $this->keyword]);
        }

        if ($this->cat_id) {
            $query->andWhere(['m.cat_id' => $this->cat_id]);
        }

        $selectFields = [
            'm.id',
            'm.mch_name',
            'm.logo',
            'm.address',
            'm.delivery_options', // 注意：这里还是取原始字段
            'm.cat_id',
            'm.type',
            'm.rating', // [新增] 取出评分
            'm.mobile', 'm.tel', 'm.latitude', 'm.longitude', 'm.opening_hours', 'm.location_address'
        ];

        // LBS 排序逻辑
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

        $pagination = null;
        $list = $query->page($pagination, $this->limit, $this->page)->asArray()->all();

        // 数据二次处理
        foreach ($list as &$item) {
            $item['category'] = $item['category']['cat_name'] ?? '';
            $item['products'] = $item['hotGoods'] ?? [];
            unset($item['hotGoods']);

            // 距离格式化
            if (isset($item['distance'])) {
                $item['distance'] = (float)$item['distance'] < 1
                    ? (round($item['distance'] * 1000) . 'm')
                    : (round($item['distance'], 1) . 'km');
            } else {
                $item['distance'] = '';
            }

            // 差异化数据：基于 mch.hotel 关联是否存在判断（而非 type 字段硬编码）
            //   - 关联存在 → 兼容存量酒店商户：显示起步价 / 设施标签 / 不显示配送
            //   - 关联为空 → 普通门店：配送服务列表 / 无起步价
            //   hotel 插件未装时 mch->hotel 走 0=1 短路 → 关联永远为空 → 自动退化为普通门店
            $hotelData = $item['hotel'] ?? null;
            if (!empty($hotelData)) {
                // 起步价：从已加载商品里取最低价
                $minPrice = 0;
                if (!empty($item['products'])) {
                    $prices = array_column($item['products'], 'price');
                    $minPrice = min($prices);
                }
                $item['min_price'] = $minPrice;

                $item['service_tags'] = [];
                if (!empty($hotelData['service_tags'])) {
                    try {
                        $tags = Json::decode($hotelData['service_tags']);
                        $item['service_tags'] = is_array($tags) ? array_slice($tags, 0, 3) : [];
                    } catch (\Exception $e) {
                    }
                }
                $item['services'] = [];
            } else {
                // 普通门店
                $servicesData = @json_decode($item['delivery_options'], true);
                $item['services'] = $this->formatServices($servicesData);
                $item['min_price'] = '';
            }

            // 清理掉不需要返回给前端的中间数据
            unset($item['delivery_options']);
            unset($item['hotel']);
        }
        unset($item);

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => '获取成功',
            'data' => ['list' => $list, 'pagination' => $pagination]
        ];
    }

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
