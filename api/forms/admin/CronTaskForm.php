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

namespace app\forms\admin;

use app\core\ApiCode;
use app\models\Admin;
use app\models\BaseModel;
use app\models\CronSetting;
use app\services\CronTaskService;
use Yii;

/**
 * Cron 任务可视化管理（仅超管）
 * 列表（代码任务 + 覆盖合并）/ 保存覆盖（启停 + 自定义表达式）
 */
class CronTaskForm extends BaseModel
{
    public $job_key;
    public $enabled;
    public $expression;

    public function rules()
    {
        return [
            [['job_key'], 'string', 'max' => 191],
            [['enabled'], 'integer'],
            [['expression'], 'string', 'max' => 100],
        ];
    }

    private function ensureSuper()
    {
        $admin = Yii::$app->admin->identity;
        return $admin && $admin->admin_type == Admin::ADMIN_TYPE_SUPER;
    }

    /**
     * Cron 任务列表（扫码 + 合并覆盖）
     */
    public function getList()
    {
        if (!$this->ensureSuper()) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '仅超级管理员可查看');
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'list' => CronTaskService::mergedTasks(),
        ]);
    }

    /**
     * 保存覆盖配置：启停 / 自定义表达式，保存后立即重写 Redis
     */
    public function save()
    {
        if (!$this->ensureSuper()) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '仅超级管理员可操作');
        }
        if (empty($this->job_key)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '缺少 job_key');
        }

        // job_key 必须是真实存在的代码任务，杜绝脏数据
        $valid = false;
        foreach (CronTaskService::mergedTasks() as $t) {
            if ($t['job_key'] === $this->job_key) {
                $valid = true;
                break;
            }
        }
        if (!$valid) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '未知的任务: ' . $this->job_key);
        }

        $expression = trim((string)$this->expression);
        // 自定义表达式做基础校验：5 或 6 段（秒可选）
        if ($expression !== '') {
            $parts = preg_split('/\s+/', $expression);
            if (count($parts) < 5 || count($parts) > 6) {
                return $this->apiResponse(ApiCode::CODE_FAIL, 'cron 表达式格式不正确（应为 5 或 6 段）');
            }
        }

        $model = CronSetting::findOne(['job_key' => $this->job_key, 'is_delete' => 0]);
        if (!$model) {
            $model = new CronSetting();
            $model->job_key = $this->job_key;
        }
        $model->enabled = $this->enabled ? 1 : 0;
        $model->expression = $expression;
        if (!$model->save()) {
            return $this->responseErrorInfo($model);
        }

        // 立即合并重写 Redis，Go Watcher 10s 内热加载
        $count = CronTaskService::syncToRedis();

        return $this->apiResponse(ApiCode::CODE_SUCCESS, "已保存，当前生效 {$count} 个任务（Go 10s 内热加载）");
    }
}
