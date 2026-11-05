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

namespace app\forms\api\order;

use app\core\ApiCode;
use app\models\BaseModel;
use Yii;

/**
 * Class OrderStatusForm
 * @package app\forms\api\order
 * @desc [Redis版] 用于轮询查询异步下单任务的状态
 */
class OrderStatusForm extends BaseModel
{
    public $queue_id; // 此时传入的是 Redis 中生成的 Token，例如 OT20260131...

    public function rules()
    {
        return [
            [['queue_id'], 'required'],
            [['queue_id'], 'string'], // 注意：Redis Token 现在是字符串
        ];
    }

    public function check()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        /** @var \app\models\User $user */
        $user = Yii::$app->user->identity;
        $redis = Yii::$app->redis;

        $redisKey = "order_status:{$this->queue_id}";

        // 1. 从 Redis 获取所有哈希字段
        $rawData = $redis->hgetall($redisKey);
        if (!$rawData) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '订单任务已过期或不存在');
        }

        // 2. 解析 Redis 的平铺数组为关联数组
        $log = $this->unflattenArray($rawData);

        // 3. 安全校验：确保只能查询自己的订单
        if ((int)$log['user_id'] !== (int)$user->id || (int)$log['mall_id'] !== (int)$user->mall_id) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '无权访问该任务');
        }
        $currentStatus = $log['status'] ?? 'pending';
        $data = [
            'status' => $currentStatus,
        ];
        if ($currentStatus === 'success') {
            $data = $log;
        } elseif ($currentStatus === 'fail') {
            $data['error_msg'] = $log['error_msg'] ?? '下单失败，请重试';
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', $data);
    }

    /**
     * 将 Redis 返回的 [key1, val1, key2, val2] 转为 [key1 => val1]
     */
    private function unflattenArray($data)
    {
        $res = [];
        for ($i = 0; $i < count($data); $i += 2) {
            $res[$data[$i]] = $data[$i + 1];
        }
        return $res;
    }
}