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
use app\helpers\ResponseHelper;
use app\models\BaseModel;
use app\models\Cat;

class CatEditForm extends BaseModel
{
    public $id;
    public $mch_id; // [核心] 必须传入商户ID
    public $name;
    public $parent_id;
    public $sort;
    public $is_show;

    public function rules()
    {
        return [
            [['mch_id', 'name'], 'required'],
            [['id', 'mch_id', 'parent_id', 'sort', 'is_show'], 'integer'],
            [['name'], 'string', 'max' => 45],
            [['parent_id'], 'default', 'value' => 0],
            [['sort'], 'default', 'value' => 100],
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        try {
            // [核心] 查找或创建分类时，必须绑定 mch_id
            $cat = Cat::findOne(['id' => $this->id, 'mch_id' => $this->mch_id, 'is_delete' => 0]);

            if (!$cat) {
                // 新增
                $cat = new Cat();
                $cat->mch_id = $this->mch_id;
                $cat->mall_id = \Yii::$app->mall->id;
            }

            // 检查父级分类是否存在且属于同一个商户
            if ($this->parent_id) {
                $parentCat = Cat::findOne(['id' => $this->parent_id, 'mch_id' => $this->mch_id, 'is_delete' => 0]);
                if (!$parentCat) {
                    throw new \Exception('选择的上级分类不存在或不属于该商户');
                }
            }

            $cat->name = $this->name;
            $cat->parent_id = $this->parent_id;
            $cat->sort = $this->sort;
            $cat->is_show = $this->is_show ? 1 : 0;

            if (!$cat->save()) {
                throw new \Exception($this->getErrorMsg($cat));
            }

            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '保存成功', ['detail' => $cat]);

        } catch (\Exception $e) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }
}