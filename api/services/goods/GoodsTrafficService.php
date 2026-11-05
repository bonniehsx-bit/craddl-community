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

use app\models\Goods;
use app\models\GoodsFootmark;
use yii\db\Expression;
use yii\db\Query;

/** 商品流量只读排行，与用户足迹累计次数共用同一数据源。 */
class GoodsTrafficService
{
    public function getList(int $mallId, int $page, int $limit, string $keyword = '', int $status = -1): array
    {
        $stats = (new Query())->from(GoodsFootmark::tableName())->where([
            'mall_id' => $mallId, 'is_delete' => 0,
        ])->andWhere(['>', 'user_id', 0])->groupBy('goods_id')->select([
            'goods_id', 'view_count' => new Expression('SUM(view_count)'),
            'visitor_count' => new Expression('COUNT(DISTINCT user_id)'),
            'last_view_at' => new Expression('MAX(CASE WHEN COALESCE(updated_at, 0) > COALESCE(created_at, 0) THEN updated_at ELSE COALESCE(created_at, 0) END)'),
        ]);
        // 范围与商品管理默认列表保持一致；无浏览的商品保留，排在有流量的商品之后。
        $query = Goods::find()->alias('g')->where([
            'g.mall_id' => $mallId, 'g.is_delete' => 0, 'g.type' => Goods::TYPE_NORMAL,
        ])->leftJoin(['f' => $stats], 'f.goods_id = g.id');
        if ($status !== -1) $query->andWhere(['g.status' => $status]);
        if ($keyword !== '') {
            $condition = ['or', ['like', 'g.name', $keyword]];
            if (ctype_digit($keyword)) $condition[] = ['g.id' => $keyword];
            $query->andWhere($condition);
        }
        $list = $query->page($pagination, $limit, $page)->select([
            'g.id', 'g.name', 'g.cover_pic', 'g.status',
            'view_count' => new Expression('COALESCE(f.view_count, 0)'),
            'visitor_count' => new Expression('COALESCE(f.visitor_count, 0)'),
            'last_view_at' => new Expression('COALESCE(f.last_view_at, 0)'),
        ])->orderBy(new Expression('COALESCE(f.view_count, 0) DESC, COALESCE(f.visitor_count, 0) DESC, g.id DESC'))->asArray()->all();
        foreach ($list as &$item) {
            foreach (['id', 'status', 'view_count', 'visitor_count', 'last_view_at'] as $key) $item[$key] = (int) $item[$key];
            $item['last_view_text'] = $item['last_view_at'] ? date('Y-m-d H:i:s', $item['last_view_at']) : '';
        }
        unset($item);
        return ['list' => $list, 'pagination' => $pagination];
    }
}
