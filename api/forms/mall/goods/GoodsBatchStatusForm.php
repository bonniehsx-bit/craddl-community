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
use app\models\BaseModel;
use app\models\Goods;
use app\services\order\OrderBehaviorRegistry;

class GoodsBatchStatusForm extends BaseModel
{
    public $ids; // 商品ID数组
    public $status; // 目标状态 0或1

    public function rules()
    {
        return [
            [['ids', 'status'], 'required'],
            [['ids'], 'safe'], // 使用 'safe' 来验证数组
            [['status'], 'integer'],
            [['status'], 'in', 'range' => [0, 1]], // 确保状态值是0或1
        ];
    }

    public function attributeLabels()
    {
        return [
            'ids' => '商品ID',
            'status' => '状态',
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorMsg($this);
        }

        // 受保护商品(插件 getOrderBehaviors().goods_protected,如收银台的默认收银商品)不能被批量上下架,
        // 与 BaseGoodsEditForm::updateStatus 口径一致。原来这里只按 id 更新,收银台占位商品能被批量下架。
        $scope = ['and', ['id' => $this->ids, 'is_delete' => 0, 'mall_id' => \Yii::$app->mall->id]];
        $protectedTypes = OrderBehaviorRegistry::keysWith('goods_protected');
        if ($protectedTypes) {
            $scope[] = ['not in', 'type', $protectedTypes];
        }

        $transaction = \Yii::$app->db->beginTransaction();
        try {
            // 使用 updateAll 进行高效批量更新
            $attrs = ['status' => $this->status, 'updated_at' => time()];
            // updateAll 绕过 AR，Goods::beforeSave 里那道「库存为 0 还上架 = 运营有意为之，
            // 豁免售罄自动下架」的收口在这里不生效，得手动补上。
            // 只影响 stock<=0 的行(见下面的 andWhere)，有库存的商品不必标记。
            if ((int)$this->status === Goods::STATUS_UP) {
                Goods::updateAll(
                    ['auto_off_at' => Goods::AUTO_OFF_EXEMPT],
                    ['and', $scope, ['<=', 'stock', 0]]
                );
            }
            $count = Goods::updateAll($attrs, $scope); // 只更新当前商城、未删除、非受保护的

            $transaction->commit();

            return [
                'code' => ApiCode::CODE_SUCCESS,
                'msg' => '操作成功。' . $count . ' 件商品已' . ($this->status == 1 ? '上架' : '下架') . '。',
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