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

namespace app\plugins\mch\forms\admin\split;

use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\helpers\UserHelper;
use app\models\BaseModel;
use app\helpers\OptionHelper;
use app\models\SplitOnboardingLog;
use app\models\User;
use app\split\BaseSplit;
use app\split\FastPaySplit;
use Yii;
use yii\helpers\Json;

class ApplyForm extends BaseModel
{
    public function apply()
    {
        $mch = Yii::$app->mch;
        if (Yii::$app->user->id != $mch->user_id) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '你不是店长不可申请');
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            // 检查是否已有处理中或成功的申请
            $existingLog = SplitOnboardingLog::find()->where([
                'user_id' => Yii::$app->user->id,
            ])->andWhere(['in', 'status', [SplitOnboardingLog::STATUS_WAIT, SplitOnboardingLog::STATUS_DOING, SplitOnboardingLog::STATUS_SUCCESS]])
                ->one();
            if ($existingLog) {
                $transaction->rollBack();
                $result = Json::decode($existingLog->response_data);
                return $this->apiResponse(ApiCode::CODE_SUCCESS, 'H5进件链接生成成功', [
                    'h5_url' => $result['registerUrl']
                ]);
            }
            $out_mch_no = Yii::$app->user->id . time();
            $onboardingData = ['outMerCode' => $out_mch_no];
            $splitClass = $this->resolveSplitClass();
            $log = new SplitOnboardingLog();
            $log->mall_id = Yii::$app->mall->id;
            $log->user_id = Yii::$app->user->id;
            $log->out_mer_code = $out_mch_no;
            $log->split_class = $splitClass;
            $log->status = SplitOnboardingLog::STATUS_WAIT;
            $log->request_data = Json::encode($onboardingData);
            if (!$log->save()) throw new \Exception("创建日志失败");
            /** @var BaseSplit $splitInstance */
            $splitInstance = new $splitClass(Yii::$app->mall->id);
            $result = $splitInstance->merchantH5Register($onboardingData);
            $log->response_data = Json::encode($result);
            $log->h5_register_flow = $result['h5RegisterFlow'] ?? null;
            if (!$log->save()) throw new \Exception("回写响应失败");
            $transaction->commit();
            return $this->apiResponse(ApiCode::CODE_SUCCESS, 'H5进件链接生成成功', [
                'h5_url' => $result['registerUrl'] ?? ''
            ]);
        } catch (\Exception $e) {
            $transaction->rollBack();
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }

    /**
     * 分账进件走哪个渠道 —— 从收银台配置里取，不再写死「固定使用快付分账」。
     *
     * ⚠️ 这里读的是 option **数据**（键名字符串），不是 import 收银台插件的类：
     *    进件流程按方案 §5.6 留在 mch（它要写 plugin_mch 的进件字段，cashier 不得写底座表），
     *    若反过来 import cashier 的 CashierSettingService，依赖方向就倒了。
     *    option 是两边共享的数据契约，读它不构成代码依赖。
     *
     * 未配置时回落到快付 —— 与改动前的行为一字不差（收银台设置页会把它显式写进 option）。
     */
    private function resolveSplitClass(): string
    {
        $setting = OptionHelper::get('cashier_setting', (int)Yii::$app->mall->id, []);
        $class = is_array($setting) ? (string)($setting['split_class'] ?? '') : '';

        if ($class !== '' && class_exists($class) && is_subclass_of($class, BaseSplit::class)) {
            return $class;
        }
        if ($class !== '') {
            Yii::error("[分账进件] 收银台配置的分账渠道 '{$class}' 无效，回落到默认渠道");
        }
        return FastPaySplit::class;
    }
}
