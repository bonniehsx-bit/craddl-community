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

class GoodsStatusForm extends BaseModel
{
    public $id;
    public $status; // 0=下架, 1=上架

    public function rules()
    {
        return [
            [['id', 'status'], 'required'],
            [['id', 'status'], 'integer'],
            ['status', 'in', 'range' => [0, 1]],
        ];
    }

    public function update()
    {
        if (!$this->validate()) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '参数错误', ['errors' => $this->getErrors()]);
        }

        try {
            // [安全] 平台操作，也需要确保操作的是商户商品，而不是自营商品
            // 「是不是自营」和「是不是本商城的」是两件事,原来只判了前者:
            // 商品 id 全局自增,不带 mall_id 就能改到别家商城商户的上下架状态。
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

            // [逻辑] 平台强制上架一个商品时，必须确保该商品已经通过审核
            if ($this->status == 1 && $goods->is_check != 1) {
                return ResponseHelper::send(ApiCode::CODE_FAIL, '商品尚未通过审核，无法上架');
            }

            $goods->status = $this->status;

            if (!$goods->save()) {
                return ResponseHelper::send(ApiCode::CODE_FAIL, '状态更新失败', ['errors' => $goods->getErrors()]);
            }

            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '状态更新成功');

        } catch (\Exception $e) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }
}