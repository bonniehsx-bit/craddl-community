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

namespace app\services;

use app\core\ApiCode;
use app\models\Attr;
use Yii;
use yii\db\Expression;
use yii\helpers\Json;

/**
 * Class GoodsService
 * @package app\services
 * @description 商品统一底层服务，处理 SKU 同步、库存计算与前端规格组装。
 */
class GoodsService
{
    /**
     * 自动处理单规格兼容与前端规格解构
     * 彻底废除 is_attr 依赖，无脑将所有商品转为“统一 SKU 架构”
     *
     * @param int|null $goodsId 当编辑时传入商品ID用于继承默认SKU的ID
     * @param array $specsData 前端传入的 specs_data 数组
     * @param float $basePrice 基础售价，用于兜底
     * @param float $costPrice 成本价，用于兜底
     * @param int $stock 基础库存，用于兜底
     * @param float $weight 基础重量，用于兜底
     * @param string $no 基础货号，用于兜底
     * @param string $coverPic 基础缩略图，用于兜底
     * @return array [is_attr, specs_data (已清洗)]
     */
    public static function normalizeSpecs($goodsId, $specsData, $basePrice, $costPrice, $stock, $weight = 0, $no = '', $coverPic = '', $purchasePrice = 0)
    {
        // 如果规格为空（前端没开启多规格直接保存）
        if (empty($specsData) || empty($specsData['spec_groups']) || empty($specsData['sku_list'])) {
            $defaultSkuId = 0;
            if ($goodsId) {
                $existingAttr = Attr::find()->where(['goods_id' => $goodsId, 'is_delete' => 0])->one();
                if ($existingAttr) {
                    $defaultSkuId = $existingAttr->id;
                }
            }

            $specsData = [
                'spec_groups' => [
                    [
                        'id' => 'temp_group_0',
                        'name' => '规格',
                        'values' => [['id' => 'temp_value_0', 'name' => '默认']]
                    ]
                ],
                'sku_list' => [
                    [
                        'id' => $defaultSkuId,
                        'specs' => [['group' => '规格', 'value' => '默认']],
                        'price' => $basePrice,
                        'cost_price' => $costPrice,
                        'purchase_price' => $purchasePrice,
                        'stock' => $stock,
                        'weight' => $weight,
                        'no' => $no,
                        'cover_pic' => $coverPic,
                    ]
                ]
            ];
        }

        // 清洗前端规格组可能附带的垃圾属性 (Element UI 的 inputVisible 等)
        if (isset($specsData['spec_groups']) && is_array($specsData['spec_groups'])) {
            $clean_spec_groups = &$specsData['spec_groups'];
            foreach ($clean_spec_groups as &$group) {
                unset($group['inputVisible'], $group['inputValue']);
            }
            unset($group);
        }

        return [
            'is_attr' => 1, // 永远是1
            'specs_data' => $specsData
        ];
    }

