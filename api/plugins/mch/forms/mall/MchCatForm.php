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

use app\core\ApiCode;
use app\models\BaseModel;
use app\plugins\mch\models\MchCat;

/**
 * Class MchCatForm
 * @package app\plugins\mch\forms\mall
 *
 * 负责后台商家分类的创建、编辑、删除和查询详情。
 */
class MchCatForm extends BaseModel
{
    public $id;
    public $cat_name;
    public $cover_pic;
    public $order_num;

    public function rules()
    {
        return [
            [['cat_name'], 'required'],
            [['id', 'order_num'], 'integer'],
            [['cat_name'], 'string', 'max' => 45],
            [['cover_pic'], 'string', 'max' => 512],
            [['order_num'], 'default', 'value' => 100],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => '分类ID',
            'cat_name' => '分类名称',
            'cover_pic' => '分类图标',
            'order_num' => '排序',
        ];
    }

    /**
     * 获取单个分类的详情（用于编辑时回显）
     * @return array
     */
    public function search()
    {
        if (!$this->validate(['id'])) {
            return $this->responseErrorInfo();
        }
        $detail = MchCat::findOne(['id' => $this->id, 'mall_id' => $this->mall_id, 'is_delete' => 0]);
        if (!$detail) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '该分类不存在或已被删除');
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', ['detail' => $detail]);
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

        $model = MchCat::findOne(['id' => $this->id, 'mall_id' => $this->mall_id, 'is_delete' => 0]);
        if (!$model) {
            $model = new MchCat();
            $model->mall_id = $this->mall_id;
        }

        $model->attributes = $this->attributes;
        if (!$model->save()) {
            return $this->responseErrorMsg($model);
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '保存成功', ['detail' => $model]);
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

        $model = MchCat::findOne(['id' => $this->id, 'mall_id' => $this->mall_id, 'is_delete' => 0]);
        if (!$model) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '该分类不存在或已被删除');
        }

        // [待办] 检查该分类下是否有关联的商户，如果有关联，则应阻止删除
        // $mchExists = \app\plugins\mch\models\Mch::find()->where(['cat_id' => $this->id, 'is_delete' => 0])->exists();
        // if ($mchExists) {
        //     return $this->apiResponse(ApiCode::CODE_FAIL, '该分类下已有商户，无法删除');
        // }

        $model->is_delete = 1;
        $model->deleted_at = time();
        $model->deleted_time = date('Y-m-d H:i:s');
        if (!$model->save()) {
            return $this->responseErrorMsg($model);
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '删除成功');
    }
}