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
namespace app\services\goods\pipeline;

use Exception;

class GoodsBaseSavePipe
{
    /**
     * @param GoodsContext $context
     * @param \Closure $next
     * @return mixed
     * @throws Exception
     */
    public function handle($context, \Closure $next)
    {
        $form = $context->form;
        $goods = $context->goods;

        $originalStock = $context->isEditing ? $goods->stock : 0;
        $originalIsAttr = $context->isEditing ? $goods->is_attr : 0;

        $defaultZeroFields = [
            'price', 'origin_price', 'cost_price', 'purchase_price', 'freight_price', 'weight',
            'stock', 'total_stock', 'sales', 'virtual_sales', 'sort', 'limit_num',
            'express_type', 'freight_id'
        ];
        foreach ($defaultZeroFields as $field) {
            if (property_exists($form, $field)) {
                if ($form->$field === '' || $form->$field === null) {
                    $form->$field = 0;
                }
            }
        }

        // 「留空 = 继承上级配置」的字段:空串必须归 NULL,不能当 0 落库。
        // concession_rate(平台抽点)的读取端是 BaseOrderHandler::calculateMchIncome:
        //   isset($goods->concession_rate) → 有值就用商品级,NULL 才回退商户全局费率。
        // 若空串落成 0.00,它会被当成"商品独立设置 0%",平台对该商品永久零抽佣、商家拿 100%(资损);
        // 而在生产的 STRICT 模式下空串直接是 SQL 错误,商品根本存不进去。两种环境两种坏法,故在此归一。
        // 注意:显式填 0 是合法值(该商品确实免抽佣),只有"没填"才继承,所以只转 '' 和 null。
        $inheritWhenBlankFields = ['concession_rate'];
        foreach ($inheritWhenBlankFields as $field) {
            if (property_exists($form, $field) && ($form->$field === '' || $form->$field === null)) {
                $form->$field = null;
            }
        }

        $goods->attributes = $form->attributes;
        $goods->cat_id = $form->cat_id ?? 0; // 插件可能没有 cat_id

        // 详情页服务标签:前端传 ID 数组,落库存 JSON。只认「这个商品能选的」——
        // 平台标签 + 该商品所属商家自建的标签,别家商家的 ID 硬塞过来一律丢掉。
        if (property_exists($form, 'service_tags')) {
            // ⚠ 商城号别直接读 $goods->mall_id:上面那行整体赋值会把表单里的 mall_id(通常是空)盖上来,
            //   真正的值要等 Goods::beforeSave 才补回去。这里取到 0 的话,标签一个都匹配不上、全被过滤掉。
            $mallId = (int)$goods->mall_id ?: (int)(\Yii::$app->mall->id ?? 0);
            $goods->service_tags = \app\models\GoodsServiceTag::sanitizeForGoods(
                $form->service_tags,
                $mallId,
                (int)$goods->mch_id
            );
        }
        
        // 尊重上下文的覆盖逻辑 (插件化核心)
        $goods->is_attr = $context->isAttr; 
        if ($context->goodsType) {
            $goods->type = $context->goodsType;
        }

        if ($context->isPlatformGoods) {
            $goods->stock = $originalStock;
            $goods->is_attr = $originalIsAttr;
        }

        // 编辑且带了库存快照:主表库存这里不写(改回库里的原值,save 就不会动它),
        // 等 GoodsSkuSyncPipe 按规格表原子重算 —— 表单里的 stock/total_stock 是打开页面时的旧值,
        // 整值写回会盖掉期间下单扣掉的库存。
        if ($context->isEditing && is_array($form->stock_snapshot ?? null)) {
            $goods->stock = $goods->getOldAttribute('stock');
            $goods->total_stock = $goods->getOldAttribute('total_stock');
        }

        if ($context->needsRecheck) {
            $goods->is_check = 0;
        }

        $expressType = property_exists($form, 'express_type') ? $form->express_type : 0;
        if ($expressType == 0) { // 包邮
            $goods->freight_price = 0;
            $goods->freight_id = 0;
        } elseif ($expressType == 1) { // 统一运费
            $goods->freight_id = 0;
        } elseif ($expressType == 2) { // 运费模板
            $goods->freight_price = 0;
        }

        if (!$goods->save()) {
            throw new Exception($form->getErrorMsg($goods));
        }

        // [架构升级] 自动同步商家的店铺私有分类 (Mch Cats)
        if (property_exists($form, 'mch_cats') && is_array($form->mch_cats)) {
            \app\models\MchGoodsCatRel::deleteAll(['goods_id' => $goods->id]);
            foreach ($form->mch_cats as $mCatId) {
                if (empty($mCatId)) continue;
                $rel = new \app\models\MchGoodsCatRel();
                $rel->mall_id = $goods->mall_id;
                $rel->mch_id = $goods->mch_id;
                $rel->goods_id = $goods->id;
                $rel->cat_id = (int)$mCatId;
                if (!$rel->save()) {
                    throw new Exception("商户私有分类保存失败: " . $rel->getErrorMsg($rel));
                }
            }
        }

        return $next($context);
    }
}
