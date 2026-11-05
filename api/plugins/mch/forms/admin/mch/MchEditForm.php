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
use app\plugins\mch\models\Mch;
use Yii;
use yii\helpers\Json;

/**
 * [简化版] 商家自行修改店铺基本资料
 */
class MchEditForm extends BaseModel
{
    // --- [核心] 基础信息字段 ---
    public $logo;
    public $banner_images;
    public $description;
    public $opening_hours;
    public $address;
    public $latitude;
    public $longitude;
    public $location_address;

    // --- [新增] 售后信息字段 ---
    public $refund_name;
    public $refund_mobile;
    public $refund_address;

    /**
     * @var Mch|null 当前商户的实例
     */
    private $_mch;

    public function rules()
    {
        $this->_mch = Yii::$app->mch;

        if (!is_array($this->banner_images)) {
            $this->banner_images = [];
        }

        return [
            // --- 规则1: 基础信息必填 ---
            [['logo', 'description', 'address'], 'required'],

            // --- 规则2: 门店专属必填 ---
            [['banner_images', 'opening_hours'], 'required', 'when' => function ($model) {
                return $this->_mch && $this->_mch->type == Mch::TYPE_DOWN_STORE;
            }],

            // --- [新增] 规则3: 售后信息必填 ---
            [['refund_name', 'refund_mobile', 'refund_address'], 'required'],

            // --- 规则4: 字段类型和长度验证 ---
            [['logo', 'address', 'opening_hours', 'location_address', 'refund_name', 'refund_mobile'], 'string', 'max' => 255],
            [['description', 'refund_address'], 'string'],
            [['latitude', 'longitude'], 'number'],
            [['banner_images'], 'each', 'rule' => ['string']],
            [['refund_mobile'], 'match', 'pattern' => '/^1[3-9]\d{9}$/', 'message' => '请输入正确的售后联系电话'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'logo' => '门店logo',
            'description' => '店铺介绍',
            'address' => '详细地址',
            'banner_images' => '店铺图片 (Banner)',
            'opening_hours' => '营业时间',
            'refund_name' => '售后联系人',
            'refund_mobile' => '售后联系电话',
            'refund_address' => '售后退货地址',
        ];
    }

    /**
     * 获取商家当前可编辑的资料
     * @return array
     */
    public function get()
    {
        $mch = Yii::$app->mch;
        if (!$mch) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '商户信息不存在');
        }

        // [核心修改] 在 toArray 中加入新增的售后字段
        // type 必须下发:前端据此判断本地生活门店(type=2)才显示/校验/提交营业时间+地址,
        // 缺它则 form.type 恒为默认 0,门店永远存不了资料(营业时间字段隐藏或后端 required-when 拒绝)。
        $data = $mch->toArray([
            'type',
            'logo', 'banner_images', 'location_address', 'latitude', 'longitude',
            'description', 'opening_hours', 'address',
            'refund_name', 'refund_mobile', 'refund_address', // <-- 新增字段
            'first_order_discount_total_stock', 'is_first_order_discount_enabled',
            'first_order_discount_start_time', 'first_order_discount_end_time'
        ]);

        $data['banner_images'] = Json::decode($data['banner_images']) ?: [];
        $data['opening_hours'] = $data['opening_hours'] ?: '';

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', ['detail' => $data]);
    }

    /**
     * 保存修改
     * @return array
     */
    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $mch = Yii::$app->mch;

            if (!$mch) {
                throw new \Exception('商户信息不存在');
            }
            if ($mch->status != 1) {
                throw new \Exception('店铺当前状态不允许修改资料');
            }

            // --- 赋值基础信息 ---
            $mch->logo = $this->logo;
            $mch->description = $this->description;
            $mch->address = $this->address;
            $mch->opening_hours = $this->opening_hours;
            $mch->banner_images = Json::encode($this->banner_images ?: []);

            // --- [核心修改] 赋值新增的售后信息 ---
            $mch->refund_name = $this->refund_name;
            $mch->refund_mobile = $this->refund_mobile;
            $mch->refund_address = $this->refund_address;

            if ($this->latitude !== null) $mch->latitude = $this->latitude;
            if ($this->longitude !== null) $mch->longitude = $this->longitude;
            if ($this->location_address !== null) $mch->location_address = $this->location_address;


            if (!$mch->save()) {
                throw new \Exception($this->getErrorMsg($mch));
            }

            $transaction->commit();
            return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '资料更新成功'];

        } catch (\Exception $e) {
            $transaction->rollBack();
            return ['code' => ApiCode::CODE_FAIL, 'msg' => $e->getMessage()];
        }
    }
}