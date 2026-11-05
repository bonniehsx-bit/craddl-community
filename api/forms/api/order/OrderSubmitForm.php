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
use app\helpers\JobHelper;
use app\helpers\LockHelper;
use app\helpers\ResponseHelper;
use app\models\BaseModel;
use app\models\Order;
use app\models\User;
use app\jobs\order\OrderCreateJob;

use app\services\order\factory\OrderHandlerFactory;
use Yii;
use yii\helpers\Json;

/**
 * Class OrderSubmitForm
 * @package app\forms\api\order
 * @desc [工厂模式 + 路由重构版]
 * 职责：
 * 1. 接收通用路由参数 (plugin, type, form_data)
 * 2. 召唤 Handler 进行同步参数校验 (校验失败直接报错，不进队列)
 * 3. 将合法请求推入 Redis/Queue 进行异步处理
 */
class OrderSubmitForm extends BaseModel
{
    // === 路由参数 ===
    public $plugin;     // 插件标识 (如 'pintuan', 'seckill')
    public $type;       // 订单类型 (如 'hotel')

    // === 核心数据载体 ===
    // 包含 address_id, goods_list, mch_id 等所有业务参数
    // 前端传 JSON 字符串，后端解析为数组
    public $form_data;

    public function rules()
    {
        return [
            [['form_data'], 'required', 'message' => '缺少业务参数'],
            [['plugin', 'type'], 'string'],
            [['form_data'], 'safe'], // 具体校验下沉到 Handler
        ];
    }

    /**
     * 提交入口
     */
    public function submit()
    {
        // 1. 基础格式校验
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        try {
            // 2. 解析 form_data 为数组
            $data = $this->parseFormData($this->form_data);

            // 3. 召唤 Handler (用于同步校验)
            // 工厂需要 goods_list 来探测 Handler (兼容不传 plugin 的情况)
            $goodsList = $data['goods_list'] ?? [];
            $handler = OrderHandlerFactory::create($this->plugin, $this->type);

            // 4. [同步校验] 委托 Handler 进行参数业务校验
            // 如果校验失败 (如：库存不足、未选地址、规则不符)，直接抛出异常，不再进入队列
            // 这一步非常重要，保证进入队列的请求格式基本是正确的
            $handler->validateParams($data);

            /** @var User $user */
            $user = Yii::$app->user->identity;

            // 4.5. [安全拦截] 防止恶意锁库存，检测当前用户今日未支付订单数量

            $unpaidCount = Order::find()->where([
                'user_id' => $user->id,
                'pay_status' => Order::PAY_STATUS_UNPAID, // 0 = 未支付
                'status' => Order::STATUS_UNPAID,         // 10 = 待付款 (未取消/未关闭)
                'is_delete' => 0,   // 未删除
            ])->andWhere(['>=', 'created_at', strtotime('today')])
                ->count();
            if ($unpaidCount >= 5) {
                throw new \Exception('您有较多未支付订单，请先支付或取消后再下单');
            }

            // === 进入异步处理流程 ===

            $cache = Yii::$app->redis;

            // 5. 加锁防止并发重复提交
            $lockToken = LockHelper::lock('ASYNC_ORDER_SUBMIT_' . $user->id, 5);
            if (!$lockToken) {
                return ResponseHelper::send(ApiCode::CODE_FAIL, '操作频繁，请稍后再试');
            }

            try {
                // 6. 生成唯一的异步 Token
                $queueToken = 'OT' . date('YmdHis') . bin2hex(random_bytes(4));
                $redisKey = "order_status:{$queueToken}";

                // 7. 构造存入 Redis 的状态数据
                // 注意：这里我们存入的是新的表单结构，Job 里的 submitForm 也要对应只有这3个字段
                $statusData = [
                    'status' => 'pending',
                    'user_id' => $user->id,
                    'mall_id' => $user->mall_id,
                    // 序列化当前对象属性 (plugin, type, form_data)
                    'form_data' => Json::encode($this->getAttributes(['plugin', 'type', 'form_data'])),
                    'error_msg' => '',
                    'order_no' => '',
                    'created_at' => time(),
                ];

                // 8. 写入 Redis (有效期1小时)
                $cache->hmset($redisKey, ...$this->flattenArray($statusData));
                $cache->expire($redisKey, 3600);

                // 9. 推送任务到队列
                JobHelper::push(new OrderCreateJob([
                    'queueToken' => $queueToken
                ]));

                return $this->apiResponse(ApiCode::CODE_SUCCESS, '订单处理中...', [
                    'status' => 'pending',
                    'queue_id' => $queueToken,
                ]);
            } finally {
                // 安全解锁：必须携带加锁时发放的 Token 防劫持
                LockHelper::unlock('ASYNC_ORDER_SUBMIT_' . $user->id, $lockToken);
            }
        } catch (\Exception $e) {
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }

    /**
     * 辅助方法：解析 form_data
     */
    private function parseFormData($data)
    {
        if (is_array($data)) {
            return $data;
        }

        if (is_string($data)) {
            $decoded = Json::decode($data);
            if (!is_array($decoded)) {
                throw new \Exception('form_data 必须是有效的 JSON 对象');
            }
            return $decoded;
        }

        throw new \Exception('form_data 参数格式错误');
    }

    /**
     * 辅助方法：将关联数组转为 redis hmset 需要的平铺格式
     */
    private function flattenArray($array)
    {
        $result = [];
        foreach ($array as $k => $v) {
            $result[] = $k;
            $result[] = $v;
        }
        return $result;
    }
}
