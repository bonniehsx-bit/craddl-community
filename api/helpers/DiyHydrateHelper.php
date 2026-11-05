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
use app\models\GoodsInfo;
use yii\helpers\Json;

/**
 * DIY 装修页的「实时数据补齐」。
 *
 * 装修数据里只存配置(商品 id、分类 id 这些),价格 / 上下架 / 商品数要在打开页面时按当前用户现查:
 *  · goods-group 商品组:手选 id(原逻辑)或「按分类自动取 N 件」→ 实时商品(会员价 + 商品权限过滤)
 *  · tab-goods 选项卡商品:每个标签手选或按分类 → 实时商品。原来存的是装修时的商品快照,
 *    改了价格首页还显示旧价,这里统一按 id 重查
 *  · cat-grid 分类宫格:补每个分类的在售商品数、分类图
 *
 * 首页(IndexForm)和其它装修页(DiyPageForm)原来各写了一份几乎一样的商品组补齐代码,
 * 且一个按 level_id、一个按 level 判断商品权限(权限里存的是等级 id,level 是错的),统一收到这里。
 */
class DiyHydrateHelper
{
    /** 按分类自动取商品:一次最多取多少件 */
    const CAT_MAX_NUM = 20;

    public static function hydrate(array &$modules, int $mallId): void
    {
        $levelId = \Yii::$app->user->isGuest ? 0 : (int)\Yii::$app->user->identity->level_id;

        foreach ($modules as &$item) {
            if (!is_array($item) || !isset($item['name'])) continue;
            switch ($item['name']) {
                case 'goods-group':
                    if (!isset($item['data']) || !is_array($item['data'])) break;
                    $data = &$item['data'];
                    if (($data['source'] ?? 'manual') === 'cat') {
                        $ids = self::idsByCat((int)($data['cat_id'] ?? 0), $mallId, (int)($data['cat_num'] ?? 6), (string)($data['cat_sort'] ?? 'sales'));
                    } else {
                        $ids = isset($data['list']) && is_array($data['list']) ? $data['list'] : [];
                    }
                    $data['list'] = self::liveGoods($ids, $mallId, $levelId, true);
                    unset($data);
                    break;

                case 'tab-goods':
                    if (empty($item['data']['list']) || !is_array($item['data']['list'])) break;
                    foreach ($item['data']['list'] as &$tab) {
                        if (!is_array($tab)) continue;
                        if (($tab['source'] ?? 'manual') === 'cat') {
                            $ids = self::idsByCat((int)($tab['cat_id'] ?? 0), $mallId, (int)($tab['cat_num'] ?? 6), (string)($tab['cat_sort'] ?? 'sales'));
                        } elseif (isset($tab['goods_list']) && is_array($tab['goods_list'])) {
                            $ids = array_map(function ($g) {
                                return is_array($g) ? ($g['id'] ?? 0) : $g;
                            }, $tab['goods_list']);
                        } else {
                            continue; // 老数据(只有 id 走 /api/mall/tab-goods 的)不动
                        }
                        $tab['goods_list'] = self::liveGoods($ids, $mallId, $levelId, false);
                    }
                    unset($tab);
                    break;

                case 'cat-grid':
                    self::fillCatGrid($item['data'], $mallId);
                    break;
            }
        }
        unset($item);
    }

    /**
     * 按 id 顺序取在售商品 + 商品权限过滤 + 会员价。
     * $keepIdsWhenEmpty:一件都查不到时原样返回 id 列表 —— 商品组的老行为(前端拿到纯 id 会忽略),保持不变
     */
    public static function liveGoods(array $ids, int $mallId, int $levelId, bool $keepIdsWhenEmpty = false): array
    {
        // 空列表 / 非数字直接跳过:id IN [] → 0=1、ORDER BY FIELD(id, ) → SQL 语法错(整页 500)。强转 int 杜绝拼接注入
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return [];
        $idsStr = implode(',', $ids);
        $list = Goods::find()->andWhere(['id' => $ids, 'mall_id' => $mallId, 'is_delete' => 0, 'status' => 1])
            ->orderBy([new \yii\db\Expression("FIELD(id, $idsStr)")])
            ->asArray()->all();
        if (!$list) return $keepIdsWhenEmpty ? $ids : [];

        // 权限过滤:批量取 GoodsInfo(规避逐商品 findOne 的 N+1)
        $infoMap = GoodsInfo::find()->where(['goods_id' => array_column($list, 'id')])->indexBy('goods_id')->asArray()->all();
        $out = [];
        foreach ($list as $goods) {
            $info = $infoMap[$goods['id']] ?? null;
            if ($info && !empty($info['permission'])) {
                // 与原商品组逻辑逐字一致:权限串解析成空值的商品不展示
                $permission = Json::decode($info['permission']);
                if (!$permission) continue;
                if (!empty($permission['explode_is_level'])
                    && !in_array($levelId, $permission['explode_level_ids'] ?? [])) {
                    continue;
                }
            }
            $out[] = $goods;
        }
        // 会员价 / 插件代理价(与详情页同口径)
        GoodsMemberPriceHelper::applyToArrayList($out, GoodsMemberPriceHelper::currentLevelId());
        return $out;
    }

