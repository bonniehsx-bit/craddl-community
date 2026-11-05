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
use app\models\Goods;

class GoodsDeleteForm extends BaseModel
{
    public $id;

    public function rules()
    {
        return [
            [['id'], 'required'],
            [['id'], 'integer'],
        ];
    }

    public function delete()
    {
        if (!$this->validate()) {
            return ResponseHelper::send(ApiCode::CODE_FORM_INVALID, '参数错误', ['errors' => $this->getErrors()]);
        }

        try {
            // 下面那道 mch_id == 0 只挡住了「平台自营」,挡不住「别家商城的商户商品」——
            // 商品 id 全局自增,不带 mall_id 就是谁都能删谁的。
            $goods = Goods::findOne([
                'id' => $this->id,
                'is_delete' => 0,
                'mall_id' => \Yii::$app->mall->id,
            ]);

            if (!$goods) {
                return ResponseHelper::send(ApiCode::CODE_FAIL, '商品不存在或已被删除');
            }

            if ($goods->mch_id == 0) {
                return ResponseHelper::send(ApiCode::CODE_FAIL, '无法操作平台自营商品');
            }

            // 执行软删除
            $goods->is_delete = 1;
            $goods->deleted_at = time();

            if (!$goods->save()) {
                return ResponseHelper::send(ApiCode::CODE_FAIL, '删除失败', ['errors' => $goods->getErrors()]);
            }

            // [可选扩展] 可以在此处触发一个事件，用于处理删除商品后的其他逻辑
            // 例如：将关联的营销活动解除等
            // \Yii::$app->trigger(Goods::EVENT_DESTROY, new GoodsEvent(['goods' => $goods]));

            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '删除成功');

        } catch (\Exception $e) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }
}