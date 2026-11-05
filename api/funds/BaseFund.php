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
 * 资金渠道抽象基类
 * [最终重构版]
 */
abstract class BaseFund
{
    public $mall_id;
    public $name;
    public $fund_type;

    /**
     * [核心变更] 统一的打款入口
     * @param array $payoutData 包含所有打款所需信息的上下文数组。
     * @return array ['status' => 'success|doing|failed', 'message' => '...', 'payout_no' => '...']
     */
    abstract public function payout(array $payoutData): array;

    /**
     * [核心变更] 渠道声明自己支持的打款目标
     * @return string[] 例如: ['bank', 'wechat']
     */
    abstract public function getSupportedChannels(): array;

    /**
     * 检查用户在【此渠道】下的提现资格
     * @param array $userData 包含 user_id 或 mch_id 的数组
     * @return array ['is_eligible' => bool, 'message' => string, 'require_onboarding' => bool]
     */
    abstract public function checkUserEligibility(array $userData): array;

    /**
     * 获取渠道的展示名称和类名
     * @return array
     */
    abstract public function getDisplay();


    // ======================= [核心新增] 开始 =======================
    /**
     * 获取该渠道进件所需的字段定义数组
     *
     * @return array
     * 每个元素都是一个描述输入项的数组，结构示例:
     * [
     *     [ 'key' => 'real_name', 'label' => '真实姓名', 'type' => 'text', 'required' => true, 'placeholder' => '请输入与银行卡开户名一致的姓名' ],
     *     [ 'key' => 'identity_no', 'label' => '身份证号', 'type' => 'id_card', 'required' => true ],
     *     [ 'key' => 'id_card_front', 'label' => '身份证正面照', 'type' => 'image', 'required' => true ],
     *     // 支持的 type: 'text', 'id_card', 'image', 'select', 'date' 等，可与前端约定
     * ]
     *
     * 如果渠道无需在线进件或没有动态表单，直接返回空数组即可。
     */
    public function getOnboardingFields(): array
    {
        // 默认实现：返回一个空数组。
        // 这意味着，如果一个具体的 Fund 子类不重写此方法，
        // 系统就认为它不需要任何在线进件字段。
        return [];
    }
    // ======================= [核心新增] 结束 =======================


    /**
     * 执行个人开户/进件
     * @param array $userData 包含用户结算信息的数组
     * @return array ['status' => 'success|failed|pending_verify', 'message' => '...', 'data' => mixed]
     */
    public function onboardPerson(array $userData): array
    {
        // 默认实现：如果渠道不重写，表示无需在线进件，直接成功。
        return ['status' => 'success', 'message' => '该渠道无需在线进件'];
    }

    /**
     * 检查用户进件状态
     * @param int $userId
     * @return array ['status' => 'success|failed|pending', 'message' => '...']
     */
    public function checkOnboardingStatus(int $userId): array
    {
        // 默认实现：如果渠道不重写，表示无需在线查询，总是成功状态。
        return ['status' => 'success', 'message' => '该渠道无需在线查询状态'];
    }

    /**
     * [可选实现] 验证进件流程（例如提交短信验证码）
     * @param int $userId
     * @param array $verifyData 包含验证所需数据的数组 (e.g., ['sms_code' => '123456'])
     * @return array ['status' => 'success|failed', 'message' => '...']
     */
    public function verifyOnboarding(int $userId, array $verifyData): array
    {
        throw new \Exception(get_called_class() . ' 该渠道不支持或不需要验证步骤');
    }