    /** 分类(含一级子分类)下的在售商品 id,按指定方式排序取前 N 件 */
    public static function idsByCat(int $catId, int $mallId, int $num, string $sort = 'sales'): array
    {
        if ($catId <= 0) return [];
        $num = max(1, min(self::CAT_MAX_NUM, $num ?: 6));
        $catIds = self::catWithChildren([$catId], $mallId)[$catId] ?? [$catId];
        $order = [
            'new' => ['g.id' => SORT_DESC],
            'price_asc' => ['g.price' => SORT_ASC, 'g.id' => SORT_DESC],
            'price_desc' => ['g.price' => SORT_DESC, 'g.id' => SORT_DESC],
        ][$sort] ?? [new \yii\db\Expression('(g.sales + g.virtual_sales) DESC'), 'g.id' => SORT_DESC];
        return Goods::find()->alias('g')->select('g.id')->distinct()
            ->innerJoin('{{%goods_cat}} gc', 'gc.goods_id = g.id AND gc.is_delete = 0')
            ->where(['gc.cat_id' => $catIds, 'g.mall_id' => $mallId, 'g.is_delete' => 0, 'g.status' => 1])
            ->orderBy($order)->limit($num)->column();
    }

    /** 分类宫格:补分类名(没填时)、分类图、在售商品数(含子分类,去重) */
    public static function fillCatGrid(&$data, int $mallId): void // $data:cat-grid 的 data,可能为 null
    {
        if (!is_array($data) || empty($data['items']) || !is_array($data['items'])) return;
        $catIds = array_values(array_unique(array_filter(array_map(function ($it) {
            return (int)($it['cat_id'] ?? 0);
        }, $data['items']))));
        if (!$catIds) return;
        $cats = (new \yii\db\Query())->from('{{%cat}}')->select(['id', 'name', 'cover_pic'])
            ->where(['id' => $catIds, 'mall_id' => $mallId, 'is_delete' => 0])->indexBy('id')->all();
        $family = self::catWithChildren($catIds, $mallId);
        foreach ($data['items'] as &$it) {
            $cid = (int)($it['cat_id'] ?? 0);
            if (!$cid || !isset($cats[$cid])) continue;
            $it['cat_name'] = $cats[$cid]['name'];
            $it['cat_pic'] = $cats[$cid]['cover_pic'];
            $it['goods_count'] = (int)Goods::find()->alias('g')
                ->innerJoin('{{%goods_cat}} gc', 'gc.goods_id = g.id AND gc.is_delete = 0')
                ->where(['gc.cat_id' => $family[$cid] ?? [$cid], 'g.mall_id' => $mallId, 'g.is_delete' => 0, 'g.status' => 1])
                ->count('DISTINCT g.id');
        }
        unset($it);
    }

    /** [父分类 id => [自己 + 一级子分类 id...]] */
    private static function catWithChildren(array $catIds, int $mallId): array
    {
        $children = (new \yii\db\Query())->from('{{%cat}}')->select(['id', 'parent_id'])
            ->where(['parent_id' => $catIds, 'mall_id' => $mallId, 'is_delete' => 0])->all();
        $map = [];
        foreach ($catIds as $id) $map[$id] = [$id];
        foreach ($children as $c) $map[(int)$c['parent_id']][] = (int)$c['id'];
        return $map;
    }
}
