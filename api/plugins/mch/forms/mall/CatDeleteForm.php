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
use app\models\Goods;

class CatDeleteForm extends BaseModel
{
    public $id;
    public $mch_id; // [核心] 必须传入商户ID

    public function rules()
    {
        return [
            [['id', 'mch_id'], 'required'],
            [['id', 'mch_id'], 'integer'],
        ];
    }

    public function delete()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        try {
            // [核心] 查找要删除的分类时，必须同时使用 id 和 mch_id 进行双重验证
            $cat = Cat::findOne(['id' => $this->id, 'mch_id' => $this->mch_id, 'is_delete' => 0]);
            if (!$cat) {
                throw new \Exception('分类不存在或无权操作');
            }

            // [核心] 检查是否有子分类，查询也必须带上 mch_id
            $childExists = Cat::find()->where(['parent_id' => $this->id, 'mch_id' => $this->mch_id, 'is_delete' => 0])->exists();
            if ($childExists) {
                throw new \Exception('请先删除该分类下的子分类');
            }

            // [核心] 检查分类下是否有商品，查询也必须带上 mch_id
            $goodsExists = Goods::find()->where(['cat_id' => $this->id, 'mch_id' => $this->mch_id, 'is_delete' => 0])->exists();
            if ($goodsExists) {
                throw new \Exception('该分类下已有商品，无法删除');
            }

            $cat->is_delete = 1;
            $cat->deleted_at = time();

            if (!$cat->save()) {
                throw new \Exception($this->getErrorMsg($cat));
            }


            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '删除成功');

        } catch (\Exception $e) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }
}