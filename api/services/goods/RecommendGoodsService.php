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
namespace app\services\goods;

use app\helpers\GoodsMemberPriceHelper;
use app\models\Goods;
use app\models\GoodsFootmark;
use yii\db\Expression;
use yii\db\Query;

/** 最近浏览与商城推荐交替排列；先去重再分页，不把用户足迹写入公共缓存。 */
class RecommendGoodsService
{
    public function getList(int $mallId, int $userId, int $page, int $limit, int $excludeId = 0): array
    {
        $base = Goods::find()->alias('g')->where([
            'g.mall_id' => $mallId, 'g.status' => Goods::STATUS_UP,
            'g.is_check' => 1, 'g.is_delete' => 0,
        ]);
        if ($condition = StandaloneGoodsGuard::listCondition('g.type')) {
            $base->andWhere($condition);
        }
        if ($excludeId) $base->andWhere(['<>', 'g.id', $excludeId]);

        $recommended = (clone $base)->andWhere(['g.is_recommend' => 1])->select([
            'id' => 'g.id',
            'position' => new Expression('ROW_NUMBER() OVER (ORDER BY g.sort ASC, g.id DESC) * 2'),
        ]);
        $query = (clone $base)->leftJoin(['r' => $recommended], 'r.id = g.id');
        if ($userId > 0) {
            // 老数据即使有重复足迹，同一商品也只生成一个浏览排名。
            $history = (new Query())->from(GoodsFootmark::tableName())->where([
                'mall_id' => $mallId, 'user_id' => $userId, 'is_delete' => 0,
            ])->select([
                'goods_id',
                'last_view_at' => new Expression('MAX(CASE WHEN COALESCE(updated_at, 0) > COALESCE(created_at, 0) THEN updated_at ELSE COALESCE(created_at, 0) END)'),
            ])->groupBy('goods_id');
            $viewed = (clone $base)->innerJoin(['f' => $history], 'f.goods_id = g.id')->select([
                'id' => 'g.id',
                'position' => new Expression('ROW_NUMBER() OVER (ORDER BY f.last_view_at DESC, g.id DESC) * 2 - 1'),
            ]);
            $query->leftJoin(['h' => $viewed], 'h.id = g.id')
                ->andWhere(['or', ['not', ['h.id' => null]], ['not', ['r.id' => null]]]);
            // 双来源商品取较早位置；两组的奇偶位置各自唯一，翻页稳定。
            $position = 'CASE WHEN h.position IS NULL THEN r.position WHEN r.position IS NULL THEN h.position WHEN h.position < r.position THEN h.position ELSE r.position END';
        } else {
            $query->andWhere(['not', ['r.id' => null]]);
            $position = 'r.position';
        }

        $list = $query->page($pagination, $limit, $page)->select([
            'g.id', 'g.name', 'g.cover_pic', 'g.price', 'g.origin_price',
            'g.sales', 'g.virtual_sales', 'g.unit', 'g.is_negotiable',
        ])->orderBy(new Expression($position . ' ASC, g.id DESC'))->asArray()->all();
        GoodsMemberPriceHelper::applyToArrayList($list, GoodsMemberPriceHelper::currentLevelId());
        return ['list' => $list, 'pagination' => $pagination];
    }
}
