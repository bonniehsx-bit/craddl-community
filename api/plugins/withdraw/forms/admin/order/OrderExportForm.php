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

namespace app\plugins\withdraw\forms\admin\order;

use app\core\ApiCode;
use app\helpers\CacheHelper;
use app\helpers\JobHelper;
use app\models\BaseModel;
use app\plugins\withdraw\jobs\WithdrawOrderExportJob;
use Yii;

/**
 * 提现记录 - 异步导出（对齐老 cash：发起任务返 token，前端轮询状态拿下载链接）。
 * 筛选参数与 OrderListForm 一致。
 */
class OrderExportForm extends BaseModel
{
    public $order_no;
    public $status;
    public $asset_type;
    public $channel_id;   // 提现渠道筛选,与列表页「提现渠道」同源
    public $user_type;
    public $user_keyword;

    public function rules()
    {
        return [
            [['status', 'channel_id'], 'integer'],
            [['order_no', 'asset_type', 'user_type', 'user_keyword'], 'string'],
        ];
    }

    /** 发起异步导出任务 */
    public function asyncExport()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $token = md5(uniqid('', true) . time() . rand(1000, 9999));

        JobHelper::push(new WithdrawOrderExportJob([
            'params'  => $this->attributes,
            'token'   => $token,
            'mall_id' => (int)Yii::$app->mall->id,
        ]));

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '导出任务已创建', ['token' => $token]);
    }

    /** 轮询导出状态 */
    public function checkStatus($token)
    {
        if (empty($token)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, 'Token不能为空');
        }
        $data = CacheHelper::get(WithdrawOrderExportJob::CACHE_PREFIX . $token);
        if (!$data) {
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['status' => 'waiting', 'percentage' => 0]);
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', $data);
    }
}
