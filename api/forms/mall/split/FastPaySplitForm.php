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

namespace app\forms\mall\split;

use app\core\ApiCode;
use app\helpers\OptionHelper;
use app\helpers\ResponseHelper;
use app\split\FastPaySplit;

class FastPaySplitForm extends SplitModel
{
    public $setting;

    public function rules()
    {
        return [[['setting'], 'safe']];
    }

    /**
     * 保存配置
     */
    public function save()
    {
        $split = $this->split;
        $this->setting['split_type'] = FastPaySplit::class;
        $split[FastPaySplit::class] = $this->setting;
        OptionHelper::set('splits', $split, \Yii::$app->mall->id);
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '保存成功');
    }

    /**
     * 读取配置
     */
    public function search()
    {
        // 定义默认配置结构，防止前端因缺少字段而出错
        $defaultConfig = [
            'status' => 0,
            'name' => '快付分账',
            'platformCode' => '',
            'applicationId' => '',
            'merchantPrivateKey' => '',
            'platformPublicKey' => '',
            'channelCode' => 'shengpay',
            'rate'=>''
        ];
        // 将数据库中已存的配置与默认配置合并
        $this->setting = array_merge($defaultConfig, $this->getSplit(FastPaySplit::class));
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', $this->setting);
    }
}