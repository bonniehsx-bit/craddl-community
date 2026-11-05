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

namespace app\plugins\withdraw\forms\admin\goods;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Goods;
use app\plugins\withdraw\listeners\RechargeGoodsListener;
use app\plugins\withdraw\models\RechargeGoods;
use app\plugins\withdraw\models\RechargePlan;
use Yii;

/**
 * 后台 —— 充值商品列表。
 *
 * 核心商品列表(forms/mall/goods/GoodsListForm)只列 type=normal,
 * 所以充值商品不会混进普通商品里 —— 这正是我们要的,它归本页管理。
 */
class GoodsListForm extends BaseModel
{
    public $page = 1;
    public $limit = 20;
    public $keyword;
    public $status;

    public function rules()
    {
        return [
            [['page', 'limit', 'status'], 'integer'],
            [['keyword'], 'string', 'max' => 64],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $mallId = (int)Yii::$app->mall->id;

        $query = Goods::find()
            ->where([
                'mall_id'   => $mallId,
                'type'      => RechargeGoodsListener::GOODS_TYPE,
                'is_delete' => 0,
            ]);
        if ($this->keyword) {
            $query->andWhere(['like', 'name', $this->keyword]);
        }
        if ($this->status !== null && $this->status !== '') {
            $query->andWhere(['status' => (int)$this->status]);
        }

        $count = (int)$query->count();
        $limit = max(1, min(100, (int)$this->limit));
        $page  = max(1, (int)$this->page);

        $rows = $query->orderBy(['sort' => SORT_ASC, 'id' => SORT_DESC])
            ->offset(($page - 1) * $limit)->limit($limit)->all();

        // 批量预加载配置与档位数(剿灭 N+1)
        $goodsIds = array_map(fn($g) => (int)$g->id, $rows);
        $cfgMap = $goodsIds
            ? RechargeGoods::find()->where(['goods_id' => $goodsIds, 'is_delete' => 0])->indexBy('goods_id')->all()
            : [];
        $planCount = [];
        if ($goodsIds) {
            $rowsCnt = RechargePlan::find()
                ->select(['goods_id', 'c' => 'COUNT(*)'])
                ->where(['goods_id' => $goodsIds, 'is_delete' => 0, 'status' => RechargePlan::STATUS_ON])
                ->groupBy('goods_id')->asArray()->all();
            foreach ($rowsCnt as $r) {
                $planCount[(int)$r['goods_id']] = (int)$r['c'];
            }
        }

        $list = [];
        foreach ($rows as $g) {
            $cfg = $cfgMap[(int)$g->id] ?? null;
            $list[] = [
                'id'          => (int)$g->id,
                'name'        => (string)$g->name,
                'cover_pic'   => (string)$g->cover_pic,
                'status'      => (int)$g->status,
                'sort'        => (int)$g->sort,
                'asset_type'  => $cfg ? (string)$cfg->asset_type : '',
                'asset_label' => $cfg ? $cfg->assetLabel() : '(未配置)',
                'plan_count'  => $planCount[(int)$g->id] ?? 0,
                'created_at'  => $g->created_at ? date('Y-m-d H:i', (int)$g->created_at) : '',
            ];
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'list'  => $list,
            'count' => $count,
        ]);
    }
}
