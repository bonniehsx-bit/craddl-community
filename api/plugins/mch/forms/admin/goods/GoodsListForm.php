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

namespace app\plugins\mch\forms\admin\goods;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Cat;
use app\models\Goods;
use Yii;

class GoodsListForm extends BaseModel
{
    public $page = 1;
    public $limit = 20;
    public $keyword;
    public $status;     // 0=下架, 1=上架, 2=售罄, -1=全部
    public $is_check;   // 审核状态: 0=待审核, 1=审核通过, 2=审核驳回
    public $cat_id;     // 按分类筛选

    public function rules()
    {
        return [
            [['page', 'limit', 'status', 'is_check', 'cat_id'], 'integer'],
            [['keyword'], 'string'],
        ];
    }

    /**
     * 获取当前店长的商品列表
     * @return array
     */
    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // 通过全局应用组件获取当前商户，MchFilter已确保其存在
        $mch = Yii::$app->mch;

        $query = Goods::find()->where([
            'mall_id' => Yii::$app->mall->id,
            'mch_id' => $mch->id, // [核心] 权限隔离，只查询自己的商品
            'is_delete' => 0,
        ]);
        
        // [安全] 只允许显示普通商品或未标记类型的旧商品，避免 Yuda, Group, Hotel 插件商品混入
        $query->andWhere(['or', ['type' => Goods::TYPE_NORMAL], ['type' => '']]);
        // 关键词搜索 (商品名称、商品ID)
        if ($this->keyword) {
            $query->andWhere([
                'or',
                ['like', 'name', $this->keyword],
                ['=', 'id', $this->keyword],
            ]);
        }

        // 按上下架状态筛选
        if ($this->status !== null && $this->status != -1) {
            if ($this->status == 2) { // 2=售罄
                $query->andWhere(['<=', 'stock', 0]);
            } else {
                $query->andWhere(['status' => $this->status]);
            }
        }

        // 按审核状态筛选
        if ($this->is_check !== null && $this->is_check != -1) {
            $query->andWhere(['is_check' => $this->is_check]);
        }

        // 按分类筛选
        if ($this->cat_id) {
            // [安全] 确保该分类也属于当前商户
            $catExists = Cat::find()->where(['id' => $this->cat_id, 'mch_id' => $mch->id])->exists();
            if ($catExists) {
                $query->andWhere(['cat_id' => $this->cat_id]);
            }
        }

        $pagination = null;
        $list = $query->with('cat') // 预加载商品分类信息
        ->orderBy('sort ASC, id DESC')
            ->page($pagination, $this->limit, $this->page)
            ->asArray()
            ->all();

        // ======================= [核心新增] 开始 =======================
        // 计算所有 Tabs 的角标统计
        $tabsCount = $this->getTabsCount($mch->id);
        // ======================= [核心新增] 结束 =======================

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'list' => $list,
            'pagination' => $pagination,
            'tabs_count' => $tabsCount, // [核心新增] 将统计数据返回给前端
        ]);
    }

    /**
     * [新增] 高效地一次性查询所有Tabs的统计数量
     * @param int $mchId
     * @return array
     */
    private function getTabsCount($mchId)
    {
        // 使用条件聚合查询，一次性从数据库中查出所有状态的数量
        $counts = Goods::find()
            ->where([
                'mall_id' => Yii::$app->mall->id,
                'mch_id' => $mchId,
                'is_delete' => 0,
            ])
            ->andWhere(['or', ['type' => Goods::TYPE_NORMAL], ['type' => '']])
            ->select([
                "COUNT(1) as `全部`",
                "SUM(CASE WHEN `status` = 1 AND `is_check` = 1 AND `stock` > 0 THEN 1 ELSE 0 END) as `在售`",
                "SUM(CASE WHEN `status` = 0 AND `is_check` = 1 AND `stock` > 0 THEN 1 ELSE 0 END) as `下架`",
                "SUM(CASE WHEN `is_check` = 1 AND `stock` <= 0 THEN 1 ELSE 0 END) as `售罄`",
                "SUM(CASE WHEN `is_check` = 0 THEN 1 ELSE 0 END) as `审核中`",
                "SUM(CASE WHEN `is_check` = 2 THEN 1 ELSE 0 END) as `未通过`",
            ])
            ->asArray()
            ->one();

        // 将数据库返回的结果转换为整数，并处理 null 的情况
        return [
            '全部' => (int)($counts['全部'] ?? 0),
            '在售' => (int)($counts['在售'] ?? 0),
            '下架' => (int)($counts['下架'] ?? 0),
            '售罄' => (int)($counts['售罄'] ?? 0),
            '审核中' => (int)($counts['审核中'] ?? 0),
            '未通过' => (int)($counts['未通过'] ?? 0),
        ];
    }
}