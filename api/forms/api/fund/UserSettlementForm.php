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
use app\services\OnboardingService;
use Yii;

/**
 * 用户结算/进件信息提交表单 [最终完整版]
 *
 * 职责：
 * 1. 接收前端提交的进件数据 (包含 channel 标识)。
 * 2. 调用 Service 动态解析目标 Fund 类。
 * 3. 将数据保存到 UserSettlementInfo 的 channel_data 字段中（按类名隔离）。
 * 4. 触发 Service 的 start 流程进行校验或上报。
 */
class UserSettlementForm extends BaseModel
{
    /**
     * @var array 前端提交的数据
     * 例如: { "channel": "alipay", "alipay_account": "13800138000", "real_name": "张三" }
     * 或者: { "channel": "bank", "bank_card_no": "622202...", "bank_name": "招商银行", "real_name": "李四" }
     */
    public $formData;

    public function rules()
    {
        return [
            [['formData'], 'safe'],
            [['formData'], 'validateFormData'],
        ];
    }

    /**
     * 自定义校验：确保数据是数组且包含必要的 channel 字段
     */
    public function validateFormData($attribute, $params)
    {
        if (!is_array($this->$attribute) || empty($this->$attribute)) {
            $this->addError($attribute, '提交的数据不能为空');
            return;
        }

        if (empty($this->$attribute['channel'])) {
            $this->addError($attribute, '必须指定进件渠道标识(channel)');
        }
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // 1. 初始化服务层
            $service = new OnboardingService($this->mall_id);

            // 2. 获取渠道标识 + 可选的精确网关类名
            $channelIdentifier = $this->formData['channel'];
            $explicitFundClass = $this->formData['fund_class'] ?? null;

            // 3. [核心动态逻辑] 解析真正要处理的 Fund 类名
            // 优先用前端显式传入的 fund_class(提现渠道按 fund_class 区分,灵工/对公/银行卡 destination 可能都是 bank),
            // 未传则回退按 channel 语义解析——兼容核心现金提现旧逻辑。
            // 这一步决定了数据要存到 channel_data JSON 中的哪个 key 下、以及状态记在哪个类下。
            $targetFundClass = $service->resolveTargetFundClass($explicitFundClass, $channelIdentifier);

            if (!$targetFundClass) {
                throw new \Exception("后台未配置 [{$channelIdentifier}] 渠道或配置无效，无法提交资料。");
            }

            // 4. 准备要保存的数据
            $dataToSave = $this->formData;
            unset($dataToSave['channel'], $dataToSave['fund_class']); // 剔除标识符，只保留业务数据

            // 5. 查找或新建用户结算模型
            $userId = Yii::$app->user->id;
            $model = UserSettlementInfo::findOne(['user_id' => $userId, 'mall_id' => $this->mall_id]);
            if (!$model) {
                $model = new UserSettlementInfo();
                $model->mall_id = $this->mall_id;
                $model->user_id = $userId;
            }

            // 6. 将数据存入 动态解析出的类名 下
            // 这样 BankFund 的数据就会存入 'app\funds\BankFund' 键
            // AlipayFund 的数据就会存入 'app\funds\AlipayFund' 键
            // 互不冲突，支持用户同时绑定多种提现方式
            $allChannelData = $model->getChannelData();

            // 这里的合并策略是覆盖：新提交的字段覆盖旧字段，未提交的保持不变
            $currentData = $allChannelData[$targetFundClass] ?? [];
            $allChannelData[$targetFundClass] = array_merge($currentData, $dataToSave);

            $model->setChannelData($allChannelData);

            // 7. 同步物理字段 (可选，兼容旧逻辑)
            // 如果 BaseFund 定义了 real_name, mobile 等通用字段，尽量同步到主表物理字段方便查询
            foreach ($dataToSave as $key => $value) {
                if ($model->hasAttribute($key) && is_string($value)) {
                    $model->$key = trim($value);
                }
            }

            // 8. 保存模型 (这一步只是存数据，状态还没更新)
            if (!$model->save()) {
                throw new \Exception($this->getErrorMsg($model));
            }

            $transaction->commit();

            // 9. 调用 Service 开始进件流程
            // 直接传已解析好的目标类名(start 内部再 resolveFundClass 一次,完整类名会原样返回),
            // 确保读取/上报的是与保存同一个 fund_class,读取刚才保存的数据后调用 Fund->onboardPerson()。
            // 成功后会更新 status 为 SUCCESS 或 PENDING。
            $result = $service->start($userId, $targetFundClass);

            return $this->apiResponse(ApiCode::CODE_SUCCESS, $result['message'] ?? '设置成功', $result);

        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::warning("用户进件提交失败: " . $e->getMessage());
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }
}