    /**
     * 提现冗余展示数据（label/value 数组）
     *
     * 用于把账户资料快照冗余到 Cash.payout_info 字段，前端按 label/value 渲染。
     * 每个 Fund 子类应覆写本方法，提供自己渠道的完整字段展示。
     *
     * 默认实现：只提供最基础的 4 个字段（姓名/账号/银行/手机），向后兼容老 Fund。
     *
     * @param array $accountData 由 getUserAccountInfo() 返回的账户数据
     * @return array  例: [['label' => '开户银行', 'value' => '招商银行'], ...]
     */
    public function getPayoutDisplay(array $accountData): array
    {
        $list = [];
        if (!empty($accountData['bank_name'])) {
            $list[] = ['label' => '渠道/银行', 'value' => $accountData['bank_name']];
        }
        if (!empty($accountData['real_name'])) {
            $list[] = ['label' => '姓名',     'value' => $accountData['real_name']];
        }
        if (!empty($accountData['account'])) {
            $list[] = ['label' => '账号',     'value' => $accountData['account']];
        }
        if (!empty($accountData['mobile'])) {
            $list[] = ['label' => '预留手机', 'value' => $accountData['mobile']];
        }
        return $list;
    }

    /**
     * [新增] 获取并格式化模型验证错误信息的辅助方法
     *
     * @param \yii\base\Model $model 验证失败的模型对象
     * @return string 格式化后的错误信息字符串
     */
    protected function getErrorMsg(\yii\base\Model $model): string
    {
        if (!$model || !$model->hasErrors()) {
            return '未知错误。';
        }

        $errorMessages = [];
        // getErrors() 返回一个类似 ['attribute' => ['Error A', 'Error B']] 的数组
        foreach ($model->getErrors() as $attribute => $errors) {
            $errorMessages[] = $attribute . ': ' . implode(', ', $errors);
        }

        // 将所有属性的错误信息用分号连接起来
        return implode('; ', $errorMessages);
    }

    /**
     * [!! 增强与修改 !!]
     * 1. 声明为 protected，增强封装性
     * 2. 补充 mobile, date, image, select 类型的验证
     *
     * 内部辅助方法：根据 getOnboardingFields() 的规则验证提交的数据
     * @param array $data
     * @return array [bool $isValid, string $errorMessage]
     */
    protected function _validateOnboardingFields(array $data): array
    {
        $fields = $this->getOnboardingFields();

        foreach ($fields as $field) {
            $key = $field['key'];
            $label = $field['label'];
            $value = $data[$key] ?? null;

            // 1. 检查 'required' (必填)
            if (!empty($field['required'])) {
                if ($this->_isEmpty($value)) {
                    return [false, $label . ' 不能为空'];
                }
            }

            // 2. 检查 'type' (类型) - 仅在有值时检查
            if (!$this->_isEmpty($value)) {
                $stringValue = (string)$value;
                switch ($field['type']) {

                    case 'number':
                        // ctype_digit 确保字符串只包含纯数字 (适用于银行卡号、纯数字ID等)
                        if (!ctype_digit($stringValue)) {
                            return [false, $label . ' 必须只包含数字'];
                        }
                        break;

                    // [!! 新增 !!] 手机号 (中国大陆11位)
                    case 'mobile':
                        if (!preg_match('/^1[3-9]\d{9}$/', $stringValue)) {
                            return [false, $label . ' 格式不正确 (应为11位手机号)'];
                        }
                        break;

                    case 'id_card':
                        // 简单的18位(17位数字+最后一位X或数字)或15位数字
                        if (!preg_match('/(^\d{15}$)|(^\d{18}$)|(^\d{17}(\d|X|x)$)/', $stringValue)) {
                            return [false, $label . ' 格式不正确'];
                        }
                        break;

                    // [!! 新增 !!] 日期 (验证 YYYY-MM-DD 格式)
                    case 'date':
                        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $stringValue) || strtotime($stringValue) === false) {
                            return [false, $label . ' 格式不正确 (应为 YYYY-MM-DD)'];
                        }
                        break;

                    // [!! 新增 !!]
                    // image 和 select 的值 (通常是 URL 或 选项value) 本质上是字符串
                    // 仅 'required' 检查已足够，类型检查等同于 'text'
                    case 'image':
                    case 'select':
                    case 'text':
                        // 文本类型/URL/选项值 已在 'required' 中处理
                        break;
                }
            }
        }

        return [true, '']; // 全部通过
    }
    protected function _isEmpty($value): bool
    {
        if (!isset($value)) return true;
        if (is_string($value) && trim($value) === "") return true;
        if (is_array($value) && empty($value)) return true;
        return false;
    }
}