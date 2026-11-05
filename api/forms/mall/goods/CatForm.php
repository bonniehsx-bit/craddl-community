<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-09-10
 * Time: 22:14
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
use app\helpers\OptionHelper;
use app\helpers\ResponseHelper;
use app\models\BaseModel;
use app\models\Cat;

class CatForm extends BaseModel
{
    public $id;
    public $name;
    public $mall_id;
    public $cover_pic;
    public $adv_pic;
    public $is_show;
    public $type; // 几级分类
    public $link;
    public $sort;
    public $parent_id;



    public function rules()
    {
        return [
            [['is_show', 'type', 'sort', 'id', 'parent_id'], 'integer'],
            [['name'], 'string', 'max' => 45],
            [['cover_pic', 'adv_pic'], 'string', 'max' => 255],
            [['link'], 'string', 'max' => 128],
            [['is_show'], 'default', 'value' => 1],
            [['sort'], 'default', 'value' => 100],
            [['parent_id'], 'default', 'value' => 0],

        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        if (empty($this->name)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '分类名称不能为空');
        }

        if ($this->id && $this->id == $this->parent_id) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '不能将分类设置为自己的子分类');
        }

        $parentCat = null;
        if ($this->parent_id) {
            $parentCat = Cat::findOne(['id' => $this->parent_id, 'is_delete' => 0, 'mall_id' => \Yii::$app->mall->id]);
            if (!$parentCat) {
                return $this->apiResponse(ApiCode::CODE_FAIL, '指定的上级分类不存在！');
            }
            if ($parentCat->type >= 1) {
                return $this->apiResponse(ApiCode::CODE_FAIL, '系统最多支持二级分类，无法为二级分类添加子分类！');
            }
        }

        $cat = null;
        if ($this->id) {
            $cat = Cat::findOne(['is_delete' => 0, 'id' => $this->id, 'mall_id' => \Yii::$app->mall->id]);
            if (!$cat) {
                return $this->apiResponse(ApiCode::CODE_FAIL, '该分类不存在');
            }
        } else {
            $cat = new Cat();
            $cat->mall_id = \Yii::$app->mall->id;
        }

        // 批量赋值, Yii2 会自动处理新增的字段
        $cat->attributes = $this->attributes;
        $cat->parent_id = $this->parent_id ?: 0;
        $cat->type = $parentCat ? 1 : 0;


        if (!$cat->save()) {
            return $this->responseErrorMsg($cat);
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '保存成功');
    }

    public function search()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $cat = Cat::findOne(['id' => $this->id, 'is_delete' => 0, 'mall_id' => \Yii::$app->mall->id]);
        if (!$cat) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '分类不存在');
        }
        $parentCat = null;
        if ($cat->parent_id) {
            $parentCat = Cat::findOne(['id' => $cat->parent_id, 'is_delete' => 0]);
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['cat' => $cat, 'parent_cat' => $parentCat]);
    }

    public function showChange()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $cat = Cat::findOne(['id' => $this->id, 'is_delete' => 0, 'mall_id' => \Yii::$app->mall->id]);
        if (!$cat) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '分类不存在');
        }
        $cat->is_show = $this->is_show;
        if (!$cat->save()) {
            return $this->responseErrorMsg($cat);
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '操作成功');
    }

    public function delete()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $cat = Cat::findOne(['id' => $this->id, 'is_delete' => 0, 'mall_id' => \Yii::$app->mall->id]);
        if (!$cat) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '分类不存在！');
        }

        $is_exist = Cat::find()->where(['parent_id' => $this->id, 'is_delete' => 0])->exists();
        if ($is_exist) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '删除失败，该分类下存在子分类！');
        }

        $cat->is_delete = 1;
        if (!$cat->save()) {
            return $this->responseErrorMsg($cat);
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '删除成功！');
    }
}
