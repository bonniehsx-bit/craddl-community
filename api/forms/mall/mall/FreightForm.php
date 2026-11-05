<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-21
 * Time: 13:08
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

namespace app\forms\mall\mall;


use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\helpers\SerializeHelper;
use app\models\BaseModel;
use app\models\FreightPrice;
use app\models\FreightTpl;
use yii\helpers\Json;

class FreightForm extends BaseModel
{

    public $id;
    public $enabled;
    public $name;
    public $is_default;
    public $price_type;
    public $price_list;

    public function rules()
    {
        return [
            [['enabled', 'is_default', 'price_type', 'id'], 'integer'],
            [['name'], 'string', 'max' => 45],
            [['price_list'], 'safe']
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $freight = null;

        $t = \Yii::$app->db->beginTransaction();
        if ($this->is_default) {
            FreightTpl::updateAll(['is_default' => 0], ['is_delete' => 0, 'mall_id' => \Yii::$app->mall->id]);
        }

        if ($this->id) {
            $freight = FreightTpl::findOne(['is_delete' => 0, 'id' => $this->id, 'mall_id' => \Yii::$app->mall->id]);
            if (!$freight) {
                $t->rollBack();
                return $this->apiResponse(ApiCode::CODE_FAIL, '模板不存在！');
            }
        }
        if (!$freight) {
            $freight = new FreightTpl();
            $freight->mall_id = \Yii::$app->mall->id;
            FreightPrice::deleteAll(['is_delete' => 0, 'mall_id' => \Yii::$app->mall->id, 'tpl_id' => $freight->id]);
        } else {
            // [优化] 更新时才执行删除旧规则的操作
            FreightPrice::deleteAll(['mall_id' => \Yii::$app->mall->id, 'tpl_id' => $freight->id]);
        }

        $freight->attributes = $this->attributes;
        if (!$freight->save()) {
            $t->rollBack();
            return $this->responseErrorMsg($freight);
        }

        foreach ($this->price_list as $item) {
            // [优化] 增加对首件/首重的校验
            if (!isset($item['first_num']) || $item['first_num'] <= 0) {
                $t->rollBack();
                return ResponseHelper::send(ApiCode::CODE_FAIL, '首件/首重数量必须大于0');
            }
            if (!isset($item['other_num']) || $item['other_num'] <= 0) {
                $t->rollBack();
                return ResponseHelper::send(ApiCode::CODE_FAIL, '续件/续重数量必须大于0');
            }

            $price = new FreightPrice();
            $price->mall_id = $freight->mall_id;
            $price->tpl_id = $freight->id;
            $price->send_area = $item['send_area'];
            $price->send_codes = !empty($item['send_codes']) ? Json::encode($item['send_codes']) : null;
            $price->first_num = $item['first_num'];
            $price->first_price = $item['first_price'];
            $price->other_num = $item['other_num'];
            $price->other_price = $item['other_price'];
            $price->is_union = $item['is_union'] ?? 0; // 确保 is_union 有默认值
            if (!$price->save()) {
                $t->rollBack();
                return $this->responseErrorMsg($price);
            }
        }

        $t->commit();
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '保存成功');
    }

    public function search()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $freight = null;
        if ($this->id) {
            $freight = FreightTpl::findOne(['is_delete' => 0, 'id' => $this->id, 'mall_id' => \Yii::$app->mall->id]);
            if (!$freight) {
                return $this->apiResponse(ApiCode::CODE_FAIL, '模板不存在！');
            }
        }
        $price_list = FreightPrice::find()->where(['tpl_id' => $this->id, 'is_delete' => 0])->select('first_price,first_num,other_price,other_num,send_area,send_codes,is_union')->asArray()->all();
        foreach ($price_list as &$item) {
            if ($item['send_codes']) {
                $item['send_codes'] = Json::decode($item['send_codes']);
            }
        }
        unset($item);
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['freight' => $freight, 'price_list' => $price_list]);
    }

    public function delete()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $t = \Yii::$app->db->beginTransaction();
        $freight = FreightTpl::findOne(['is_delete' => 0, 'id' => $this->id, 'mall_id' => \Yii::$app->mall->id]);
        if (!$freight) {
            $t->rollBack();
            return $this->apiResponse(ApiCode::CODE_FAIL, '模板不存在！');
        }
        $freight->is_delete = 1;

        if (!$freight->save()) {
            $t->rollBack();
            return $this->responseErrorMsg($freight);
        }
        // [优化] 使用软删除
        FreightPrice::updateAll(['is_delete' => 1], ['mall_id' => \Yii::$app->mall->id, 'tpl_id' => $freight->id]);

        $t->commit();
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '删除成功！');
    }
}
