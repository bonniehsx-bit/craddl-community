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

namespace app\plugins\mch\forms\api\setting;

use app\core\ApiCode;
use app\helpers\OptionHelper;
use app\helpers\ResponseHelper;

// [推荐] 引入统一的 ResponseHelper
use app\models\BaseModel;
use app\plugins\mch\models\MchCat;
use yii\helpers\ArrayHelper;

/**
 * Class SettingForm
 * @package app\plugins\mch\forms\api\setting
 *
 * [最终增强版] 负责为前端商家入驻申请页面 提供所有需要的预设配置数据。
 */
class SettingForm extends BaseModel
{
    /**
     * 获取商家入驻申请页面等前端场景所需的公共配置
     * @return array
     */
    public function getIndex()
    {
        // --- 1. 获取商家插件的全局设置 ---
        // 安全合并：list 字段(allowed_mch_types)显式覆盖，规避 ArrayHelper::merge append 陷阱
        // （详见 OptionHelper::mergeSetting；开发规范 §2.3.1）
        $settings = OptionHelper::mergeSetting('mch_setting', \Yii::$app->mall->id, $this->getDefaults(), ['allowed_mch_types']);

        // 业务清洗：去重 + 转 int（兼容历史脏数据 [1,2,4,"2",...]）
        if (isset($settings['allowed_mch_types']) && is_array($settings['allowed_mch_types'])) {
            $settings['allowed_mch_types'] = array_values(array_unique(array_map('intval', $settings['allowed_mch_types'])));
        }

        // --- 2. 获取商家分类列表 (您的原有逻辑) ---
        $categories = MchCat::find()->where([
            'mall_id' => \Yii::$app->mall->id,
            'is_delete' => 0,
        ])
            ->select(['id', 'cat_name']) // [优化] 将cat_name重命名为name，前端更通用
            ->orderBy(['order_num' => SORT_DESC, 'id' => SORT_ASC])
            ->asArray()
            ->all();

        // --- 3. 动态生成让利比例选项 (您的原有逻辑) ---


        // --- 4. 汇总所有前端需要的数据 ---
        $data = [
            'setting' => [
                // 原有的数据
                'is_mch_open' => $settings['is_mch_open'],
                'allowed_mch_types' => $settings['allowed_mch_types'] ?? [1, 2, 4],
                'categories' => $categories,
                'instructions_title' => $settings['instructions_title'],
                'instructions_content' => $settings['instructions_content'],
                'entry_agreement_title' => $settings['entry_agreement_title'],
                'entry_agreement_content' => $settings['entry_agreement_content'],
            ]
        ];

        // [推荐] 使用统一的 ResponseHelper 返回
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '获取成功', $data);
    }

    /**
     * 提供一份与后台设置表单完全一致的默认配置结构
     * @return array
     */
    private function getDefaults(): array
    {
        return [
            // --- 审核与入驻 ---
            'is_mch_open' => 1,
            'allowed_mch_types' => [1, 2, 4],
            'entry_agreement_title' => '商家入驻协议',
            'entry_agreement_content' => '欢迎入驻！...',
            'instructions_title' => '入驻说明',
            'instructions_content' => '请填写真实有效的信息。',
            'review_type' => 1,
            // --- 支付与结算 ---
            'withdraw_rules' => [ // 确保提现规则有默认值
                'is_enabled' => 1,
                'min_amount' => '1.00',
                'day_max_count' => '3',
                'day_max_amount' => '10000.00',
                'service_fee_rate' => '0.6',
                'tax_rate' => '0',
            ],
            // --- UI与显示 ---
            'is_show_sales' => 1,
        ];
    }
}
