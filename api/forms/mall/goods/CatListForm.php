<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-09-27
 * Time: 10:20
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

class CatListForm extends BaseModel
{
    public $page;
    public $limit;
    public $status;
    public $keyword;
    public $mch_id;
    public $type;

    public $parent_cat_id;

    public function rules()
    {
        return [
            [['page', 'limit', 'status', 'mch_id', 'type', 'parent_cat_id'], 'integer'],
            [['keyword'], 'string'],
            [['limit'], 'default', 'value' => 10]
        ];
    }

    /**
     * [核心修改] 获取分类列表，适配两级展开结构
     * @return array
     */
    public function getTreeList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $query = Cat::find()->where([
            'mall_id' => \Yii::$app->mall->id,
            'is_delete' => 0,
            'mch_id' => 0,
        ]);

        if ($this->keyword) {

            // 如果有关键词，返回扁平的搜索结果
            $list = $query->andWhere(['like', 'name', $this->keyword, false]) // 优化模糊查询
                ->orderBy('sort ASC, id DESC')
                ->asArray()
                ->all();
        } else {
            // 如果没有关键词，构建并返回两级结构
            $allCats = $query->orderBy('sort ASC, id DESC')->asArray()->all();
            $list = $this->buildTwoLevelTree($allCats);
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '请求成功', ['list' => $list]);
    }

    /**
     * [核心修改] 新增一个方法，专门用于构建两级树状结构
     * @param array $allCats 所有分类的扁平数组
     * @return array
     */
    private function buildTwoLevelTree(array $allCats)
    {
        $parents = [];
        $childrenMap = [];

        // 1. 先将所有分类按父ID分组
        foreach ($allCats as $cat) {
            if ($cat['parent_id'] != 0) {
                $childrenMap[$cat['parent_id']][] = $cat;
            }
        }

        // 2. 遍历所有分类，找出顶级分类，并挂载子分类
        foreach ($allCats as $cat) {
            if ($cat['parent_id'] == 0) {
                if (isset($childrenMap[$cat['id']])) {
                    $cat['children'] = $childrenMap[$cat['id']];
                    $cat['hasChildren'] = true; // 告知前端此节点有子节点可展开
                } else {
                    $cat['children'] = [];
                    $cat['hasChildren'] = false;
                }
                $parents[] = $cat;
            }
        }

        return $parents;
    }


    public function getList()
    {

        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $query = Cat::find()
            ->where(['mall_id' => \Yii::$app->mall->id, 'is_delete' => 0, 'mch_id' => 0]);
        if ($this->keyword) {
            $query->andWhere(['like', 'name', $this->keyword]);
        }
        if ($this->mch_id) {
            $query->andWhere(['mch_id' => $this->mch_id]);
        }
        $list = $query->orderBy('id DESC')->page($pagination, $this->limit, $this->page)->asArray()->all();
        foreach ($list as &$item) {

            $parent = Cat::getOne($item['parent_id']);
            $item['parent_name'] = '无上级分类';
            if ($parent) {
                $item['parent_name'] = $parent->name;
            }
        }
        unset($item);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['list' => $list, 'pagination' => $pagination]);
    }


    public function query()
    {

        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $query = Cat::find()
            ->where(['mall_id' => \Yii::$app->mall->id, 'is_delete' => 0]);
        if ($this->mch_id) {
            $query->andWhere(['mch_id' => $this->mch_id]);
        }
        if ($this->keyword) {
            $query->andWhere(['like', 'name', $this->keyword]);
        }
        $list = $query->orderBy('id DESC')->asArray()->all();
        foreach ($list as &$item) {
            $exist = Cat::find()->where(['is_delete' => 0, 'parent_id' => $item['id']])->exists();
            $item['is_child'] = $exist;
        }
        unset($item);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['list' => $list]);
    }
}
