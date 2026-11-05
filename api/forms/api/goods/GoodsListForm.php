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

namespace app\forms\api\goods;

use app\core\ApiCode;
use app\helpers\JobHelper;
use app\helpers\MallSettingHelper;
use app\jobs\goods\SearchLogJob;
use app\models\BaseModel;
use app\models\Goods;
use app\models\GoodsCat;
use app\models\GoodsInfo;
use Yii;
use yii\helpers\Json;

class GoodsListForm extends BaseModel
{
    // --- 筛选参数 ---
    public $keyword;
    public $cat_id;
    public $tab_value; // 用于首页Tab筛选 (recommend, hot_sales, property_xxx)

    // --- 排序参数 ---
    public $sort_by;   // 排序键 (sales, price, composite)
    public $sort_order = 'desc';

    // --- 分页参数 ---
    public $page = 1;
    public $limit = 10;

    // --- 兼容旧参数 ---
    public $is_recommend;
    public $is_special_price;
    public $property;
    public $coupon_id;
    public $sub_cat_id;

    public function rules()
    {
        return [
            [['page', 'limit', 'is_recommend', 'is_special_price', 'coupon_id', 'cat_id'], 'integer'],
            [['keyword', 'tab_value', 'sort_by', 'sort_order'], 'string'],
            [['property', 'sub_cat_id'], 'safe'],
            [['limit'], 'default', 'value' => 10],
            [['page'], 'default', 'value' => 1],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        if ($this->keyword) {
            if (Yii::$app->user->isGuest) {
                $userId = 0;
            } else {
                $userId = Yii::$app->user->id;
            }
            JobHelper::push(new SearchLogJob([
                'mall_id' => Yii::$app->mall->id,
                'user_id' => $userId,
                'keyword' => $this->keyword,
            ]));
        }
        $query = Goods::find()->alias('g')->where([
            'g.mall_id' => $this->mall_id,
            'g.is_delete' => 0,
            'g.status' => Goods::STATUS_UP,
            // 未过审的商品不得出现在 C 端列表/搜索。这正是核心放开上传拦截时写下的约定:
            // BaseGoodsEditForm「移除 is_check 拦截机制，允许未审核商品先『标称上架』但客户端不显示」——
            // 约定的后半句此前只有首页/推荐/店铺内列表实现了,主列表和搜索漏了,
            // 于是商家上传后自己点上架就能被搜到、点进去、下单,平台审核形同虚设。
            'g.is_check' => 1,
            'g.type' => Goods::TYPE_NORMAL
        ]);


        // --- 1. 处理所有筛选 (FILTERING) ---
        $this->applyFilters($query);

        // --- 2. 处理排序 (ORDERING) ---
        $this->applySorting($query);

        // --- 3. 查询与返回 ---
        $selectFields = [
            'g.id', 'g.name', 'g.subtitle', 'g.cover_pic',
            // virtual_sales 只是**多下发一个字段**,sales 的含义和既有调用方全部不变 ——
            // 分类主题页要展示的是虚拟销量(运营填的),没填时前端回退真实 sales。
            // 不在这里做 sales = virtual_sales 的替换:那会改掉所有列表接口的语义。
            // is_recommend / is_special_price 供列表打角标。用商品自己的真实标记,
            // 不在前端按销量之类现编「热卖」——编出来的标签运营改不了、也对不上后台。
            'g.price', 'g.origin_price', 'g.sales', 'g.virtual_sales', 'g.is_negotiable', 'g.unit',
            'g.is_recommend', 'g.is_special_price'
        ];

        $list = $query->select($selectFields)
            ->page($pagination, $this->limit, $this->page)
            ->asArray()
            ->all();

        // --- 4. 商品权限判断与二次处理 (保持您原有逻辑) ---
        $newList = $this->filterByPermission($list);

        // 补会员价/插件代理价(与详情页同口径;批量 with('info')+Level 请求级缓存,无 N+1)
        \app\helpers\GoodsMemberPriceHelper::applyToArrayList(
            $newList,
            \app\helpers\GoodsMemberPriceHelper::currentLevelId()
        );

        // [新增] 获取并返回热门搜索词
        $hot_tags = [];
        $propertyString = MallSettingHelper::get('goods_property', $this->mall_id);
        if ($propertyString) {
            $properties = explode(',', $propertyString);
            foreach ($properties as $prop) {
                if (trim($prop)) $hot_tags[] = ['name' => trim($prop)];
            }
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'list' => $newList,
            'pagination' => $pagination,
            'hot_tags' => $hot_tags,
        ]);
    }

