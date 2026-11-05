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

namespace app\plugins\mch\forms\mall;

use app\helpers\ResponseHelper;
use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Goods;
use app\plugins\mch\models\Mch;

class GoodsListForm extends BaseModel
{
    public $page = 1;
    public $limit = 20;
    public $keyword;      // 搜索商品名称、ID
    public $mch_keyword;  // 搜索商户名称、ID、手机号
    public $status;       // 上架状态: 0=下架, 1=上架
    public $is_check;     // 审核状态: 0=待审核, 1=已通过, 2=已驳回
    public $sort_prop;    // 排序字段
    public $sort_order;   // 排序顺序

    public function rules()
    {
        return [
            [['page', 'limit', 'status', 'is_check'], 'integer'],
            [['keyword', 'mch_keyword', 'sort_prop', 'sort_order'], 'string'],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, null, ['errors' => $this->getErrors()]);
        }

        $query = Goods::find()->alias('g')->where([
            'g.is_delete' => 0,
            'g.mall_id' => \Yii::$app->mall->id,
            'g.type' => Goods::TYPE_NORMAL,
        ])->andWhere(['>', 'g.mch_id', 0]); // 只查询商户商品

        // [核心] 关联商户表
        $query->leftJoin(['m' => Mch::tableName()], 'g.mch_id = m.id');

        // 商品关键词搜索
        if ($this->keyword) {
            $query->andWhere([
                'OR',
                ['like', 'g.name', $this->keyword],
                ['=', 'g.id', $this->keyword],
            ]);
        }

        // 商户关键词搜索
        if ($this->mch_keyword) {
            $query->andWhere([
                'OR',
                ['like', 'm.mch_name', $this->mch_keyword],
                ['=', 'm.id', $this->mch_keyword],
                ['like', 'm.mobile', $this->mch_keyword],
            ]);
        }

        // 上架状态筛选
        if ($this->status !== '' && $this->status !== null) {
            if ($this->status == 2) { // 兼容前端传来的“售罄”状态
                $query->andWhere(['<=', 'g.stock', 0]);
            } else {
                $query->andWhere(['g.status' => $this->status]);
            }
        }

        // 审核状态筛选
        if ($this->is_check !== '' && $this->is_check !== null) {
            $query->andWhere(['g.is_check' => $this->is_check]);
        }

        // 排序
        $orderBy = 'g.sort ASC, g.id DESC';
        if ($this->sort_prop) {
            $order = $this->sort_order === 'ascending' ? 'ASC' : 'DESC';
            $allowedSortProps = ['id', 'created_at', 'sales', 'price', 'stock', 'sort'];
            if (in_array($this->sort_prop, $allowedSortProps)) {
                $orderBy = 'g.' . $this->sort_prop . ' ' . $order;
            }
        }

        $list = $query->with('mch') // [性能] 使用 with 预加载商户信息
        ->select('g.*')
            ->orderBy($orderBy)
            ->page($pagination, $this->limit, $this->page)
            ->all();

        // 格式化数据
        $list = array_map(function ($goods) {
            return [
                'id' => $goods->id,
                'name' => $goods->name,
                'cover_pic' => $goods->cover_pic,
                'price' => $goods->price,
                'stock' => $goods->stock,
                'sales' => $goods->sales,
                'status' => $goods->status,
                'is_check' => $goods->is_check,
                'check_remark' => $goods->info ? $goods->info->remarks : '', // 假设驳回理由存在info表
                'created_at' => date('Y-m-d H:i:s', $goods->created_at),
                'mch' => [
                    'id' => $goods->mch->id,
                    'mch_name' => $goods->mch->mch_name,
                    'logo' => $goods->mch->logo,
                ]
            ];
        }, $list);

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '获取成功', [
            'list' => $list,
            'pagination' => $pagination,
        ]);
    }
}