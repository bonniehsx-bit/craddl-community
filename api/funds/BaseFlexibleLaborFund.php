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

namespace app\funds;

/**
 * 灵活用工资金渠道的抽象基类
 * [最终重构版]
 *
 * 继承自 BaseFund，并为灵工业务定义了更具体的契约。
 * 它强制所有子类实现签约和打款的核心逻辑。
 */
abstract class BaseFlexibleLaborFund extends BaseFund
{
    /**
     * [灵工核心] 执行个人签约/进件
     *
     * 我们【重写】(Override)了 BaseFund 中的方法，而不是将其设为 abstract。
     * 通过抛出逻辑异常，我们强制所有继承此类的具体实现（如 FastPayFlexibleLaborFund）
     * 必须提供它们自己的 onboardPerson 方法实现，否则在调用时会立即报错。
     *
     * @param array $userData 包含用户实名信息的数组 (例如: user_id, real_name, identity_no, mobile)
     * @return array ['status' => 'success|failed|pending', 'message' => '...', 'data' => mixed]
     */
    public function onboardPerson(array $userData): array
    {
        // 这是一个“模板方法”模式的体现，基类定义了操作，但将具体实现延迟到子类。
        // 如果子类没有实现，调用就会触发这个明确的错误。
        throw new \LogicException(get_called_class() . " 必须实现 onboardPerson() 方法以处理灵工签约逻辑。");
    }

    /**
     * [灵工核心] 检查个人签约状态
     *
     * 同样，我们重写此方法并强制子类实现。
     *
     * @param int $userId 我们系统的用户ID
     * @return array ['status' => 'success|failed|pending', 'message' => '...']
     */
    public function checkOnboardingStatus(int $userId): array
    {
        throw new \LogicException(get_called_class() . " 必须实现 checkOnboardingStatus() 方法以查询签约状态。");
    }

    /**
     * @inheritdoc
     * [灵工核心] 灵工下发 (统一打款入口)
     *
     * 这个方法在 BaseFund 中是 abstract 的，灵工渠道在这里继续保持其 abstract 状态，
     * 强制所有具体的灵工渠道（如 FastPayFlexibleLaborFund）必须实现它。
     *
     * @param array $payoutData 包含所有打款所需信息的上下文数组。
     * 对于灵工渠道，account_info 中通常需要 account_name, account_no, identity_no, mobile 等。
     * @return array ['status' => 'success|doing|failed', 'message' => '...', 'payout_no' => '...']
     */
    abstract public function payout(array $payoutData): array;

    /**
     * [可选实现] 查询平台在代征主体下的余额
     * @param string $agentWay 下发方式的渠道内部标识 (例如: BANK_CARD, ALIPAY, WX)
     * @return array 包含余额信息的数组
     */
    public function queryPlatformBalance(string $agentWay): array
    {
        // 提供一个默认实现，如果渠道不支持此功能，可以直接抛出异常或返回不支持信息。
        throw new \Exception('该渠道不支持查询平台余额');
    }

    /**
     * @inheritdoc
     * 灵工场景下，资格检查就是检查签约状态。
     * 这个方法重写了 BaseFund 的 checkUserEligibility，提供了符合灵工业务的逻辑。
     */
    public function checkUserEligibility(array $userData): array
    {
        try {
            if (empty($userData['user_id'])) {
                throw new \Exception('缺少 user_id');
            }
            // 直接调用本类中定义的、需要子类实现的 checkOnboardingStatus 方法
            $statusResult = $this->checkOnboardingStatus($userData['user_id']);

            if ($statusResult['status'] !== 'success') {
                return [
                    'is_eligible' => false,
                    'message' => '提现失败：' . $statusResult['message'],
                    'require_onboarding' => true // 明确告知前端需要引导用户去签约
                ];
            }
            return ['is_eligible' => true, 'message' => '签约状态正常，资格校验通过'];
        } catch (\Exception $e) {
            \Yii::error("[FlexibleLaborFund::check] 资格检查异常: " . $e->getMessage());
            return [
                'is_eligible' => false,
                'message' => '提现失败：无法验证您的提现资格，请联系平台管理员。',
                'require_onboarding' => false
            ];
        }
    }

}