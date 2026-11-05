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
use app\helpers\JobHelper;
use app\models\Admin;
use app\models\BaseModel;
use app\models\ScheduleDeadJob;
use Yii;
use yii\helpers\Json;

/**
 * 调度器死信任务管理（仅超管）
 * 列表 / 人工重投 / 忽略
 */
class ScheduleDeadJobForm extends BaseModel
{
    public $id;
    public $status; // 列表筛选：不传=待处理，传 -1=全部

    public function rules()
    {
        return [
            [['id', 'status', 'page', 'limit'], 'integer'],
        ];
    }

    private function ensureSuper()
    {
        $admin = Yii::$app->admin->identity;
        return $admin && $admin->admin_type == Admin::ADMIN_TYPE_SUPER;
    }

    /**
     * 死信列表 + 待处理数量
     */
    public function getList()
    {
        if (!$this->ensureSuper()) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '仅超级管理员可查看');
        }

        $query = ScheduleDeadJob::find()->where(['is_delete' => 0]);
        // 默认只看待处理；status 传 -1 看全部
        if ($this->status === null || $this->status === '') {
            $query->andWhere(['status' => ScheduleDeadJob::STATUS_PENDING]);
        } elseif ((int)$this->status >= 0) {
            $query->andWhere(['status' => (int)$this->status]);
        }

        $list = $query->orderBy('id DESC')
            ->page($pagination, $this->limit, $this->page)
            ->asArray()
            ->all();

        foreach ($list as &$item) {
            $item['failed_at_text'] = $item['failed_at'] ? date('Y-m-d H:i:s', $item['failed_at']) : '';
        }
        unset($item);

        $pendingCount = (int)ScheduleDeadJob::find()
            ->where(['is_delete' => 0, 'status' => ScheduleDeadJob::STATUS_PENDING])
            ->count();

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'list'          => $list,
            'pagination'    => $this->getPaginationInfo($pagination),
            'pending_count' => $pendingCount,
        ]);
    }

    /**
     * 人工重投：取出 payload 重新入队（attempt 归 0），原死信行标记已重投
     */
    public function retry()
    {
        if (!$this->ensureSuper()) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '仅超级管理员可操作');
        }
        $row = ScheduleDeadJob::findOne(['id' => $this->id, 'is_delete' => 0]);
        if (!$row) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '死信任务不存在');
        }
        if ($row->status != ScheduleDeadJob::STATUS_PENDING) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '该任务已处理，无法重复操作');
        }

        $payload = $row->payload ? Json::decode($row->payload) : null;
        if (!is_array($payload) || empty($payload['class'])) {
            return $this->apiResponse(ApiCode::CODE_FAIL, 'payload 已损坏，无法重投');
        }

        if (!JobHelper::pushRaw($payload, 0)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '重投失败，请检查 Redis');
        }

        $row->status = ScheduleDeadJob::STATUS_RETRIED;
        $row->save();

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '已重新投递');
    }

    /**
     * 一键重投：把全部待处理死信重新入队，逐个处理，损坏的跳过。
     */
    public function retryAll()
    {
        if (!$this->ensureSuper()) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '仅超级管理员可操作');
        }
        $rows = ScheduleDeadJob::find()
            ->where(['is_delete' => 0, 'status' => ScheduleDeadJob::STATUS_PENDING])
            ->all();
        $ok = 0;
        $fail = 0;
        foreach ($rows as $row) {
            $payload = $row->payload ? Json::decode($row->payload) : null;
            if (!is_array($payload) || empty($payload['class'])) {
                $fail++;
                continue;
            }
            if (!JobHelper::pushRaw($payload, 0)) {
                $fail++;
                continue;
            }
            $row->status = ScheduleDeadJob::STATUS_RETRIED;
            $row->save();
            $ok++;
        }
        $msg = "已重新投递 {$ok} 个" . ($fail > 0 ? "，{$fail} 个损坏/失败已跳过" : '');
        return $this->apiResponse(ApiCode::CODE_SUCCESS, $msg, ['ok' => $ok, 'fail' => $fail]);
    }

    /**
     * 一键忽略：全部待处理死信标记为已忽略。
     */
    public function ignoreAll()
    {
        if (!$this->ensureSuper()) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '仅超级管理员可操作');
        }
        $count = ScheduleDeadJob::updateAll(
            ['status' => ScheduleDeadJob::STATUS_IGNORED, 'updated_at' => time()],
            ['is_delete' => 0, 'status' => ScheduleDeadJob::STATUS_PENDING]
        );
        return $this->apiResponse(ApiCode::CODE_SUCCESS, "已忽略 {$count} 个", ['count' => (int)$count]);
    }

    /**
     * 忽略：标记为已忽略，不再出现在待处理列表
     */
    public function ignore()
    {
        if (!$this->ensureSuper()) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '仅超级管理员可操作');
        }
        $row = ScheduleDeadJob::findOne(['id' => $this->id, 'is_delete' => 0]);
        if (!$row) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '死信任务不存在');
        }
        if ($row->status != ScheduleDeadJob::STATUS_PENDING) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '该任务已处理');
        }
        $row->status = ScheduleDeadJob::STATUS_IGNORED;
        $row->save();
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '已忽略');
    }
}
