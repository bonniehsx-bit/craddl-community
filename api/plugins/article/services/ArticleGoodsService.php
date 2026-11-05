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

namespace app\plugins\article\services;

use app\helpers\GoodsMemberPriceHelper;
use app\models\Attr;
use app\models\Goods;
use app\plugins\article\models\ArticleGoods;
use yii\base\UserException;

/**
 * 文章挂商品:保存(整组替换)+ 给 C 端 / 后台读取。
 *
 * 展示规则:商品下架 / 已删除 / 没库存 / 不是普通商品的不显示,
 * 价格走会员价口径(和商城列表一致)。挂载记录本身保留,商品重新上架后自动回来。
 */
class ArticleGoodsService
{
    public const MAX = 6;
    public const RECOMMEND_MAX = 60;

    /**
     * 整组替换一篇文章的挂载商品。
     *
     * @param array $items [['goods_id'=>1,'recommend'=>'..','is_main'=>1], ...] 顺序即排序
     * @throws UserException 校验不过
     */
    public static function replace(int $articleId, int $mallId, array $items): void
    {
        $clean = [];
        foreach ($items as $it) {
            $gid = (int)($it['goods_id'] ?? $it['id'] ?? 0);
            if ($gid <= 0 || isset($clean[$gid])) {
                continue; // 重复的只留第一条
            }
            $rec = trim((string)($it['recommend'] ?? ''));
            if (mb_strlen($rec) > self::RECOMMEND_MAX) {
                throw new UserException('推荐语最多 ' . self::RECOMMEND_MAX . ' 个字');
            }
            $clean[$gid] = ['goods_id' => $gid, 'recommend' => $rec, 'is_main' => !empty($it['is_main']) ? 1 : 0];
        }
        if (count($clean) > self::MAX) {
            throw new UserException('每篇文章最多挂 ' . self::MAX . ' 件商品');
        }
        if ($clean) {
            // 只能挂本商城、未删除的「普通商品」;插件商品(拼团 / 核销券 / 来信……)下单入口各不相同,文章里点不进去
            $q = Goods::find()->select('id')->where(['id' => array_keys($clean), 'mall_id' => $mallId, 'is_delete' => 0, 'type' => 'normal']);
            $ok = array_map('intval', $q->column());
            $bad = array_diff(array_keys($clean), $ok);
            if ($bad) {
                throw new UserException('商品 ' . implode('、', $bad) . ' 不存在或不能挂在文章里');
            }
            // 主推:只留第一个标了的;一个都没标就第一件
            $main = 0;
            foreach ($clean as $gid => $c) {
                if ($c['is_main'] && !$main) {
                    $main = $gid;
                }
            }
            $main = $main ?: array_key_first($clean);
            foreach ($clean as $gid => &$c) {
                $c['is_main'] = $gid === $main ? 1 : 0;
            }
            unset($c);
        }

        $tx = \Yii::$app->db->beginTransaction();
        try {
            ArticleGoods::deleteAll(['article_id' => $articleId, 'mall_id' => $mallId]);
            $sort = 0;
            foreach ($clean as $c) {
                $m = new ArticleGoods();
                $m->mall_id = $mallId;
                $m->article_id = $articleId;
                $m->goods_id = $c['goods_id'];
                $m->recommend = $c['recommend'];
                $m->is_main = $c['is_main'];
                $m->sort = $sort++;
                if (!$m->save()) {
                    throw new UserException('保存挂载商品失败');
                }
            }
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
    }

    /** 后台编辑用:这篇文章挂了哪些商品(含下架的,让运营看得到并能移除),按排序 */
    public static function forAdmin(int $articleId, int $mallId): array
    {
        $rows = ArticleGoods::find()->where(['article_id' => $articleId, 'mall_id' => $mallId, 'is_delete' => 0])
            ->orderBy('sort asc, id asc')->asArray()->all();
        if (!$rows) {
            return [];
        }
        $goods = Goods::find()->select(['id', 'name', 'cover_pic', 'price', 'status', 'is_delete', 'stock'])
            ->where(['id' => array_column($rows, 'goods_id')])->indexBy('id')->asArray()->all();
        $out = [];
        foreach ($rows as $r) {
            $g = $goods[$r['goods_id']] ?? null;
            $out[] = [
                'id'        => (int)$r['goods_id'],
                'name'      => $g ? $g['name'] : '(商品已删除)',
                'cover_pic' => $g ? $g['cover_pic'] : '',
                'price'     => $g ? $g['price'] : '',
                'recommend' => $r['recommend'],
                'is_main'   => (int)$r['is_main'],
                'offline'   => (!$g || (int)$g['is_delete'] === 1 || (int)$g['status'] !== 1) ? 1 : 0,
            ];
        }
        return $out;
    }

    /**
     * C 端用:一批文章各自挂的「能卖」的商品。返回 [article_id => [商品行, ...]],商品行已带会员价。
     *
     * @param int[] $articleIds
     */
    public static function sellableByArticles(array $articleIds, int $mallId, int $levelId): array
    {
        $articleIds = array_values(array_unique(array_filter(array_map('intval', $articleIds))));
        if (!$articleIds) {
            return [];
        }
        $links = ArticleGoods::find()->where(['article_id' => $articleIds, 'mall_id' => $mallId, 'is_delete' => 0])
            ->orderBy('article_id asc, sort asc, id asc')->asArray()->all();
        if (!$links) {
            return [];
        }
        $q = Goods::find()->select(['id', 'name', 'cover_pic', 'price', 'is_attr', 'stock'])
            ->where(['id' => array_unique(array_column($links, 'goods_id')), 'mall_id' => $mallId, 'status' => 1, 'is_delete' => 0, 'type' => 'normal'])
            ->andWhere(['>', 'stock', 0]);
        $rows = $q->indexBy('id')->asArray()->all();
        if (!$rows) {
            return [];
        }
        // 多规格商品:展示最低规格价,有不同价就标「起」
        $attrIds = array_keys(array_filter($rows, function ($g) { return (int)$g['is_attr'] === 1; }));
        $attrMin = [];
        if ($attrIds) {
            foreach (Attr::find()->select(['goods_id', 'price'])->where(['goods_id' => $attrIds, 'is_delete' => 0])->asArray()->all() as $a) {
                $attrMin[(int)$a['goods_id']][] = (float)$a['price'];
            }
        }
        $list = array_values($rows);
        foreach ($list as &$g) {
            $ps = $attrMin[(int)$g['id']] ?? [];
            if ($ps) {
                $g['price'] = number_format(min($ps), 2, '.', '');
                $g['price_multi'] = count(array_unique($ps)) > 1 ? 1 : 0;
            } else {
                $g['price_multi'] = 0;
            }
        }
        unset($g);
        GoodsMemberPriceHelper::applyToArrayList($list, $levelId);
        $byId = [];
        foreach ($list as $g) {
            $byId[(int)$g['id']] = $g;
        }

        $out = [];
        foreach ($links as $l) {
            $g = $byId[(int)$l['goods_id']] ?? null;
            if (!$g) {
                continue;
            }
            $out[(int)$l['article_id']][] = [
                'id'          => (int)$g['id'],
                'name'        => $g['name'],
                'cover_pic'   => $g['cover_pic'],
                'price'       => $g['price'],
                'price_multi' => $g['price_multi'],
                'recommend'   => $l['recommend'],
                'is_main'     => (int)$l['is_main'],
            ];
        }
        // 主推被下架时,让第一件顶上
        foreach ($out as $aid => &$items) {
            if (!array_filter($items, function ($i) { return $i['is_main']; })) {
                $items[0]['is_main'] = 1;
            }
        }
        unset($items);
        return $out;
    }
}
