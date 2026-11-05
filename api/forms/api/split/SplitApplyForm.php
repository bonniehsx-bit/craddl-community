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

namespace app\forms\api\split;

use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\helpers\UserHelper;
use app\models\BaseModel;
use app\models\SplitOnboardingLog;
use app\models\User;
use app\split\FastPaySplit;
use Yii;
use yii\helpers\Json;

class SplitApplyForm extends BaseModel
{
    public function apply()
    {
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
            //目前使用快付分账
            $splitClass = FastPaySplit::class; // 固定使用快付分账
            $log = new SplitOnboardingLog();
            $log->mall_id = Yii::$app->mall->id;
            $log->user_id = Yii::$app->user->id;
            $log->out_mer_code = $out_mch_no;
            $log->split_class = $splitClass;
            $log->status = SplitOnboardingLog::STATUS_WAIT;
            $log->request_data = Json::encode($onboardingData);
            if (!$log->save()) throw new \Exception("创建日志失败");
            /** @var FastPaySplit $splitInstance */
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
}

