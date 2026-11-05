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

namespace app\plugins\mch\forms\admin\order;

use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\models\BaseModel;
use app\models\Order;
use app\models\User;
use app\plugins\clerk\models\ClerkLog;
use app\services\OrderService;
use app\services\verify\VerifyContext;
use app\services\verify\VerifyGateway;
use Yii;
use yii\helpers\Json;

/**
 * 商家端 - 统一核销网关
 * 架构模式：责任链 (Chain of Responsibility)
 * 核心逻辑：
 * 1. 扫码查询：自提订单 -> 团购订单 -> 插件订单
 * 2. 确认核销：更新凭证状态 -> 完结主订单 -> 强制触发资金结算
 * 3. [新增] 支持 is_manual_force 参数，允许商家管理员强制核销过期团购单
 */
class UnifiedVerifyForm extends BaseModel
{
    public $code;
    public $is_manual_force; // [新增] 是否强制核销 (1=是, 0=否)
    public $remark; // [新增] 核销备注文字描述

    public function rules()
    {
        return [
            [['code'], 'required'],
            [['code', 'remark'], 'string'],
            [['is_manual_force'], 'integer'], // 接收前端传来的强制标志
            [['is_manual_force'], 'default', 'value' => 0],
            [['remark'], 'default', 'value' => ''],
        ];
    }

    /**
     * =========================================================================
     * 1. 扫码查询 (获取详情)
     * =========================================================================
     */
    public function search()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        try {
            $mchId = Yii::$app->mch->id;

            // --- 第一步：优先检查商家自己的普通自提订单 ---
            $normalOrderRes = $this->checkNormalOrder($mchId);
            if ($normalOrderRes) {
                return ResponseHelper::send(ApiCode::CODE_SUCCESS, '查询成功', $normalOrderRes);
            }

            // --- 第二步：遍历插件(统一走 VerifyGateway 责任链,团购、遇搭等异业订单均走此链路) ---
            $mallId = isset(Yii::$app->mall) ? Yii::$app->mall->id : 0;
            $ctx = new VerifyContext($mallId, $mchId, Yii::$app->user->id, '', (int)$this->is_manual_force, 'mch');
            $res = (new VerifyGateway())->searchPlugins($this->code, $ctx);
            if ($res !== null) {
                return ResponseHelper::send(ApiCode::CODE_SUCCESS, '查询成功', $res);
            }

            throw new \Exception('无效的核销码');

        } catch (\Throwable $e) {
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }

    /**
     * =========================================================================
     * 2. 确认核销 (执行动作)
     * =========================================================================
     */
    public function verify()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // 1. [拦截快照]：提早抓取商品快照，用于全域核销日志引擎
        $snapshot = null;
        $orderNo = '';
        $verifyType = 'unknown';
        try {
            $searchRes = $this->search();
            if (isset($searchRes['code']) && $searchRes['code'] == ApiCode::CODE_SUCCESS) {
                $snapshot = $searchRes['data']['data']['products'] ?? [];
                $orderNo = $searchRes['data']['data']['order_no'] ?? '';
                $verifyType = $searchRes['data']['type'] ?? 'unknown';
            }
        } catch (\Exception $e) {}

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $mchId = Yii::$app->mch->id;
            $clerkId = Yii::$app->user->id; // 当前操作员ID
            
            $isVerified = false;
            $verifyResult = null;

            // --- 第一步：尝试核销普通自提订单 ---
            if ($this->verifyNormalOrder($mchId, $clerkId)) {
                $isVerified = true;
                $verifyResult = $this->apiResponse(ApiCode::CODE_SUCCESS, '核销成功');
                if ($verifyType === 'unknown') $verifyType = 'normal';
            }

            // --- 第二步：遍历插件尝试核销 (支持 group, yuda 及其他所有拥有 verifyCode 接口的插件) ---
            if (!$isVerified) {
                // 统一走 VerifyGateway 责任链(反射 3/4 参兼容 + 坏插件故障隔离)
                $mallId = isset(Yii::$app->mall) ? Yii::$app->mall->id : 0;
                $ctx = new VerifyContext($mallId, $mchId, $clerkId, '', (int)$this->is_manual_force, 'mch');
                $res = (new VerifyGateway())->verifyPlugins($this->code, $ctx);
                if ($res !== null) {
                    $isVerified = true;
                    $verifyResult = $res;
                }
            }

            if (!$isVerified) {
                throw new \Exception('无效的核销码');
            }

