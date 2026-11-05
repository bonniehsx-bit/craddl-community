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
// [核心] 命名空间已更正

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Cat;
use app\models\Goods;

class MchGoodsCatEditForm extends BaseModel
{
    public $id;
    public $name;
    public $parent_id;
    public $sort;


    public function rules()
    {
        return [
            [['name'], 'required'],
            [['id', 'parent_id', 'sort'], 'integer'],
            [['name'], 'string', 'max' => 45],
            [['parent_id'], 'default', 'value' => 0],
            [['sort'], 'default', 'value' => 100],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => '分类ID',
            'name' => '分类名称',
            'parent_id' => '上级分类',
            'sort' => '排序',
        ];
    }

    /**
     * 保存分类（新增或编辑）
     * @return array
     */
    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $mch = \Yii::$app->mch;
        // MchFilter 已经确保了 $mch 存在，但在这里再做一次检查是更安全的设计
        if (!$mch) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '商户不存在或权限不足');
        }

        if ($this->parent_id) {
            $parentCat = Cat::findOne(['id' => $this->parent_id, 'mch_id' => $mch->id, 'is_delete' => 0]);
            if (!$parentCat) {
                return $this->apiResponse(ApiCode::CODE_FAIL, '选择的上级分类不存在');
            }
        }

        $cat = Cat::findOne(['id' => $this->id, 'mch_id' => $mch->id, 'is_delete' => 0]);
        if (!$cat) {
            // 新增
            $cat = new Cat();
            $cat->mch_id = $mch->id;
            $cat->mall_id = \Yii::$app->mall->id;
        }

        $cat->name = $this->name;
        $cat->parent_id = $this->parent_id;
        $cat->sort = $this->sort;
        // ... 赋值其他字段

        if (!$cat->save()) {
            return $this->responseErrorMsg($cat);
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '保存成功', ['detail' => $cat]);
    }

    /**
     * 删除分类
     * @return array
     */
    public function delete()
    {
        if (!$this->validate(['id'])) {
            return $this->responseErrorInfo();
        }

        $mch = \Yii::$app->mch;
        if (!$mch) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '商户不存在或权限不足');
        }

        $cat = Cat::findOne(['id' => $this->id, 'mch_id' => $mch->id, 'is_delete' => 0]);
        if (!$cat) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '分类不存在或已被删除');
        }

        // [核心] 检查分类下是否有商品
        if (Goods::find()->where(['cat_id' => $this->id, 'is_delete' => 0, 'mch_id' => $mch->id])->exists()) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '该分类下已有商品，无法删除');
        }
        // [核心] 检查分类下是否有子分类
        if (Cat::find()->where(['parent_id' => $this->id, 'is_delete' => 0, 'mch_id' => $mch->id])->exists()) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '请先删除该分类下的子分类');
        }

        $cat->is_delete = 1;
        $cat->deleted_at = time();
        $cat->deleted_time = date('Y-m-d H:i:s');
        if (!$cat->save()) {
            return $this->responseErrorMsg($cat);
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '删除成功');
    }
}