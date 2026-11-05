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

namespace app\forms\mall\user;

use app\core\ApiCode;
use app\helpers\CacheHelper;
use app\helpers\JobHelper;
use app\models\BaseModel;
use app\jobs\user\UserExportJob;
use Yii;

class UserExportForm extends BaseModel
{
    // --- 搜索参数 (需与 UserListForm 保持一致) ---
    public $keyword;
    public $user_id;
    public $mobile;
    public $nickname;
    public $parent_id;
    public $level_id;
    public $top_parent_id;
    public $search_date;
    public $status; // [新增]

    // --- 导出核心参数 ---
    public $export_fields; // 前端勾选的字段 (JSON string)
    public $token;         // 查询进度的 token

    public function rules()
    {
        return [
            // 搜索参数验证
            [['keyword', 'nickname', 'mobile'], 'string'],
            [['user_id', 'parent_id', 'level_id', 'top_parent_id', 'status'], 'integer'], // [新增]
            [['search_date'], 'safe'],

            // 导出参数验证
            [['export_fields', 'token'], 'string'],
        ];
    }

    /**
     * 1. 发起异步导出
     */
    public function asyncExport()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // 生成任务唯一标识
        $token = md5(uniqid() . time() . rand(1000, 9999));

        // 整理参数
        $params = $this->attributes;
        // 移除 token 本身，避免混淆
        unset($params['token']);

        // 投递队列任务
        $job = new UserExportJob([
            'params' => $params, // 包含搜索条件 + export_fields
            'token' => $token,
            'mall_id' => Yii::$app->mall->id,
        ]);

        JobHelper::push($job);

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => '导出任务已创建，正在后台生成...',
            'data' => [
                'token' => $token
            ]
        ];
    }

    /**
     * 2. 查询导出进度
     */
    public function checkStatus()
    {
        if (empty($this->token)) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => 'Token不能为空'];
        }

        // 缓存键名需与 Job 中保持一致
        $cacheKey = 'user_export_status_' . $this->token;
        $data = CacheHelper::get($cacheKey);

        // 如果缓存不存在，可能是任务还没开始，或者已过期
        if (!$data) {
            return [
                'code' => ApiCode::CODE_SUCCESS,
                'data' => [
                    'status' => 'waiting',
                    'percentage' => 0,
                    'msg' => '任务排队中...'
                ]
            ];
        }

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'data' => $data
        ];
    }
}
