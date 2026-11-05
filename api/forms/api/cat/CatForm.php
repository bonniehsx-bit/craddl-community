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

namespace app\forms\api\cat;

use app\services\goods\StandaloneGoodsGuard;
use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Cat;

class CatForm extends BaseModel
{

    /**
     * [新接口] 获取用于分类页的分类树 (只含一、二级)
     * @return array
     */
    public function getCatTree()
    {
        $list = Cat::find()
            ->where([
                'mall_id' => \Yii::$app->mall->id,
                'is_delete' => 0,
                'parent_id' => 0,
                'is_show' => 1
            ])
            ->with(['childs' => function ($query) {
                $query->where(['is_delete' => 0, 'is_show' => 1])->orderBy('sort DESC');
            }])
            ->orderBy('sort DESC')
            ->asArray()
            ->all();

        // [重要] 为每个一级分类的子分类列表预置商品数据容器
        foreach ($list as &$item) {
            if (isset($item['childs']) && is_array($item['childs'])) {
                foreach ($item['childs'] as &$child) {
                    $child['goods'] = []; // 预先定义，避免前端出错
                }
                unset($child);
            }
        }
        unset($item);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', ['list' => $list]);
    }

    public function search()
    {
        $list = Cat::find()->where(['mall_id' => \Yii::$app->mall->id, 'is_delete' => 0, 'parent_id' => 0])->orderBy('sort desc')->asArray()->all();
        foreach ($list as &$item) {
            $second_list = Cat::find()->where(['mall_id' => \Yii::$app->mall->id, 'is_delete' => 0, 'parent_id' => $item['id']])->orderBy('sort desc')->asArray()->all();
            foreach ($second_list as &$item1) {
                $third_list = Cat::find()->where(['mall_id' => \Yii::$app->mall->id, 'is_delete' => 0, 'parent_id' => $item1['id']])->orderBy('sort desc')->asArray()->all();
                $item1['children'] = $third_list;
            }
            unset($item1);
            $item['children'] = $second_list;
        }
        unset($item);
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['list' => $list]);
    }

    public function search2()
    {
        $list = Cat::find()->where(['mall_id' => \Yii::$app->mall->id, 'is_delete' => 0, 'parent_id' => 0])->orderBy('sort desc')->asArray()->all();
        foreach ($list as &$item) {
            $second_list = Cat::find()->where(['mall_id' => \Yii::$app->mall->id, 'is_delete' => 0, 'parent_id' => $item['id']])->orderBy('sort desc')->asArray()->all();
            $item['children'] = $second_list;
        }
        unset($item);
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['list' => $list]);
    }


    /**
     * [最终优化版] 获取带商品的分类列表 (高性能)
     * @return array
     */
    public function searchCatWithGoods()
    {
        // [核心优化] 使用嵌套 with() 预加载，将 N+1 查询降为 3 次查询
        $list = Cat::find()
            ->where([
                'mall_id' => $this->mall_id,
                'is_delete' => 0,
                'parent_id' => 0,
                'is_show' => 1
            ])
            ->with([
                'childs' => function ($query) {
                    /** @var \app\models\BaseActiveQuery $query */
                    $query->where(['is_delete' => 0, 'is_show' => 1])->orderBy('sort DESC');
                },
                'childs.goods' => function ($query) {
                    /** @var \app\models\BaseActiveQuery $query */
                    $query->where(['is_delete' => 0, 'status' => 1]);
                    // 插件专属商品(来信、充值……)不进商城分类页。⚠ 别用 andFilterWhere:它会吃掉条件里的 type IS NULL 分支
                    if ($c = StandaloneGoodsGuard::listCondition('type')) {
                        $query->andWhere($c);
                    }
                    $query
                        ->select(['id', 'name', 'cover_pic', 'price', 'cat_id']) // [性能] 只选择需要的字段
                        ->orderBy('sort DESC')->limit(6);
                }
            ])
            ->orderBy('sort DESC')
            ->asArray()
            ->all();

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['list' => $list]);
    }
}