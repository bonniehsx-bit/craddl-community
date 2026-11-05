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

namespace app\forms\api\user;

use app\core\ApiCode;
use app\helpers\BankHelper;
use app\helpers\ResponseHelper;
use app\helpers\UserHelper;
use app\models\Bank;
use app\models\BaseModel;

class BankForm extends BaseModel
{
    public $bank_name;
    public $name;
    public $id_card;
    public $bank_code;
    public $account;
    public $bank_branch_name;

    public $mobile;
    public $is_validity;
    public $begin_date;
    public $end_date;
    public $id_card_pic1;
    public $id_card_pic2;
    public $bank_card_pic1;
    public $bank_card_pic2;
    public $province_code;// 省编码
    public $city_code;// 市编码
    public $area_code;// 区编码
    public $province_name;// 省
    public $city_name;// 市
    public $area_name;// 区


    public function rules()
    {
        return [
            [['province_code', 'city_code', 'area_code'],'required'],
            [['bank_name', 'name', 'id_card', 'bank_code', 'account', 'bank_branch_name','mobile'],'trim'],
            [['province_code', 'city_code', 'area_code'],'string','max' => 11],
            [['province_name', 'city_name', 'area_name'],'string','max' => 45],
            [['bank_branch_name', 'id_card_pic1', 'id_card_pic2', 'bank_card_pic1', 'bank_card_pic2', 'is_validity', 'begin_date', 'end_date'], 'safe'],
            [['bank_name', 'name', 'id_card', 'bank_code', 'account', 'id_card', 'mobile'], 'required'],
            [['bank_name', 'name', 'id_card', 'bank_code', 'account', 'id_card', 'mobile'], 'string']
        ];
    }

    public function attributeLabels()
    {
        return [
            'bank_name' => '银行名称',
            'name' => '开户人姓名',
            'id_card' => '身份证号',
            'bank_code' => '银行编码',
            'account' => '银行卡号',
            'bank_branch_name' => '开户支行',
            'mobile' => '手机号',
            'is_validity' => '是否长期有效',
            'begin_date' => '开始日期',
            'end_date' => '结束日期',
            'id_card_pic1' => '身份证正面',
            'id_card_pic2' => '身份证背面',
            'bank_card_pic1' => '银行卡正面',
            'bank_card_pic2' => '银行卡背面',
        ];
    }

    public function search()
    {

        $bank = Bank::findOne(['user_id' => UserHelper::getMyId(), 'is_delete' => 0]);
        $list = BankHelper::getBankList();
        if ($bank) {
            $bank->bank_code = $bank->bank_code ? intval($bank->bank_code) : '';
        }
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '成功', ['bank' => $bank, 'list' => $list]);
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        foreach ($this->attributes as $key => $value) {
            if ($value === null || $value == 'null') {
                $this->attributes[$key] = '';
            }
        }
        $bank = Bank::findOne(['user_id' => UserHelper::getMyId(), 'is_delete' => 0]);
        if (!$bank) {
            $bank = new Bank();
            $bank->mall_id = $this->mall_id;
            $bank->user_id = UserHelper::getMyId();
        }
        if (!$this->is_validity) {
            $this->is_validity = 0;
        }
        $bank->attributes = $this->attributes;
        if (!$this->begin_date) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '请选择有效期开始日期');
        }
        if (!$this->is_validity) {
            if (!$this->begin_date || !$this->end_date) {
                return ResponseHelper::send(ApiCode::CODE_FAIL, '请选择有效期');
            }
        }
        if (!$bank->save()) {
            return $this->responseErrorMsg($bank);
        }
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '保存成功');
    }
}