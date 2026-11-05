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

namespace app\jobs\order;

use app\core\ApiCode;
use app\core\Job;
use app\events\PluginOrderEvent;
use app\forms\api\order\OrderSubmitForm;
use app\models\Mall;
use app\models\Order;
use app\models\User;
use app\services\OrderService;
use Yii;
use yii\helpers\Json;

/**
 * 异步订单创建队列消费者
 * 职责：
 * 1. 从 Redis 恢复提交现场 (用户、商城、表单数据)
 * 2. 调用 OrderService -> OrderCreator 执行业务
 * 3. 将执行结果 (order_no, pay_key 等) 回写到 Redis 供前端轮询
 */
class OrderCreateJob extends Job
{
    public $queueToken;

    public function execute($params = null)
    {
        // 兼容处理：支持直接属性赋值或 params 传参
        $token = $this->queueToken ?: ($params['queueToken'] ?? null);
        if (!$token) {
            Yii::error("OrderCreateJob: 缺少 queueToken");
            return;
        }

        $redisKey = "order_status:{$token}";
        $cache = Yii::$app->redis;

        // 1. 获取 Redis 数据
        $data = $cache->hgetall($redisKey);
        if (!$data) {
            // 数据可能已过期或未写入
            Yii::warning("OrderCreateJob: Redis 中找不到 Token {$token}");
            return;
        }

        // Redis hgetall 返回的是平铺数组，转回 Key-Value
        $data = $this->unflattenArray($data);

        // 2. 恢复上下文 (User & Mall)
        // 必须先恢复身份，否则 Service 内部的 Yii::$app->user->id 会失效
        try {
            $user = User::findOne($data['user_id']);
            if (!$user) {
                // 如果用户被删，直接标记失败
                $this->updateStatus($redisKey, 'fail', '下单用户不存在');
                return;
            }
            Yii::$app->user->setIdentity($user);
            Yii::$app->mall = Mall::findOne($data['mall_id']);

            // 3. 重建表单对象
            // Redis 里的 'form_data' 是 OrderSubmitForm 属性的 JSON 字符串
            // 包含: plugin, type, form_data(业务JSON)
            $formAttributes = Json::decode($data['form_data']);

            $form = new OrderSubmitForm();
            $form->attributes = $formAttributes;

            // 再次校验 (防止 Redis 数据异常)
            if (!$form->validate()) {
                throw new \Exception($form->getErrorMsg());
            }

            \Yii::error("【DEBUG】OrderCreateJob 即将调用 createOrder. user_id=" . ($data['user_id'] ?? 'null') . ", 内存中的 identity=" . (Yii::$app->user->identity ? Yii::$app->user->identity->id : 'null'), 'order.submit');

            // 4. 调用 Service 执行业务
            // OrderService::createOrder 内部会调用 OrderCreator
            $orderService = new OrderService();
            $result = $orderService->createOrder($form);

            // 5. 处理结果
            // OrderCreator 返回的是 ['code' => 0, 'data' => [...]]
            if (isset($result['code']) && $result['code'] === ApiCode::CODE_SUCCESS) {

                // 成功：回写状态和支付参数到 Redis
                $cache->hmset(
                    $redisKey,
                    'status',
                    'success',
                    'order_no',
                    $result['data']['order_no'] ?? '',
                    'pay_order_id',
                    $result['data']['pay_order_id'] ?? 0,
                    'pay_data_key',
                    $result['data']['pay_data_key'] ?? '', // 支付参数Key
                    'updated_at',
                    time()
                );
                // 延长过期时间，给前端留足轮询时间 (5分钟)
                $cache->expire($redisKey, 300);

                // 触发插件钩子：order.after_create
                // 此处 form_data 仍在内存，是携带 plugin_params 的唯一窗口期
                $businessData = Json::decode($formAttributes['form_data'] ?? '{}');
                $pluginParams = is_array($businessData['plugin_params'] ?? null)
                    ? $businessData['plugin_params'] : [];
                if (!empty($pluginParams) && !empty($result['data']['order_no'])) {
                    $order = Order::findOne(['order_no' => $result['data']['order_no']]);
                    if ($order) {
                        Yii::$app->trigger('order.after_create', new PluginOrderEvent([
                            'order_id'      => $order->id,
                            'mall_id'       => (int)$data['mall_id'],
                            'plugin_params' => $pluginParams,
                        ]));
                    }
                }
            } else {
                // 业务失败 (如库存不足)：抛出异常进入 catch 块
                throw new \Exception($result['msg'] ?? '下单失败，请稍后重试');
            }
        } catch (\Exception $e) {
            // 6. 异常处理：记录日志并更新 Redis 状态为 fail
            Yii::error("OrderCreateJob Error [Token:{$token}]: " . $e->getMessage());
            $this->updateStatus($redisKey, 'fail', $e->getMessage());
        } finally {
            // 清理上下文，防止污染 (常驻内存模式下必须)
            Yii::$app->user->setIdentity(null);
            Yii::$app->mall = null;
        }
    }

    /**
     * 更新 Redis 状态辅助方法
     */
    private function updateStatus($key, $status, $msg = '')
    {
        $cache = Yii::$app->redis;
        $cache->hmset(
            $key,
            'status',
            $status,
            'error_msg',
            $msg,
            'updated_at',
            time()
        );
        // 失败状态也要保留一段时间供前端读取错误信息
        $cache->expire($key, 300);
    }

    /**
     * 将 Redis 的平铺数组 [key1, val1, key2, val2] 转为关联数组
     */
    private function unflattenArray($data)
    {
        $res = [];
        $count = count($data);
        for ($i = 0; $i < $count; $i += 2) {
            if (isset($data[$i + 1])) {
                $res[$data[$i]] = $data[$i + 1];
            }
        }
        return $res;
    }
}
