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
use app\models\BaseModel;
use app\models\SplitOnboardingLog;
use app\plugins\mch\models\Mch;
use app\split\FastPaySplit;
use Yii;

class StatusForm extends BaseModel
{
    public function get()
    {
        /**
         * @var Mch $mch
         */
        $mch = Yii::$app->mch;

        /**
         * @var SplitOnboardingLog $log
         */
        $log = SplitOnboardingLog::find()
            ->where(['mch_id' => $mch->id])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        // [核心逻辑] 如果日志状态是待处理或处理中，主动向上游查询最新状态
        if ($log && in_array($log->status, [SplitOnboardingLog::STATUS_WAIT, SplitOnboardingLog::STATUS_DOING])) {
            try {
                Yii::warning("[SplitStatusForm] 日志 #{$log->id} 状态为 {$log->status}，开始主动查询...");
                /** @var FastPaySplit $splitInstance */
                $splitInstance = new $log->split_class(Yii::$app->mall->id);
                $queryResult = $splitInstance->merchantRegisterQuery(['h5RegisterFlow' => $log->h5_register_flow]);

                $apiStatus = $queryResult['status'] ?? null;
                $statusMap = [
                    'SUCCESS' => SplitOnboardingLog::STATUS_SUCCESS,
                    'FAIL' => SplitOnboardingLog::STATUS_FAILED,
                    'DOING' => SplitOnboardingLog::STATUS_DOING,
                    'WAIT' => SplitOnboardingLog::STATUS_WAIT,
                ];

                if (isset($statusMap[$apiStatus]) && $statusMap[$apiStatus] !== $log->status) {
                    Yii::warning("[SplitStatusForm] 日志 #{$log->id} 状态已变更: {$log->status} -> {$statusMap[$apiStatus]}");
                    $log->status = $statusMap[$apiStatus];
                    $log->remark = ($queryResult['desc'] ?? '状态由主动查询更新');
                    if ($log->status === SplitOnboardingLog::STATUS_SUCCESS) {
                        $log->platform_user_id = $queryResult['userId'] ?? null;
                        $log->application_id = $queryResult['applicationId'];
                        $mch->other_application_id = $log->application_id;
                        $mch->other_user_id = $log->platform_user_id;
                        // ⚠ 这两个字段此前只赋值、从不保存(下面只 save 了 $log),等于进件成功的结果
                        //   根本没落到 plugin_mch 表。而收款码那条链是靠它们放行的:
                        //   cashier\forms\admin\paycode\CashierGoodsCreateForm 见 other_application_id 为空
                        //   就直接拒绝建码 —— 商家进件明明成功,收款码却永远开不出来。
                        //   用 save(false, [...]) 只写这两列:避免 Mch 上无关字段的校验把这次回写挡掉。
                        if (!$mch->save(false, ['other_application_id', 'other_user_id'])) {
                            Yii::error("[SplitStatusForm] 进件成功但回写商户失败 MchID:{$mch->id}");
                        }
                    }
                    $log->save();
                }
            } catch (\Exception $e) {
                Yii::error("[SplitStatusForm] 主动查询进件状态失败: " . $e->getMessage());
                // 查询失败不影响正常返回，保留原状态
            }
        }

        $response = [
            'has_applied' => false,
            'status' => 'not_applied',
            'status_text' => '未申请',
            'h5_url' => '',
            'remark' => '',
        ];
        if ($log) {
            $statusTextMap = [
                SplitOnboardingLog::STATUS_WAIT => '待处理',
                SplitOnboardingLog::STATUS_DOING => '审核中',
                SplitOnboardingLog::STATUS_SUCCESS => '已成功',
                SplitOnboardingLog::STATUS_FAILED => '已失败',
            ];
            $response['has_applied'] = true;
            $response['status'] = $log->status;
            $response['status_text'] = $statusTextMap[$log->status] ?? '未知状态';
            $response['h5_url'] = $log->response_data ? (json_decode($log->response_data, true)['registerUrl'] ?? '') : '';
            $response['remark'] = $log->remark;
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', $response);
    }
}

