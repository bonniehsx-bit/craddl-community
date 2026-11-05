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

namespace app\forms\mall\mall;

use app\core\ApiCode;
use app\helpers\OptionHelper;
use app\helpers\ResponseHelper;
use app\models\BaseModel;

class RandomMinusSettingForm extends BaseModel
{
    // 该属性将接收前端传来的整个配置对象
    public $form;

    public function rules()
    {
        return [
            [['form'], 'safe']
        ];
    }

    /**
     * 获取随机减配置
     * @return array
     */
    public function get()
    {
        $default = $this->getDefaults();
        // 使用一个独立的 option key 来存储配置
        $setting = OptionHelper::get('random_minus_rules', $this->mall_id, $default);
        // 合并默认值，确保新添加的配置项能正常显示
        $setting = array_merge($default, $setting);

        // [健壮性处理] 确保 tiers 字段永远是一个数组
        if (!isset($setting['tiers']) || !is_array($setting['tiers'])) {
            $setting['tiers'] = [];
        }

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '获取成功', ['setting' => $setting]);
    }

    /**
     * 保存随机减配置
     * @return array
     */
    public function save()
    {
        if (OptionHelper::set('random_minus_rules', $this->form, $this->mall_id)) {
            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '保存成功');
        } else {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '保存失败');
        }
    }

    /**
     * 提供一份默认配置结构
     * @return array
     */
    private function getDefaults()
    {
        return [
            'is_enabled' => 0,
            'tiers' => [
                ['order_amount_max' => '10.00', 'minus_amount_max' => '0.10'],
                ['order_amount_max' => '100.00', 'minus_amount_max' => '1.00'],
            ],
        ];
    }
}