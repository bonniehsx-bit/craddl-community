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

namespace app\services;

use app\models\CommonPayOrder;
use app\helpers\OptionHelper;

// 记得引入
use yii\base\Component;
use yii\base\UserException;
use Yii;

class CommonPayOrderService extends Component
{
    /**
     * 创建公共支付订单
     *
     * @param array $params
     * @return CommonPayOrder
     * @throws UserException
     */
    public function createOrder(array $params)
    {
        $order = new CommonPayOrder();

        // 1. 基础赋值
        $order->mall_id = $params['mall_id'] ?? 1;
        $order->user_id = $params['user_id'];
        $order->order_id = $params['order_id'];
        $order->order_no = $params['order_no'];
        $order->pay_price = $params['pay_price'];
        $order->total_price = $params['total_price'];
        $order->order_class = $params['order_class'];
        $order->pay_openid = $params['pay_openid'] ?? '';
        $order->remarks = $params['remarks'] ?? '支付订单';
        $order->key = $params['key'] ?? uniqid();

        // 2. 处理自动关闭/过期时间 (Expire Logic)
        if (isset($params['expire_at']) && $params['expire_at'] > 0) {
            // A. 如果外部直接传了过期时间（比如秒杀订单想设置更短的时间），直接使用
            $order->expire_at = intval($params['expire_at']);
        } else {
            // B. 外部没传，使用系统默认配置
            $order->expire_at = $this->getDefaultExpireAt($order->mall_id);
        }

        // 3. 保存
        if (!$order->save()) {
            $errors = $order->getFirstErrors();
            throw new UserException(reset($errors) ?: '公共支付订单生成失败');
        }

        return $order;
    }

    /**
     * 获取系统配置的默认过期时间戳
     * @param int $mallId
     * @return int
     */
    private function getDefaultExpireAt($mallId)
    {
        try {
            // 获取后台设置的订单自动取消时间（默认15分钟）
            // 注意：这里假设 'mall_setting' 是你的配置组名
            $setting = OptionHelper::get('mall_setting', $mallId, ['over_time' => 15]);

            $minutes = isset($setting['over_time']) ? (int)$setting['over_time'] : 15;

            // 安全检查：防止配置错误导致立即过期
            if ($minutes <= 0) {
                $minutes = 15;
            }

            return time() + ($minutes * 60);

        } catch (\Exception $e) {
            Yii::warning("获取订单过期配置失败，使用默认30分钟: " . $e->getMessage());
            // 降级方案：默认30分钟
            return time() + 1800;
        }
    }
}