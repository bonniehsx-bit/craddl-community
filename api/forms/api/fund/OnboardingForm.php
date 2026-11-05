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
use app\funds\BaseFund;
use app\models\BaseModel;
use app\models\UserSettlementInfo;
use app\services\OnboardingService;
use Yii;

/**
 * 进件表单获取 Form
 * [修改版] - 支持根据 channel 参数动态返回不同渠道的表单字段和数据
 */
class OnboardingForm extends BaseModel
{
    /** @var string 渠道标识: 'bank', 'alipay', 'wechat' */
    public $channel;

    /**
     * @var string 可选:精确指定进件网关的完整类名(如 app\funds\FastPayFlexibleLaborFund)。
     * 提现渠道按 fund_class 区分(银行卡/灵工/对公 destination 都可能是 bank),传入它才能落到正确的网关。
     * 不传则按 channel 语义解析,兼容核心现金提现旧逻辑。
     */
    public $fund_class;

    public function rules()
    {
        return [
            [['channel', 'fund_class'], 'string'],
            // 默认为 bank，兼容旧版本或未传参的情况
            [['channel'], 'default', 'value' => 'bank'],
        ];
    }

    /**
     * 获取动态表单结构和用户已填写的信息
     */
    public function getForm()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        try {
            // 1. [核心] 解析渠道对应的 Fund 类——必须与落库(UserSettlementForm::save)使用同一套
            //    OnboardingService::resolveFundClass，保证「下发的表单字段」与「落库读取的字段」永远是
            //    同一个 handler。否则会出现取字段走 AlipayFund(alipay_account)、落库走 ManualFund(account)
            //    导致字段名错位、提交时报「姓名和账号不能为空」。
            $service = new OnboardingService($this->mall_id);
            $fundClass = $service->resolveTargetFundClass($this->fund_class, $this->channel);
            if (!$fundClass || !class_exists($fundClass)) {
                return $this->apiResponse(ApiCode::CODE_FAIL, "未找到渠道 [{$this->channel}] 对应的处理类，请检查后台打款通道配置");
            }
            // 2. 实例化渠道类并调用 getOnboardingFields
            /** @var BaseFund $fundInstance */
            $fundInstance = Yii::createObject($fundClass, [$this->mall_id]);
            $fields = $fundInstance->getOnboardingFields();
            // 3. 查询用户已保存的结算信息
            /** @var UserSettlementInfo $settlementInfoModel */
            $settlementInfoModel = UserSettlementInfo::findOne([
                'user_id' => Yii::$app->user->id,
                'mall_id' => $this->mall_id,
                'is_delete' => 0
            ]);

            $infoForFrontend = [];
            $onboardingStatus = [];

            if ($settlementInfoModel) {
                // a. 提取物理字段 (共享的基础身份信息)
                $infoForFrontend = [
                    'real_name' => $settlementInfoModel->real_name,
                    'identity_no' => $settlementInfoModel->identity_no,
                    'mobile' => $settlementInfoModel->mobile,
                ];

                // b. 获取所有渠道的 JSON 数据
                $allChannelData = $settlementInfoModel->getChannelData();

                // c. [核心] 只提取当前请求渠道($fundClass)的专属数据
                // 比如当前是 AlipayFund，就只取 alipay_account，不取 bank_account_no
                $currentChannelData = [];
                if ($fundClass && isset($allChannelData[$fundClass]) && is_array($allChannelData[$fundClass])) {
                    $currentChannelData = $allChannelData[$fundClass];
                }

                // d. 合并数据
                $infoForFrontend = array_merge($infoForFrontend, $currentChannelData);

                // e. 提取状态信息
                $extraInfo = $settlementInfoModel->getExtraInfo();
                $allOnboardingStatus = $extraInfo['onboarding_status'] ?? [];

                // 返回状态，前端会根据 status 来判断是否显示“审核中”
                if ($fundClass) {
                    $onboardingStatus[$fundClass] = $allOnboardingStatus[$fundClass] ?? [];
                }
            }

            // 4. 返回结果
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
                'fields' => $fields, // 动态字段列表
                'info' => $infoForFrontend, // 回显数据
                'onboarding_status' => $onboardingStatus, // 审核状态
            ]);

        } catch (\Exception $e) {
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }
}