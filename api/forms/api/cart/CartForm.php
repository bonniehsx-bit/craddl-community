<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-11
 * Time: 17:32
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

namespace app\forms\api\cart;


use app\core\ApiCode;
use app\helpers\UserHelper;
use app\models\Attr;
use app\models\BaseModel;
use app\models\Cart;
use app\models\Goods;
use app\plugins\mch\models\Mch;

class CartForm extends BaseModel
{
    public $page;
    public $limit;
    public $keyword;
    public $mch_id;

    public function rules()
    {
        return [
            [['page', 'limit', 'mch_id'], 'integer'],
            [['keyword'], 'string'],
            [['limit'], 'default', 'value' => 10]
        ];
    }

    public function getCount()
    {
        $count = Cart::find()->where(['user_id' => \Yii::$app->user->identity->id, 'is_delete' => 0])->count();
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['count' => $count ?? 0]);
    }

    public function clear()
    {

        if ($this->mch_id) {
            $goods_ids = Cart::find()->alias('c')
                ->innerJoin(['g' => Goods::tableName()], 'g.id=c.goods_id')
                ->andWhere(['g.mch_id' => $this->mch_id])
                ->select('c.goods_id')->column();
            if ($goods_ids) {
                Cart::deleteAll(['user_id' => UserHelper::getMyId(), 'goods_id' => $goods_ids]);
            }
        } else {
            Cart::deleteAll(['user_id' => UserHelper::getMyId()]);
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '清空成功');
    }

    public function getMyCart()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // --- 步骤1：优化查询，同时获取商品和商家信息 ---
        $query = Cart::find()->alias('c')
            ->leftJoin(['g' => Goods::tableName()], 'g.id=c.goods_id')
            ->leftJoin(['m' => Mch::tableName()], 'm.id = g.mch_id') // [核心改造] 关联商家表
            ->where([
                'c.mall_id' => \Yii::$app->mall->id,
                'c.is_delete' => 0,
                'c.user_id' => \Yii::$app->user->identity->id
            ]);

        if ($this->mch_id) {
            $query->andWhere(['g.mch_id' => $this->mch_id]);
        }
        if ($this->keyword) {
            $query->andWhere(['like', 'g.name', $this->keyword]);
        }

        $list = $query->page($pagination, $this->limit, $this->page)
            ->orderBy('c.created_at DESC') // 按加入购物车时间倒序
            // [核心改造] 在 select 中加入商家名称
            ->select('c.*, g.cover_pic, g.name, g.price as goods_price, g.is_attr, g.mch_id, m.mch_name, g.send_integral, g.send_integral_type')
            ->asArray()
            ->all();

        // --- 步骤2：处理商品有效性、规格和最终价格 ---
        // 商品和规格一次查完,不在循环里逐条 findOne
        $goodsMap = Goods::find()
            ->where(['id' => array_unique(array_column($list, 'goods_id'))])
            ->indexBy('id')->all();
        $attrMap = Attr::find()
            ->where(['id' => array_unique(array_filter(array_column($list, 'goods_attr_id'))), 'is_delete' => 0])
            ->indexBy('id')->all();

        $processedList = [];
        $invalidList = [];
        foreach ($list as $item) {
            $item['num'] = intval($item['num']);
            $item['attr_name'] = '';
            $item['price'] = $item['goods_price'];
            $item['stock'] = 0;
            $item['invalid_reason'] = '';

            /** @var Goods|null $goods */
            $goods = $goodsMap[$item['goods_id']] ?? null;
            if (!$goods || $goods->is_delete || $goods->status == 0) {
                $item['invalid_reason'] = '商品已下架';
            } elseif ($item['is_attr']) {
                /** @var Attr|null $attr */
                $attr = $attrMap[$item['goods_attr_id']] ?? null;
                // 规格必须属于这件商品:否则购物车里会按别的商品的规格价显示
                if (!$attr || (int)$attr->goods_id !== (int)$item['goods_id']) {
                    $item['invalid_reason'] = '规格已调整,请重新选择';
                } elseif ($attr->stock <= 0) {
                    $item['invalid_reason'] = '该规格已售罄';
                } else {
                    $item['attr_name'] = $attr->attr_name;
                    $item['price'] = $attr->price;
                    $item['stock'] = (int)$attr->stock;
                    $item['cover_pic'] = $attr->cover_pic ?: $item['cover_pic'];
                }
            } elseif ($goods->stock <= 0) {
                $item['invalid_reason'] = '商品已售罄';
            } else {
                $item['stock'] = (int)$goods->stock;
            }
            $item['is_valid'] = $item['invalid_reason'] === '' ? 1 : 0;

            // 移除不再需要的字段，保持数据干净
            unset($item['goods_price']);

            if ($item['is_valid']) {
                $processedList[] = $item;
            } else {
                $invalidList[] = $item;
            }
        }

        // --- 步骤2.5：补会员价/插件代理价 ---
        // 与下单实付同口径(OrderPricingService 也是「base = 规格价或商品价 → applyLevelDiscountToPrice」),
        // 否则购物车显示原价、下单却按会员价扣,前后对不上。
        // ⚠ 必须放在上面规格价赋值之后、按商家分组之前:此时才是「已带规格价的扁平数组」,
        //   分组后是嵌套结构,Helper 就套不上了。
        // ⚠ idKey 传 goods_id:本列表 select 了 c.*,其 id 是 cart.id,用默认 'id' 会张冠李戴。
        \app\helpers\GoodsMemberPriceHelper::applyToArrayList(
            $processedList,
            \app\helpers\GoodsMemberPriceHelper::currentLevelId(),
            'price',
            'goods_id'
        );

        // --- 步骤3：[核心改造] 将有效商品列表按 mch_id 分组 ---
        $groupedByMch = [];
        foreach ($processedList as $item) {
            $mchId = (int)$item['mch_id'];
            // 如果这个商家的分组还不存在，则初始化
            if (!isset($groupedByMch[$mchId])) {
                $groupedByMch[$mchId] = [
                    'mch_id' => $mchId,
                    'mch_name' => $item['mch_name'] ?: '平台自营',
                    'goods_list' => [],
                ];
            }
            // 将当前商品项添加到对应商家的 goods_list 中
            $groupedByMch[$mchId]['goods_list'][] = $item;
        }
        // 将以 mch_id 为键的关联数组，转换为前端需要的索引数组
        $finalGroupedList = array_values($groupedByMch);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'list' => $finalGroupedList,  // 返回分组后的数据
            'invalid_list' => $invalidList, // 附带失效商品列表
            'pagination' => $pagination
        ]);
    }
}
