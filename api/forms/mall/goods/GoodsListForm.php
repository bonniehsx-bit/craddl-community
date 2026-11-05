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

namespace app\forms\mall\goods;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Cat;
use app\models\Goods;
use app\models\GoodsCat;
use app\plugins\mch\models\Mch;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

class GoodsListForm extends BaseModel
{
    public $page;
    public $limit;
    public $status;
    public $keyword;
    public $search_date;
    public $is_recommend;
    public $cat_id;
    public $is_special_price;
    public $property;
    public $cat_ids;

    public $is_check;
    public $mch_name;
    public $source;
    public $is_export;
    public $type;
    /** 只看库存预警商品(供后台首页「库存预警」卡片点进来) */
    public $low_stock;

    public function rules()
    {
        return [
            [['property'], 'safe'],
            [['status'], 'default', 'value' => 1],
            [['is_check'], 'default', 'value' => -1],
            [['is_export'], 'default', 'value' => 0],
            [['page', 'limit', 'status', 'is_recommend', 'cat_id', 'is_special_price', 'is_check', 'low_stock'], 'integer'],
            // 将 source 加入验证规则
            [['keyword', 'source', 'mch_name', 'type'], 'string'],
            [['search_date', 'cat_ids'], 'safe'],
            [['limit'], 'default', 'value' => 10]
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // 核心修改点 1: 使用 with(['cat', 'mch']) 预加载关联数据
        $query = Goods::find()->alias('g')->with(['cat', 'mch'])->where(['g.mall_id' => $this->mall_id, 'g.is_delete' => 0]);
        
        if ($this->type === 'all') {
            // 显式"全部类型"：不加 type 过滤（供等级升级-购买指定商品等场景使用）
        } elseif ($this->type) {
            $query->andWhere(['g.type' => $this->type]);
        } else {
            $query->andWhere(['g.type' => [Goods::TYPE_NORMAL]]);
        }
        
        // 库存预警筛选。阈值与后台首页那张卡片共用 Goods::LOW_STOCK_THRESHOLD,
        // 两边各写一个 10 的话,点卡片进来数量对不上,而且没人会想到去比这两个数。
        if (!empty($this->low_stock)) {
            $query->andWhere(['<', 'g.stock', Goods::LOW_STOCK_THRESHOLD]);
        }

        // 显式处理 status，避免歧义
        if (isset($this->status) && $this->status != -1) {
            $query->andWhere(['g.status' => $this->status]);
        }

        if ($this->is_check != -1) {
            $query->andWhere(['is_check' => $this->is_check]);
        }

        // 核心修改点 2: 新增商品来源的筛选逻辑
        if (isset($this->source) && $this->source !== '') {
            if ($this->source == '0') { // 0 代表平台自营
                $query->andWhere(['mch_id' => 0]);
            }
            if ($this->source == '1') { // 1 代表商家商品
                $query->andWhere(['>', 'mch_id', 0]);
            }
        }

        if ($this->cat_id) {
            $goodsIds = GoodsCat::find()->andWhere(['cat_id' => $this->cat_id, 'is_delete' => 0, 'mall_id' => $this->mall_id])->select('goods_id')->column();
            $query->andWhere(['id' => $goodsIds]);
        }
        if ($this->cat_ids && is_array($this->cat_ids) && count($this->cat_ids)) {
            $goodsIds = GoodsCat::find()->andWhere(['cat_id' => $this->cat_ids, 'is_delete' => 0, 'mall_id' => $this->mall_id])->select('goods_id')->column();
            $query->andWhere(['id' => $goodsIds]);
        }
        if ($this->is_special_price) {
            $query->andFilterWhere(['is_special_price' => $this->is_special_price]);
        }
        if ($this->is_recommend) {
            $query->andFilterWhere(['is_recommend' => $this->is_recommend]);
        }
        if ($this->search_date && count($this->search_date)) {
            $start_date = strtotime($this->search_date[0]);
            $end_date = strtotime($this->search_date[1]);
            $query->andWhere(['>', 'created_at', $start_date])->andWhere(['<', 'created_at', $end_date]);
        }
        if ($this->property && !is_array($this->property)) {
            if (strpos($this->property, '[') !== false) {
                $this->property = Json::decode($this->property);
            } else {
                $this->property = explode(',', $this->property);
            }
        }
        if (!empty($this->mch_name)) {
            // 使用 joinWith 关联 Mch 模型，并为关联表设置别名 'm'
            // joinWith 会自动处理关联关系，比手动 leftJoin 更符合 Yii 的规范
            $query->joinWith('mch m');
            $query->andWhere(['like', 'm.mch_name', $this->mch_name]);
        }
        if ($this->keyword) {
            $query->andWhere([
                'or',
                ['like', 'g.name', $this->keyword],
                ['like', 'g.property', $this->keyword],
                ['g.id' => $this->keyword]
            ]);
        }
        if ($this->property && is_array($this->property)) {
            $str = '';
            foreach ($this->property as $item) {
                if ($str != '') {
                    $str .= " or `property` LIKE '%{$item}%'";
                } else {
                    $str .= "`property` LIKE '%{$item}%'";
                }
            }
            $query->andWhere(new Expression($str));
        }

        $pagination = null;

        if ($this->is_export) {
            $list = $query
                ->orderBy('sort ASC, id DESC')
                ->asArray()
                ->all();
        } else {
            $list = $query->page($pagination, $this->limit, $this->page)
                ->orderBy('sort ASC, id DESC')
                ->asArray()
                ->all();
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['list' => $list, 'pagination' => $pagination]);
    }
}
