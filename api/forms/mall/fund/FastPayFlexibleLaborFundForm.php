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

namespace app\forms\mall\fund;

use app\core\ApiCode;
use app\funds\FastPayFlexibleLaborFund;

// [核心] 引入灵活用工的 Fund 类
use app\helpers\OptionHelper;
use app\helpers\ResponseHelper;

/**
 * [新增] 快付灵活用工渠道的后台配置 Form
 */
class FastPayFlexibleLaborFundForm extends FundModel // 你有一个 FundModel 基类
{
    public $setting;

    public function rules()
    {
        return [
            [['setting'], 'safe'],
            // [建议] 增加对核心字段的校验
            [['setting'], function ($attribute, $params) {
                if (empty($this->setting['name'])) {
                    $this->addError($attribute, '渠道名称不能为空');
                }
                if ($this->setting['status'] == 1) { // 仅在启用时校验
                    if (empty($this->setting['applicationId'])) {
                        $this->addError($attribute, '应用APPID不能为空');
                    }
                    if (empty($this->setting['platformCode'])) {
                        $this->addError($attribute, '平台商户号不能为空');
                    }
                    if (empty($this->setting['mainstayNo'])) { // [新增] 校验灵工专属字段
                        $this->addError($attribute, '代征主体编号不能为空');
                    }
                    if (empty($this->setting['channelCode'])) { // [新增] 校验灵工专属字段
                        $this->addError($attribute, '渠道编号不能为空');
                    }
                    if (empty($this->setting['merchantPrivateKey'])) {
                        $this->addError($attribute, '商户私钥不能为空');
                    }
                    if (empty($this->setting['platformPublicKey'])) {
                        $this->addError($attribute, '快付平台公钥不能为空');
                    }
                }
            }],
        ];
    }

    public function attributeLabels()
    {
        return [
            'setting' => '配置'
        ];
    }

    /**
     * 保存快付灵活用工的配置
     */
    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // [核心改造] 读取和保存到 'flexible_labor_funds' 配置项
        $flexibleFunds = OptionHelper::get('flexible_labor_funds', \Yii::$app->mall->id, []);

        $this->setting['fund_type'] = FastPayFlexibleLaborFund::class;
        $flexibleFunds[FastPayFlexibleLaborFund::class] = $this->setting;

        OptionHelper::set('flexible_labor_funds', $flexibleFunds, \Yii::$app->mall->id);

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '保存成功');
    }

    /**
     * 获取快付灵活用工的配置
     */
    public function search()
    {
        // [核心改造] 从 'flexible_labor_funds' 配置项中读取
        $allConfigs = OptionHelper::get('flexible_labor_funds', \Yii::$app->mall->id, []);
        $setting = $allConfigs[FastPayFlexibleLaborFund::class] ?? $this->getDefaults();

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '获取成功', $setting);
    }

    /**
     * 提供一个默认的配置结构
     * @return array
     */
    private function getDefaults(): array
    {
        return [
            'status' => 0,
            'name' => '快付灵活用工',
            'applicationId' => '',
            'platformCode' => '',
            'mainstayNo' => '', // [新增] 灵工专属字段
            'merchantPrivateKey' => '',
            'platformPublicKey' => '',
            'channelCode'=>''
        ];
    }
}