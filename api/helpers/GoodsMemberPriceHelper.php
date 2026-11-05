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

namespace app\helpers;

use app\models\Goods;
use Yii;

/**
 * 商品列表会员价批量套用工具。
 *
 * 背景:会员价/插件代理价的唯一计算入口是 Goods 模型实例方法 Goods::applyLevelDiscountToPrice()
 * (内含插件定价钩子 EVENT_CALC_PRICE)。用 ->asArray() 查出来的纯数组无法调用它 → 各 C 端列表页
 * 只能显示原价,与详情页(会员价)前后不一致。本工具给「asArray 商品列表」批量补会员价,且规避 N+1:
 *   · 一次性批量 Goods::find()->with('info') 拿模型(含 GoodsInfo 预载),按 id 建 map,一条 SQL;
 *   · Level 由 Goods 内部请求级缓存(resolveLevelCached),循环里不再逐商品查 Level;
 *   · 逐条走 applyLevelDiscountToPrice(触发插件定价钩子),价格有变则原价挪 origin_price。
 *
 * 用法:各列表表单拿到 asArray 列表后一行调用:
 *   GoodsMemberPriceHelper::applyToArrayList($list, GoodsMemberPriceHelper::currentLevelId());
 */
class GoodsMemberPriceHelper
{
    /**
     * 给一批「asArray 商品列表」批量套会员价/插件定价(原地改写)。
     *
     * @param array  &$list    asArray 商品列表,每项至少含 $idKey、$priceKey;原地改写
     * @param int    $levelId  当前用户等级ID(Level.id);0/游客 → 不折算,自然回退原价
     * @param string $priceKey 价格字段名(默认 price)
     * @param string $idKey    商品ID字段名(默认 id)。购物车/订单明细这类「主键不是 goods_id」的列表
     *                         传 'goods_id',否则会拿 cart.id 去查 Goods 而张冠李戴。
     */
    public static function applyToArrayList(array &$list, $levelId, $priceKey = 'price', $idKey = 'id')
    {
        if (empty($list)) {
            return;
        }

        $ids = [];
        foreach ($list as $it) {
            if (is_array($it) && isset($it[$idKey])) {
                $ids[] = $it[$idKey];
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids)) {
            return;
        }

        // 批量拿模型 + 预载 info(规避 applyLevelDiscountToPrice 里 $this->info 懒加载的 N+1),一条 SQL
        /** @var Goods[] $models */
        $models = Goods::find()->where(['id' => $ids])->with('info')->indexBy('id')->all();
        if (empty($models)) {
            return;
        }

        foreach ($list as &$item) {
            if (!is_array($item) || !isset($item[$idKey])) {
                continue;
            }
            $model = $models[$item[$idKey]] ?? null;
            if (!$model) {
                continue;
            }
            $base = $item[$priceKey] ?? $model->price;
            $final = $model->applyLevelDiscountToPrice($base, $levelId);
            // 价格有变(会员价/代理价)才改写:原价挪 origin_price,置 is_member_price 供前端展示
            if ((string)$final !== (string)$base && (float)$final >= 0) {
                $item['origin_price'] = $base;
                $item[$priceKey] = $final;
                $item['is_member_price'] = 1;
            }
        }
        unset($item);
    }

    /**
     * 给一批 Goods 模型实例套会员价(原地改写 model->price / origin_price,仅内存不落库)。
     * 适用于返回「模型」而非 asArray 的列表。调用方应对查询 ->with('info') 预载,避免 info 懒加载 N+1。
     *
     * @param Goods[]|iterable $models  Goods 模型实例列表
     * @param int              $levelId 当前用户等级ID;0/游客 → 不折算
     */
    public static function applyToModelList($models, $levelId)
    {
        if (empty($models)) {
            return;
        }
        foreach ($models as $model) {
            if (!$model instanceof Goods) {
                continue;
            }
            $base = $model->price;
            $final = $model->applyLevelDiscountToPrice($base, $levelId);
            if ((string)$final !== (string)$base && (float)$final >= 0) {
                $model->origin_price = $base;
                $model->price = $final;
                $model->is_member_price = 1;
            }
        }
    }

    /**
     * 从当前登录用户解析 level_id(游客返回 0)。
     * @return int
     */
    public static function currentLevelId()
    {
        if (Yii::$app->user->isGuest) {
            return 0;
        }
        return (int)(Yii::$app->user->identity->level_id ?? 0);
    }
}
