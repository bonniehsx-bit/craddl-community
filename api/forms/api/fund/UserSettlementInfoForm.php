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

namespace app\forms\api\fund;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\UserSettlementInfo;

// [新增] 导入我们需要的辅助类
use app\helpers\OptionHelper;
use Yii;

/**
 * [重构版 - 适配 channel_data]
 */
class UserSettlementInfoForm extends BaseModel
{
    public function getInfo()
    {
        $userId = \Yii::$app->user->id;
        $info = UserSettlementInfo::findOne(['user_id' => $userId, 'mall_id' => $this->mall_id, 'is_delete' => 0]);

        // [核心新增] 获取用户端的默认进件渠道 (网关)
        // 这必须和 OnboardingForm.php 的逻辑保持一致
        $fundChannelSetting = OptionHelper::get('fund_channel', $this->mall_id, []);
        $onboardingFundClass = $fundChannelSetting['default_onboarding_fund'] ?? null;

        $responseData = [];
        if (!$info) {
            // 如果用户从未保存过，返回一个空的默认结构
            $responseData = [
                'real_name'   => '',
                'identity_no' => '',
                'mobile'      => '',
            ];
        } else {
            // 如果有数据，合并物理字段和JSON字段

            // a. 先放入物理字段 (这是所有渠道共享的身份信息)
            $responseData = [
                'real_name'   => $info->real_name,
                'identity_no' => $info->identity_no,
                'mobile'      => $info->mobile,
            ];

            // b. [核心修改] 解码新的 channel_data 字段
            $allChannelData = $info->getChannelData(); // <-- 从 getChannelData() 获取

            // c. [核心修改] 只提取当前默认渠道($onboardingFundClass)的专属数据
            $currentChannelData = [];
            if ($onboardingFundClass && isset($allChannelData[$onboardingFundClass]) && is_array($allChannelData[$onboardingFundClass])) {
                $currentChannelData = $allChannelData[$onboardingFundClass];
            }

            // d. [核心修改] 合并 物理共享字段 和 当前渠道专属字段
            $responseData = array_merge($responseData, $currentChannelData);
        }

        // [核心变更] (此逻辑保持不变) 返回所有渠道的状态
        $onboardingStatus = $info ? ($info->getExtraInfo()['onboarding_status'] ?? []) : [];

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'info' => $responseData, // 扁平的、合并后的数据
            'onboarding_status' => $onboardingStatus // 完整的、嵌套的状态
        ]);
    }
}