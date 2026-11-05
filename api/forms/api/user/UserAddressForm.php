<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-09-17
 * Time: 16:07
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

namespace app\forms\api\user;

use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\models\BaseModel;
use app\models\UserAddress;

class UserAddressForm extends BaseModel
{
    public $province_code;
    public $province_name;
    public $city_code;
    public $city_name;
    public $county_code;
    public $county_name;
    public $town_code;
    public $town_name;
    public $is_default;
    public $mobile;
    public $id;
    public $area;
    public $address;
    public $detail;
    public $name;
    public $latitude;
    public $longitude;
    public $location;


    public function rules()
    {
        return [
            [['location'], 'string'],
            [['is_default', 'id'], 'integer'],
            [['mobile'], 'string', 'max' => 11],
            [['latitude', 'longitude'], 'number'],
            [['province_code', 'city_code', 'county_code', 'town_code'], 'string', 'max' => 20],
            [['province_name', 'city_name', 'county_name', 'town_name', 'name'], 'string', 'max' => 45],
            [['area', 'address', 'detail'], 'string', 'max' => 255], // Increased address length
            [['is_default'], 'default', 'value' => 0]
        ];
    }

    public function setDefault()
    {
        UserAddress::updateAll(['is_default' => 0], ['user_id' => \Yii::$app->user->identity->id]);
        UserAddress::updateAll(['is_default' => 1], ['id' => $this->id]);
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '操作成功');
    }

    /**
     * [OPTIMIZED] 保存地址
     */
    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $transaction = \Yii::$app->db->beginTransaction();
        try {
            $userId = \Yii::$app->user->identity->id;
            // 如果用户将此地址设为默认，则先将该用户的所有其他地址取消默认
            if ($this->is_default == 1) {
                UserAddress::updateAll(['is_default' => 0], ['user_id' => $userId]);
            }
            $address = null;
            if ($this->id) {
                $address = UserAddress::findOne(['is_delete' => 0, 'id' => $this->id, 'user_id' => $userId]);
                if (!$address) {
                    throw new \Exception('地址不存在');
                }
            } else {
                $address = new UserAddress();
                $address->user_id = $userId;
                $address->mall_id = \Yii::$app->mall->id;
            }

            // [FIXED] Manually assign attributes and construct the full address
            $address->name = $this->name;
            $address->mobile = $this->mobile;
            $address->province_code = $this->province_code;
            $address->province_name = $this->province_name;
            $address->city_code = $this->city_code;
            $address->city_name = $this->city_name;
            $address->county_code = $this->county_code;
            $address->county_name = $this->county_name;
            $address->town_code = $this->town_code;
            $address->town_name = $this->town_name;
            $address->is_default = $this->is_default;
            $address->area = $this->area;
            $address->latitude = $this->latitude;
            $address->longitude = $this->longitude;
            $address->location = $this->location;

            // The detail part from the form is now correctly assigned to the 'detail' field
            $address->detail = $this->detail;

            // Construct the full address string from components
            $fullAddressParts = array_filter([$this->province_name, $this->city_name, $this->county_name, $this->town_name, $this->detail]);
            $address->address = implode('', $fullAddressParts);


            // [健壮性] 检查用户是否还有其他地址，如果没有，则强制将当前地址设为默认
            $addressCountQuery = UserAddress::find()->where(['user_id' => $userId, 'is_delete' => 0]);
            if ($this->id) {
                $addressCountQuery->andWhere(['!=', 'id', $this->id]);
            }
            $otherAddressCount = $addressCountQuery->count();

            if ($otherAddressCount == 0) {
                $address->is_default = 1;
            }

            if (!$address->save()) {
                throw new \Exception($this->getErrorMsg($address));
            }
            $transaction->commit();
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '保存成功');
        } catch (\Exception $e) {
            $transaction->rollBack();
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }

    public function delete()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $address = UserAddress::findOne(['is_delete' => 0, 'id' => $this->id, 'user_id' => \Yii::$app->user->identity->id]);
        if (!$address) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '该记录不存在或已被删除');
        }
        $address->is_delete = 1;
        if (!$address->save()) {
            return $this->responseErrorMsg($address);
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '删除成功');
    }

    public function search()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $address = UserAddress::findOne(['is_delete' => 0, 'id' => $this->id, 'user_id' => \Yii::$app->user->identity->id]);
        if (!$address) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '该记录不存在或已被删除');
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['address' => $address]);
    }


    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $list = UserAddress::find()->where(['is_delete' => 0, 'user_id' => \Yii::$app->user->identity->id])->orderBy(['is_default' => SORT_DESC, 'id' => SORT_DESC])->asArray()->all();
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['list' => $list]);
    }
}
