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

namespace app\plugins\mch\forms\admin\mch;

use app\core\ApiCode;
use app\models\BaseModel;
use Yii;
use yii\helpers\Json;

/**
 * 配送设置表单(商家自配送)
 * 1. 统一使用 'city' 标识同城配送
 * 2. 处理 delivery_options / city_config / pickup_discount_rate / pickup_discount_max_amount
 *    (服务商+骑手派单层已剔除,不再有 service_provider_id)
 */
class DeliveryConfigForm extends BaseModel
{
    public $delivery_options;
    public $city_config;
    public $pickup_discount_rate;
    public $pickup_discount_max_amount;

    public function rules()
    {
        return [
            [['pickup_discount_max_amount'], 'number'],
            [['delivery_options', 'city_config'], 'safe'],
            [['pickup_discount_rate'], 'number', 'min' => 0, 'max' => 100],
        ];
    }

    /**
     * 获取商家的聚合配送配置
     */
    public function get()
    {
        $mch = Yii::$app->mch;

        $deliveryOptions = !empty($mch->delivery_options) ? Json::decode($mch->delivery_options) : [];
        $cityConfig = !empty($mch->city_delivery_config) ? Json::decode($mch->city_delivery_config) : $this->getCityDefaultConfig();
        $pickupDiscountRate = (float)$mch->pickup_discount_rate;
        $pickupDiscountMaxAmount = (float)$mch->pickup_discount_max_amount;

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'mch_type' => $mch->type, // <--- [核心] 必须有这个字段 (1=供应商, 2=门店)
            'delivery_options' => $deliveryOptions,
            'city_config' => $cityConfig,
            'pickup_discount_rate' => $pickupDiscountRate,
            'pickup_discount_max_amount' => $pickupDiscountMaxAmount
        ]);
    }

    /**
     * 保存商家的聚合配送配置
     */
    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $mch = Yii::$app->mch;
        $isCityEnabled = in_array('city', $this->delivery_options ?: []);
        if ($isCityEnabled) {
            if ($this->city_config) {
                $this->city_config['enable'] = "1";
            } else {
                $this->city_config['enable'] = "0";
            }
        }
        $mch->delivery_options = Json::encode($this->delivery_options ?: []);
        $mch->city_delivery_config = Json::encode($this->city_config ?: (object)[]);
        $mch->pickup_discount_rate = (float)$this->pickup_discount_rate ?: 0.00;
        $mch->pickup_discount_max_amount = (float)$this->pickup_discount_max_amount ?: 0.00;
        // 同步 city_delivery_distance 字段
        if ($isCityEnabled && isset($this->city_config['delivery_radius'])) {
            $mch->city_delivery_distance = (float)$this->city_config['delivery_radius'];
        } else {
            $mch->city_delivery_distance = 0;
        }

        if ($mch->save()) {
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '保存成功');
        } else {
            return $this->responseErrorInfo($mch);
        }
    }

    /**
     * 提供同城配的默认配置
     */
    private function getCityDefaultConfig(): array
    {
        return [
            'delivery_radius' => 5,
            'charge_rules' => [
                'start_distance' => 3.0,
                'start_fee' => '5.00',
                'extra_fee_per_km' => '2.00',
            ],
            'order_amount_limit' => [
                'min_order_amount' => '0.00',
            ],
        ];
    }
}