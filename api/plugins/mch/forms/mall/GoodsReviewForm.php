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

use app\helpers\ResponseHelper;
use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Goods;
use app\models\GoodsInfo;

class GoodsReviewForm extends BaseModel
{
    public $id;
    public $is_check; // 1=通过, 2=驳回
    public $check_remark;

    public function rules()
    {
        return [
            [['id', 'is_check'], 'required'],
            [['id', 'is_check'], 'integer'],
            [['check_remark'], 'string'],
            ['is_check', 'in', 'range' => [1, 2]],
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, null, ['errors' => $this->getErrors()]);
        }

        // 审核是带副作用的写操作(驳回会强制下架并写回备注),必须限定在本商城内,
        // 否则任一商城的运营都能驳回别家商城的商品。
        $goods = Goods::findOne([
            'id' => $this->id,
            'is_delete' => 0,
            'mall_id' => \Yii::$app->mall->id,
        ]);
        if (!$goods) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '商品不存在');
        }

        $goods->is_check = $this->is_check;
        // 如果驳回，必须强制下架
        if ($this->is_check == 2) {
            $goods->status = 0;
            $goodsInfo = GoodsInfo::findOne(['goods_id' => $goods->id]);
            if ($goodsInfo) {
                $goodsInfo->remarks = $this->check_remark;
                $goodsInfo->save();
            }
        }

        if (!$goods->save()) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '操作失败', ['errors' => $goods->getErrors()]);
        }

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '审核操作成功');
    }
}