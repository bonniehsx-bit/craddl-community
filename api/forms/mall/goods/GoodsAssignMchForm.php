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

namespace app\forms\mall\goods;

use app\core\ApiCode;
use app\events\GoodsEvent;
use app\models\BaseModel;
use app\models\Cat;
use app\models\Goods;
use app\plugins\mch\models\Mch;

class GoodsAssignMchForm extends BaseModel
{
    public $goods_id;
    public $mch_id;
    public $cat_id;

    public function rules()
    {
        return [
            [['goods_id', 'mch_id', 'cat_id'], 'required'],
            [['goods_id', 'mch_id', 'cat_id'], 'integer'],
        ];
    }

    public function assign()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $transaction = \Yii::$app->db->beginTransaction();
        try {
            // 1. 验证商品
            // 下面那道 mch_id > 0 只挡「已经有归属的商品」,挡不住「别家商城的商品」——
            // 不带 mall_id 的话,能把 B 商城的自营商品指派给 C 商城的商户。
            $goods = Goods::findOne(['id' => $this->goods_id, 'mall_id' => \Yii::$app->mall->id]);
            if (!$goods) {
                throw new \Exception('商品不存在');
            }
            if ($goods->mch_id > 0) {
                throw new \Exception('该商品已属于某个商家，无法重复指派');
            }

            // 2. 验证商户
            $mch = Mch::findOne([
                'id' => $this->mch_id,
                'is_delete' => 0,
                'mall_id' => \Yii::$app->mall->id,
            ]);
            if (!$mch) {
                throw new \Exception('目标商户不存在');
            }

            // 3. 验证分类
            $cat = Cat::findOne(['id' => $this->cat_id, 'mch_id' => $this->mch_id, 'is_delete' => 0]);
            if (!$cat) {
                throw new \Exception('所选分类不属于该商户');
            }

            // 4. 执行更新
            $goods->mch_id = $this->mch_id;
            $goods->cat_id = $this->cat_id;
            if (!$goods->save()) {
                throw new \Exception($this->getErrorMsg($goods));
            }


            $goodsEvent = new GoodsEvent();
            $goodsEvent->goods = $goods;
            \Yii::$app->trigger(Goods::EVENT_EDIT, $goodsEvent);

            $transaction->commit();

            return [
                'code' => ApiCode::CODE_SUCCESS,
                'msg' => '指派成功',
            ];

        } catch (\Exception $e) {
            $transaction->rollBack();
            return [
                'code' => ApiCode::CODE_FAIL,
                'msg' => $e->getMessage(),
            ];
        }
    }
}