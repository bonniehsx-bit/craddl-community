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
use app\helpers\JsonHelper;
use app\models\BaseModel;
use app\models\Cat;
use app\models\Goods;
use yii\helpers\ArrayHelper;

class MchGoodsListForm extends BaseModel
{
    public $id; // 接收 mch_id

    public function rules()
    {
        return [['id', 'required'], ['id', 'integer']];
    }

    public function attributeLabels()
    {
        return ['id' => '商户ID'];
    }

    /**
     * [模型驱动重构版] 获取单个商户的、按分类组织的商品列表
     * @return array
     */
    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        try {
            // --- 步骤1: [重构] 一次性获取该商户的所有商品模型对象 ---
            $goodsModels = Goods::find()->with(['info', 'attrs' => function ($query) {
                $query->andWhere(['is_delete' => 0]);
            }])
                ->where([
                    'mall_id' => \Yii::$app->mall->id,
                    'mch_id' => $this->id,
                    'is_delete' => 0,
                    'status' => 1,
                    'is_check' => 1,
                ])->andWhere(['type' => Goods::TYPE_NORMAL])->andWhere(['>', 'stock', 0])
                ->orderBy('sort ASC, id DESC')->all();
            if (empty($goodsModels)) {
                return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', ['list' => []]);
            }

            // --- 步骤2: 检查是否有商家自建分类，有则优先使用，否则降级使用平台分类 ---
            $catModels = Cat::find()->where([
                'mall_id' => \Yii::$app->mall->id,
                'mch_id' => $this->id,
                'is_delete' => 0,
            ])->orderBy('sort ASC, id DESC')->all();

            $useMchCat = !empty($catModels);

            if (!$useMchCat) {
                // 回退到平台分类
                $catModels = Cat::find()->where([
                    'mall_id' => \Yii::$app->mall->id,
                    'mch_id' => 0,
                    'is_delete' => 0,
                ])->orderBy('sort ASC, id DESC')->all();
            }

            // --- 步骤3: 初始化按照类目分组结构，支持通过 pivot 表绑定多个商家类目 ---
            $goodsByCatId = [];
            
            if ($useMchCat) {
                $catRels = \app\models\MchGoodsCatRel::find()->where(['mch_id' => $this->id, 'is_delete' => 0])->all();
                $goodsRelMap = []; 
                foreach ($catRels as $rel) {
                    $goodsRelMap[$rel->goods_id][] = $rel->cat_id;
                }
                foreach ($goodsModels as $goods) {
                    if (isset($goodsRelMap[$goods->id])) {
                        // 挂载到其所有的商家自定义分类下 (去重兜底)
                        $uniqueCatIds = array_unique($goodsRelMap[$goods->id]);
                        foreach ($uniqueCatIds as $cId) {
                            $goodsByCatId[$cId][] = $goods;
                        }
                    } else {
                        // 如果有商品未绑定到任何商家类目，统一归入 '全部商品' (虚拟分类 -1)
                        $goodsByCatId[-1][] = $goods;
                    }
                }
            } else {
                foreach ($goodsModels as $goods) {
                    $goodsByCatId[$goods->cat_id][] = $goods;
                }
            }

            // --- 步骤4: 构建最终的树状结构 ---
            $treeList = [];

            // 预设一个"默认分类"容纳未归类的游离商品
            if ($useMchCat && !empty($goodsByCatId[-1])) {
                $treeList[-1] = [
                    'id' => -1,
                    'name' => '其它商品',
                    'products' => $this->formatGoods($goodsByCatId[-1]),
                    'children' => []
                ];
            }

            // a. 先处理一级分类
            foreach ($catModels as $cat) {
                if ($cat->parent_id == 0) {
                    $catData = $cat->toArray();
                    $catData['children'] = [];
                    $treeList[$cat->id] = $catData;
                }
            }

            // b. 再处理二级分类
            foreach ($catModels as $cat) {
                if ($cat->parent_id > 0 && isset($treeList[$cat->parent_id])) {
                    $catData = $cat->toArray();
                    $catData['products'] = $this->formatGoods($goodsByCatId[$cat->id] ?? []);
                    if (!empty($catData['products'])) {
                        $treeList[$cat->parent_id]['children'][] = $catData;
                    }
                }
            }

            // c. 为没有子分类的一级分类，直接挂载商品
            foreach ($treeList as $k => $level1Cat) {
                if ($k != -1 && empty($level1Cat['children'])) {
                    $treeList[$k]['products'] = $this->formatGoods($goodsByCatId[$level1Cat['id']] ?? []);
                }
            }

            // d. 过滤掉没有任何商品的空分类
            $finalList = [];
            foreach ($treeList as $level1Cat) {
                if (!empty($level1Cat['products']) || !empty($level1Cat['children'])) {
                    $finalList[] = $level1Cat;
                }
            }

            return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', ['list' => array_values($finalList)]);

        } catch (\Exception $e) {
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }

    /**
     * [重构] 辅助方法：将 Goods 模型对象数组，格式化为前端需要的纯数组
     * @param Goods[] $goodsModels
     * @return array
     */
    private function formatGoods(array $goodsModels)
    {
        $formatted = [];
        foreach ($goodsModels as $goods) {
            $specGroups = $goods->info && $goods->info->spec_template ? JsonHelper::decode($goods->info->spec_template) : [];

            $skuList = [];
            if ($goods->attrs) {
                foreach ($goods->attrs as $attr) {
                    $specs = $attr->spec_data ? JsonHelper::decode($attr->spec_data) : [];
                    $skuList[] = [
                        'id' => (int)$attr->id,
                        'price' => (float)$attr->price,
                        'stock' => (int)$attr->stock,
                        'cover_pic' => $attr->cover_pic ?? '',
                        'specs' => is_array($specs) ? $specs : [],
                    ];
                }
            }

            $formatted[] = [
                'id' => $goods->id,
                'name' => $goods->name,
                'cover_pic' => $goods->cover_pic,
                'price' => $goods->price,
                'sales' => $goods->sales + $goods->virtual_sales,
                'stock' => $goods->stock,
                'is_attr' => $goods->is_attr,
                'send_integral' => $goods->send_integral,
                'specs_data' => [
                    'spec_groups' => is_array($specGroups) ? $specGroups : [],
                    'sku_list' => $skuList
                ]
            ];
        }
        return $formatted;
    }
}