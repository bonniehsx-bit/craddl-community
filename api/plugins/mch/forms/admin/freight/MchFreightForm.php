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

namespace app\plugins\mch\forms\admin\freight;

use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\models\BaseModel;
use app\models\FreightTpl;
use app\models\FreightPrice;
use yii\helpers\Json;

/**
 * 商家版运费表单（简化版）
 * @package app\plugins\mch\forms\admin\freight
 */
class MchFreightForm extends BaseModel
{
    public $name;
    public $price_type;
    public $enabled;
    public $first_num;
    public $first_price;
    public $other_num;
    public $other_price;

    /** @var \app\plugins\mch\models\Mch */
    public $mch;

    public function rules()
    {
        return [
            [['name'], 'string', 'max' => 45],
            [['price_type', 'enabled'], 'integer'],
            [['first_num', 'other_num'], 'integer', 'min' => 1, 'message' => '首件/首重和续件/续重必须为大于0的整数'],
            [['first_price', 'other_price'], 'number', 'min' => 0],
            // name 不是必填，如果不填给个默认值
            [['name'], 'default', 'value' => '商家默认模板'],
            // 其他字段在提交时必须有
            [['price_type', 'enabled', 'first_num', 'first_price', 'other_num', 'other_price'], 'required', 'on' => 'save'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'name' => '模板名称',
            'price_type' => '计费方式',
            'enabled' => '是否启用',
            'first_num' => '首件/首重',
            'first_price' => '首件/首重运费(元)',
            'other_num' => '续件/续重',
            'other_price' => '续件/续重运费(元)',
        ];
    }

    public function getList()
    {
        $list = FreightTpl::find()->where([
            'mch_id' => \Yii::$app->mch->id,
            'mall_id' => $this->mall_id,
            'is_delete' => 0
        ])->asArray()->all();

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', ['list' => $list]);
    }

    /**
     * 获取商家运费设置
     */
    public function getDetail()
    {
        $tpl = FreightTpl::findOne([
            'mch_id' => $this->mch->id,
            'mall_id' => $this->mch->mall_id,
            'is_delete' => 0
        ]);

        // 默认的运费规则（用于新商家）
        $defaultPrice = [
            'first_num' => 1,
            'first_price' => 0.00,
            'other_num' => 1,
            'other_price' => 0.00,
        ];

        if (!$tpl) {
            // 如果模板不存在，返回一套默认值
            $data = [
                    'name' => '商家默认模板',
                    'price_type' => 0, // 默认按件
                    'enabled' => 1, // 默认启用
                ] + $defaultPrice;
        } else {
            // 如果模板存在，查询其关联的全国统一规则
            $price = FreightPrice::findOne([
                'tpl_id' => $tpl->id,
                'is_delete' => 0,
                'is_union' => 1 // 商家版只使用全国统一运费规则
            ]);

            $data = [
                'name' => $tpl->name,
                'price_type' => (int)$tpl->price_type,
                'enabled' => (int)$tpl->enabled,
            ];

            if (!$price) {
                // 模板存在但规则丢失（异常情况），附加默认规则
                $data = $data + $defaultPrice;
            } else {
                // 正常情况，附加查询到的规则
                $data = $data + [
                        'first_num' => (int)$price->first_num,
                        'first_price' => (float)$price->first_price,
                        'other_num' => (int)$price->other_num,
                        'other_price' => (float)$price->other_price,
                    ];
            }
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', $data);
    }

    /**
     * 保存商家运费设置
     */
    public function save()
    {
        $this->scenario = 'save';
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $t = \Yii::$app->db->beginTransaction();

        try {
            // 1. 查找或创建运费模板 (FreightTpl)
            // 确保一个商家只有一个运费模板
            $tpl = FreightTpl::findOne([
                'mch_id' => $this->mch->id,
                'mall_id' => $this->mch->mall_id,
                'is_delete' => 0
            ]);

            if (!$tpl) {
                $tpl = new FreightTpl();
                $tpl->mch_id = $this->mch->id;
                $tpl->mall_id = $this->mch->mall_id;
                $tpl->is_default = 1; // 商家的唯一模板，即为默认
            }

            $tpl->name = $this->name;
            $tpl->price_type = $this->price_type;
            $tpl->enabled = $this->enabled;

            if (!$tpl->save()) {
                $t->rollBack();
                return $this->responseErrorMsg($tpl);
            }

            // 2. 查找或创建运费规则 (FreightPrice)
            // 确保该模板下只有一个全国统一规则
            $price = FreightPrice::findOne([
                'tpl_id' => $tpl->id,
                'is_delete' => 0,
                'is_union' => 1
            ]);

            if (!$price) {
                $price = new FreightPrice();
                $price->tpl_id = $tpl->id;
                $price->mall_id = $this->mch->mall_id;
                $price->send_area = '全国统一运费'; // 商家版固定为此值
                $price->send_codes = Json::encode([]); // 商家版固定为空
                $price->is_union = 1; // 标记为全国统一
            }

            // 更新运费规则
            $price->first_num = $this->first_num;
            $price->first_price = $this->first_price;
            $price->other_num = $this->other_num;
            $price->other_price = $this->other_price;

            if (!$price->save()) {
                $t->rollBack();
                return $this->responseErrorMsg($price);
            }

            $t->commit();
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '保存成功');

        } catch (\Exception $e) {
            $t->rollBack();
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }
}