    /**
     * [新增] 辅助方法：应用所有筛选条件
     * @param \yii\db\ActiveQuery $query
     */
    private function applyFilters(&$query)
    {
        // a. 按分类筛选:选了具体子分类以子分类为准;sub_cat_id 为 'all' 或根本没传,都按 cat_id 筛。
        //    之前 cat_id 只在 sub_cat_id=='all' 时才生效,goods-list 页(分类页点分类、「查看更多」、
        //    装修链接选「分类」)只带 cat_id,分类条件被整个跳过,返回的是全部商品。
        //    goods_cat 存的是整条祖先链(Goods::afterSave 逐级往上写),按父分类 id 筛就能带出
        //    所有子孙分类的商品;每个商品对同一 cat_id 只有一行,join 不会出重复。
        if ($this->sub_cat_id && $this->sub_cat_id != 'all') {
            $query->innerJoinWith('goodsCat gcat')->andWhere(['gcat.cat_id' => $this->sub_cat_id]);
        } elseif ($this->cat_id) {
            $query->innerJoinWith('goodsCat gcat')->andWhere(['gcat.cat_id' => $this->cat_id]);
        }

        // b. 按首页 Tab 的 value 进行筛选
        if ($this->tab_value) {
            if (strpos($this->tab_value, 'property_') === 0) {
                $propertyValue = substr($this->tab_value, 9);
                $query->andWhere(['like', 'g.property', $propertyValue]);
            }
        }

        // c. 按关键词筛选
        if ($this->keyword) {
            $query->andWhere(['like', 'g.name', $this->keyword]);
        }

        // d. 其他筛选 (保留您的原有逻辑)
        if ($this->is_special_price) $query->andFilterWhere(['g.is_special_price' => $this->is_special_price]);
        if ($this->is_recommend && !$this->tab_value) $query->andFilterWhere(['g.is_recommend' => $this->is_recommend]);
    }

    /**
     * [新增] 辅助方法：应用所有排序条件
     * @param \yii\db\ActiveQuery $query
     */
    private function applySorting(&$query)
    {
        // a. 优先使用用户指定的排序方式
        if ($this->sort_by === 'sales') {
            $order = $this->sort_order === 'asc' ? SORT_ASC : SORT_DESC;
            $query->orderBy(['g.sales' => $order]);
        } elseif ($this->sort_by === 'price') {
            $order = $this->sort_order === 'asc' ? SORT_ASC : SORT_DESC;
            $query->orderBy(['g.price' => $order]);
        } else {
            // b. 如果用户未指定排序，则根据 Tab 类型应用默认排序
            if ($this->tab_value === 'recommend') {
                $this->applyRecommendSorting($query);
            } elseif ($this->tab_value === 'hot_sales') {
                $query->orderBy(['g.sales' => SORT_DESC]);
            } else {
                $query->orderBy(['g.sort' => SORT_ASC]);
            }
        }
        // c. 增加一个最终的保底排序，确保顺序稳定
        $query->addOrderBy(['g.id' => SORT_DESC]);
    }

    /**
     * [新增] 辅助方法：应用“猜你喜欢”的复杂排序/筛选逻辑
     * @param \yii\db\ActiveQuery $query
     */
    private function applyRecommendSorting(&$query)
    {
        $userId = !Yii::$app->user->isGuest ? Yii::$app->user->id : null;
        $footmarkGoodsIds = [];
        if ($userId) {
            $footmarkGoodsIds = \app\models\GoodsFootmark::find()
                ->where(['user_id' => $userId, 'is_delete' => 0])
                ->orderBy(['created_at' => SORT_DESC])
                ->select('goods_id')->column();
        }

        if (!empty($footmarkGoodsIds)) {
            $catIds = GoodsCat::find()->where(['goods_id' => $footmarkGoodsIds])->select('cat_id')->distinct()->column();
            if (!empty($catIds)) {
                $query->innerJoinWith('goodsCat gcat2')->andWhere(['gcat2.cat_id' => $catIds]);
            }
            $query->orderBy(['g.sales' => SORT_DESC, 'g.is_recommend' => SORT_DESC]);

        } else {
            $query->orderBy(['g.is_recommend' => SORT_DESC, 'g.sort' => SORT_ASC]);
        }

    }

    /**
     * [保留] 您的商品权限过滤逻辑
     * @param array $list
     * @return array
     */
    private function filterByPermission(array $list): array
    {
        if (Yii::$app->user->isGuest) {
            $level = -1;
        } else {
            $level = Yii::$app->user->identity->level;
        }

        $newList = [];
        $goods_ids = array_column($list, 'id');
        if (empty($goods_ids)) return [];

        $goodsInfoMap = GoodsInfo::find()->where(['goods_id' => $goods_ids])->indexBy('goods_id')->asArray()->all();

        foreach ($list as $goods) {
            $goodsInfo = $goodsInfoMap[$goods['id']] ?? null;
            $canView = true;
            if ($goodsInfo && !empty($goodsInfo['permission'])) {
                $permission = Json::decode($goodsInfo['permission']);
                if (!empty($permission['explode_is_level'])) {
                    if (!in_array($level, $permission['explode_level_ids'] ?? [])) {
                        $canView = false;
                    }
                }
            }
            if ($canView) {
                $newList[] = $goods;
            }
        }
        return $newList;
    }
}