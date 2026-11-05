<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-11-07
 * Time: 15:19
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
use app\helpers\SerializeHelper;
use app\models\BaseModel;
use app\models\Eorder;

class EorderForm extends BaseModel
{

    public $id;
    public $mall_id;
    public $name;
    public $express_key;
    public $account;
    public $password;
    public $month_code;
    public $shop_name;
    public $tpl_style;
    public $pay_type;
    public $to_home;
    public $is_default;
    public $express;
    public $shop_code;
    public $sender_name; //发件人名字
    public $sender_mobile; ///发件手机号
    public $sender_province; //发件省份
    public $sender_city; //发件人城市
    public $sender_county; //发件人区域
    public $sender_address; //发件人地址


    public function rules()
    {
        return [

            [['id'], 'integer'],
            [['pay_type', 'to_home', 'is_default'], 'integer'],
            [['name', 'account', 'password', 'month_code', 'shop_name', 'tpl_style', 'shop_code', 'sender_name', 'sender_mobile', 'sender_province', 'sender_city', 'sender_county', 'sender_address'], 'string', 'max' => 45],
            [['express_key'], 'string', 'max' => 10],
            [['express'], 'safe']
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $eorder = null;
        if ($this->id) {
            $eorder = Eorder::findOne(['mall_id' => \Yii::$app->mall->id, 'is_delete' => 0, 'id' => $this->id]);
            if (!$eorder) {
                return $this->apiResponse(ApiCode::CODE_FAIL, '该电子面单模板不存在！');
            }
        }
        if (!$eorder) {
            $eorder = new Eorder();
        }

        if ($this->is_default) {
            Eorder::updateAll(['is_default' => 0], ['mall_id' => \Yii::$app->mall->id, 'is_default' => 1]);
        }
        $eorder->attributes = $this->attributes;
        $eorder->express = SerializeHelper::encode($this->express);
        $eorder->mall_id = \Yii::$app->mall->id;
        if (!$eorder->save()) {
            return $this->responseErrorMsg($eorder);
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '保存成功');
    }

    public function search()
    {
        $eorder = Eorder::findOne(['mall_id' => \Yii::$app->mall->id, 'is_delete' => 0, 'id' => $this->id]);
        if (!$eorder) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '该电子面单模板不存在！');
        }
        $eorder->express = SerializeHelper::decode($eorder->express);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['eorder' => $eorder]);
    }

    public function delete()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $eorder = Eorder::findOne(['is_delete' => 0, 'id' => $this->id, 'mall_id' => \Yii::$app->mall->id]);
        if (!$eorder) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '模板不存在！');
        }
        $eorder->is_delete = 1;

        if (!$eorder->save()) {
            return $this->responseErrorMsg($eorder);
        }


        return $this->apiResponse(ApiCode::CODE_SUCCESS, '删除成功！');
    }
}