    /**
     * 【核心架构对齐】安全的 SKU 差集同步机制 + 反哺数据提取
     *
     * @param int $goodsId 商品ID
     * @param int $mallId 商城ID
     * @param array $skuList 前端传入的 SKU 列表
     * @param float $fallbackPrice 外层兜底用的单件价格，防脏
     * @return array
     */
    /**
     * @param array|null $stockSnapshot 打开编辑页时各规格的库存 {attr_id: stock}。
     *   给了就按差额改:已有规格的库存 = 当前库存 + (表单值 − 快照值),一条 SQL 原子加减;
     *   不给(新建商品、没带快照的老调用方)还是整值写入,和原来一样。
     *   ⛔ 整值写回会把「打开页面 → 点保存」之间下单扣掉的库存加回来 —— 哪怕运营只改了个商品名,
     *      表单里的库存也是打开页面时的旧值,照样写回去,然后超卖。
     */
    public static function syncSkus($goodsId, $mallId, $skuList, $fallbackPrice = 0, ?array $stockSnapshot = null)
    {
        if ($skuList && is_string($skuList)) {
            $skuList = Json::decode($skuList);
        }
        if (!is_array($skuList)) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '规格数据格式错误'];
        }

        // 建立老规格的索引池，用于后续作差集被动淘汰
        $existingAttrs = Attr::find()
            ->where(['goods_id' => $goodsId, 'is_delete' => 0])
            ->indexBy('id')
            ->all();

        $totalStock = 0;
        $processedIds = [];
        $stockChanged = false;   // 这次保存有没有真动库存(调了数、加了/删了规格),售罄豁免要用
        $stockDeltas = [];       // attr_id => 差额,规格行保存完再统一原子加减

        // 反哺主表兜底:重量记首个有效 SKU,价格记所有 SKU 的最小价(见下方循环内说明)
        $defaultWeight = 0;
        $defaultPrice = 0;
        $isFirst = true;

        foreach ($skuList as $skuItem) {
            if (empty($skuItem['specs']) || !is_array($skuItem['specs'])) {
                continue;
            }

            $attrNameParts = [];
            foreach ($skuItem['specs'] as $spec) {
                if (isset($spec['value'])) {
                    $attrNameParts[] = $spec['value'];
                }
            }

            $skuModel = null;
            $currentId = isset($skuItem['id']) ? (int)$skuItem['id'] : 0;

            if ($currentId > 0 && isset($existingAttrs[$currentId])) {
                $skuModel = $existingAttrs[$currentId];
                $processedIds[] = $currentId;
            } else {
                $skuModel = new Attr();
                $skuModel->goods_id = $goodsId;
                $skuModel->mall_id = $mallId;
            }

            $skuModel->attr_name = implode(';', $attrNameParts);
            $skuModel->price = isset($skuItem['price']) ? (float)$skuItem['price'] : (float)$fallbackPrice;
            $skuModel->cost_price = isset($skuItem['cost_price']) ? (float)$skuItem['cost_price'] : 0;
            $skuModel->purchase_price = isset($skuItem['purchase_price']) ? (float)$skuItem['purchase_price'] : 0;
            $formStock = isset($skuItem['stock']) ? (int)$skuItem['stock'] : 0;
            $byDelta = !$skuModel->isNewRecord && $stockSnapshot !== null && array_key_exists($currentId, $stockSnapshot);
            if ($byDelta) {
                // 已有规格:不动模型上的 stock(save 就不会写它),只记差额
                $delta = $formStock - (int)$stockSnapshot[$currentId];
                if ($delta !== 0) {
                    $stockDeltas[$currentId] = $delta;
                    $stockChanged = true;
                }
            } else {
                if ($skuModel->isNewRecord || (int)$skuModel->stock !== $formStock) {
                    $stockChanged = true;
                }
                $skuModel->stock = $formStock;
            }
            $skuModel->weight = isset($skuItem['weight']) ? (float)$skuItem['weight'] : 0;
            $skuModel->no = isset($skuItem['no']) ? (string)$skuItem['no'] : '';
            $skuModel->cover_pic = isset($skuItem['cover_pic']) ? (string)$skuItem['cover_pic'] : '';
            $skuModel->spec_data = Json::encode($skuItem['specs'], JSON_UNESCAPED_UNICODE);

            if (!$skuModel->save()) {
                return ['code' => ApiCode::CODE_FAIL, 'msg' => 'SKU保存失败', 'error' => $skuModel->getErrors()];
            }

            if (isset($stockDeltas[$skuModel->id])) {
                // 调的是差额:同时有人下单扣库存也不会被盖掉。调减超过现有库存时归 0,不为负
                Attr::updateAll(
                    ['stock' => new Expression('GREATEST(0, stock + :delta)', [':delta' => $stockDeltas[$skuModel->id]])],
                    ['id' => $skuModel->id]
                );
            }

            // 差额模式下模型上的 stock 还是旧值,合计以库里为准,由调用方原子重算(见 GoodsSkuSyncPipe)
            $totalStock += $byDelta ? 0 : $skuModel->stock;

            // 反哺主表:重量取首个有效 SKU;价格取所有 SKU 的【最小价】——
            // goods.price 对多规格是纯展示/排序字段(下单按选中 SKU 的 attr.price 计),
            // 取最小价才符合列表/详情"¥X 起"的市场惯例(原实现取首个 SKU 价,随规格排列顺序而定,会误导)。
            if ($isFirst) {
                $defaultWeight = $skuModel->weight;
                $defaultPrice = $skuModel->price;
                $isFirst = false;
            } elseif ($skuModel->price < $defaultPrice) {
                $defaultPrice = $skuModel->price;
            }
        }

        // 差集运算：找出被前端删掉的老规格，执行软删除
        $allExistingIds = array_keys($existingAttrs);
        $idsToDelete = array_diff($allExistingIds, $processedIds);

        if (!empty($idsToDelete)) {
            $stockChanged = true;
            Attr::updateAll(
                ['is_delete' => 1, 'deleted_at' => time()],
                ['id' => $idsToDelete]
            );
        }

        // 吐出给上层调用者反哺 Goods 主表的数据
        return [
            'code' => 0,
            'msg' => '同步成功',
            'data' => [
                'total_stock' => $totalStock, // 差额模式下不可信,调用方要按规格表原子重算
                'stock_changed' => $stockChanged,
                'weight' => $defaultWeight,
                'price' => $defaultPrice
            ]
        ];
    }
}