            // 3. [保存] 记录统一核销日志 (记录到新表)
            try {
                // 动态拉取插件底层自身抛出的核心备注描述（如分佣结账、退除押金等）
                $backendMessage = (is_array($verifyResult) && isset($verifyResult['msg'])) ? $verifyResult['msg'] : '插件或本地扫码核销完成';
                $finalRemark = $this->remark ? "前端附加: {$this->remark} | 系统动态: {$backendMessage}" : $backendMessage;

                $verifyTypeName = '扩展延伸业务';
                if (is_array($verifyResult) && isset($verifyResult['verify_type_name'])) {
                    $verifyTypeName = $verifyResult['verify_type_name'];
                } elseif ($verifyType === 'normal') {
                    $verifyTypeName = '自提订单';
                }

                $log = new \app\plugins\mch\models\MchVerifyLog();
                $log->mall_id = Yii::$app->mall->id;
                $log->mch_id = $mchId;
                $log->clerk_id = $clerkId;
                $log->clerk_name = Yii::$app->user->identity->nickname ?? '';
                $log->verify_type = $verifyType;
                $log->verify_type_name = $verifyTypeName;
                $log->order_no = $orderNo ?: $this->code;
                $log->products_snapshot = \yii\helpers\Json::encode($snapshot);
                $log->remark = mb_substr($finalRemark, 0, 250); // 防止某些插件长报错打挂数据库
                $log->verify_at = time();
                $log->save();

                // 统一核销台账 ClerkLog 双写(guarded,与 MchVerifyLog 过渡并存,读侧后续切换)
                ClerkLog::record(
                    new VerifyContext(Yii::$app->mall->id, (int)$mchId, (int)$clerkId, Yii::$app->user->identity->nickname ?? '', (int)$this->is_manual_force, 'mch'),
                    [
                        'biz_type' => (string)$verifyType,
                        'biz_type_name' => (string)$verifyTypeName,
                        'code' => (string)$this->code,
                        'order_no' => (string)($orderNo ?: $this->code),
                        'products' => $snapshot,
                        'remark' => mb_substr($finalRemark, 0, 250),
                        'result' => 1,
                    ]
                );
            } catch (\Exception $e) {
                Yii::error('写入统一核销日志失败: ' . $e->getMessage());
            }

            $transaction->commit();
            return $verifyResult;

        } catch (\Exception $e) {
            $transaction->rollBack();
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }

    /**
     * [私有] 检查普通自提订单
     */
    private function checkNormalOrder($mchId)
    {
        /**
         * @var Order $order
         */
        $order = Order::find()->with(['orderDetails.goods', 'user'])
            ->where([
                'mch_id' => $mchId,
                'pickup_code' => $this->code,
                'is_delete' => 0,
            ])
            ->andWhere(['or', ['plugin' => ''], ['plugin' => null]])
            ->one();

        if (!$order) {
            return false;
        }

        $products = [];
        /** @var \app\models\OrderDetail $detail */
        foreach ($order->orderDetails as $detail) {
            $specText = '';
            if ($detail->attr) {
                try {
                    $attrs = Json::decode($detail->attr);
                    if (isset($attrs['attr_list'])) {
                        $specParts = array_column($attrs['attr_list'], 'attr_name');
                    } else {
                        $specParts = array_column($attrs, 'attr_name') ?: array_column($attrs, 'value');
                    }
                    $specText = implode('; ', $specParts);
                } catch (\Exception $e) {}
            }
            $products[] = [
                'name' => $detail->snapshotName() ?: '商品已失效',   // 下单时的商品(快照),老数据回退商品表
                'spec' => $specText,
                'num' => $detail->num,
                'image' => $detail->snapshotPic(),
            ];
        }

        $isAllow = ($order->status == Order::STATUS_TO_BE_SHIPPED || $order->status == Order::STATUS_SHIPPED)
            && ($order->deliver_type == Order::DELIVER_TYPE_PICKUP);

        $msg = $isAllow ? '待核销' : '订单状态不可核销';

        return [
            'type' => 'normal',
            'is_allow' => $isAllow,
            'msg' => $msg,
            'data' => [
                'name' => '普通自提订单',
                'order_no' => $order->order_no,
                'user_name' => $order->name ?: '未知用户',
                'info' => '实付: ¥' . $order->pay_price,
                'products' => $products,
            ]
        ];
    }

    /**
     * [私有] 执行普通订单核销
     */
    private function verifyNormalOrder($mchId, $clerkId)
    {
        $order = Order::find()->where([
            'pickup_code' => $this->code,
            'mch_id' => $mchId,
            'is_delete' => 0
        ])->andWhere(['or', ['plugin' => ''], ['plugin' => null]])->one();

        if (!$order) {
            return false;
        }

        $clerkUser = User::findOne($clerkId);
        $orderService = new OrderService();

        if ($orderService->verifyPickup($order, $clerkUser)) {
            return true;
        }

        // 取 service 自身的错误,**不能传 $order**:verifyPickup 失败时错误 addError 在 service 上。
        // 这条是商家核销台最常走的路径(核心自营自提单),传错对象等于核销员永远看不到失败真因。
        throw new \Exception($orderService->getErrorMsg() ?: '普通订单核销失败');
    }
}