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

namespace app\forms\mall\order;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Order;
use app\services\order\actions\OrderShipmentService;
use app\services\verify\VerifyContext;
use Yii;

class OrderVerifyForm extends BaseModel
{
    public $order_id;

    public function rules()
    {
        return [
            [['order_id'], 'required'],
            [['order_id'], 'integer'],
        ];
    }

    public function verify()
    {
        if (!$this->validate()) {
            return $this->getErrorResponse();
        }

        try {
            $order = Order::find()->where([
                'mall_id' => Yii::$app->mall->id,
                'id' => $this->order_id,
                'is_delete' => 0,
            ])->one();

            if (!$order) {
                throw new \Exception('订单不存在');
            }

            // 利用 Handler 自治权限网进行拦截，取代“硬编码”黑名单
            if (!empty($order->plugin)) {
                try {
                    $handler = \app\services\order\factory\OrderHandlerFactory::create($order->plugin, $order->type);
                    $config = $handler->getActionConfig($order);
                    if (isset($config['show_force_verify']) && $config['show_force_verify'] === false) {
                        throw new \Exception($config['custom_tip'] ?: '该特权卡券/特殊业务单不支持通过通用管道进行强制核销');
                    }
                } catch (\Exception $e) {
                    if ($e->getMessage() !== '获取订单处理器失败' && !str_contains($e->getMessage(), 'Class')) {
                        throw $e;
                    }
                }
            }

            // 执行核销
            // 第三个参数 $force = true 代表是后台管理员强行点击
            $adminUser = Yii::$app->admin->identity; // 取当前操作的管理员实体做备注/标识，或者默认传系统
            if (!$adminUser) {
                 // 如果没有 adminUser(部分总控环境可能是由 user_id 代理)，这只是个引用，我们传个占位的对象
                 $adminUser = new \app\models\User();
                 $adminUser->id = 0;
                 $adminUser->nickname = '平台总管理员';
            }

            $shipmentService = new OrderShipmentService();
            // 在 force 模式下，允许 $clerk 为空或平台虚拟账号
            if (!$shipmentService->verifyPickup($order, $adminUser, true)) {
                throw new \Exception($shipmentService->getErrorMsg());
            }

            // 后台强制核销也计入统一台账 ClerkLog(guarded:clerk 插件未装则跳过,不耦合核心)
            // 否则读侧改读 ClerkLog 后,这条管理员强制核销路径的记录会在核销台账丢失
            if (class_exists(\app\plugins\clerk\models\ClerkLog::class)) {
                $products = [];
                foreach ($order->orderDetails as $detail) {
                    $products[] = [
                        'name' => $detail->snapshotName() ?: '商品已失效',
                        'image' => $detail->snapshotPic(),
                        'spec' => '',
                        'num' => $detail->num,
                    ];
                }
                \app\plugins\clerk\models\ClerkLog::record(
                    new VerifyContext(
                        Yii::$app->mall->id,
                        (int)$order->mch_id,
                        (int)$adminUser->id,
                        $adminUser->nickname ?? '平台管理员',
                        1,          // 强制核销
                        'force'     // 来源:后台强制核销
                    ),
                    [
                        'biz_type' => $order->plugin ?: 'normal',
                        'biz_type_name' => '自提订单',
                        'code' => (string)$order->order_no,
                        'order_no' => (string)$order->order_no,
                        'products' => $products,
                        'remark' => '后台强制核销 · 实付 ¥' . $order->pay_price,
                        'result' => 1,
                    ]
                );
            }

            return [
                'code' => ApiCode::CODE_SUCCESS,
                'msg' => '强制核销成功'
            ];

        } catch (\Exception $e) {
            return [
                'code' => ApiCode::CODE_ERROR,
                'msg' => $e->getMessage()
            ];
        }
    }
}
