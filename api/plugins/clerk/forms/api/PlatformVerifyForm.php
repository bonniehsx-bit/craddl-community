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

namespace app\plugins\clerk\forms\api;

use app\core\ApiCode;
use app\helpers\LockHelper;
use app\models\BaseModel;
use app\models\Order;
use app\plugins\clerk\models\ClerkLog;
use app\plugins\clerk\models\ClerkUser;
use app\services\verify\VerifyContext;
use app\services\verify\VerifyGateway;
use Yii;
use yii\helpers\Json;

class PlatformVerifyForm extends BaseModel
{
    public $code;
    public $is_manual_force; 
    public $remark; 

    public function rules()
    {
        return [
            [['code'], 'required'],
            [['code', 'remark'], 'string'],
            [['is_manual_force'], 'integer'],
            [['is_manual_force'], 'default', 'value' => 0],
            [['remark'], 'default', 'value' => ''],
        ];
    }

    private function checkClerkAuth(): bool
    {
        if (!$this->isClerk()) {
            throw new \Exception('您不是平台自营核销员，无权核销平台商品。');
        }
        return true;
    }

    /**
     * 当前登录用户是否为本商城的启用中平台核销员
     */
    public function isClerk(): bool
    {
        return ClerkUser::find()->where([
            'mall_id' => Yii::$app->mall->id,
            'user_id' => Yii::$app->user->id,
            'status' => 1,
            'is_delete' => 0,
        ])->exists();
    }

    /**
     * 供移动端核销台进页校验:非核销员前端直接显示「无权限」,不再让台面对所有人可见。
     */
    public function authInfo()
    {
        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => '',
            'data' => ['is_clerk' => $this->isClerk() ? 1 : 0],
        ];
    }

    public function search()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        try {
            $this->checkClerkAuth();

            // 自营商品核销，强制 mch_id = 0
            $mchId = 0;

            // --- 第一步：检查普通自提订单 ---
            $normalOrderRes = $this->checkNormalOrder($mchId);
            if ($normalOrderRes) {
                return [
                    'code' => ApiCode::CODE_SUCCESS,
                    'msg' => '查询成功',
                    'data' => $normalOrderRes
                ];
            }

            // --- 第二步：遍历插件(统一走 VerifyGateway 责任链) ---
            $ctx = new VerifyContext(Yii::$app->mall->id, $mchId, Yii::$app->user->id, '', (int)$this->is_manual_force, 'platform');
            $res = (new VerifyGateway())->searchPlugins($this->code, $ctx);
            if ($res !== null) {
                return [
                    'code' => ApiCode::CODE_SUCCESS,
                    'msg' => '查询成功',
                    'data' => $res
                ];
            }

            throw new \Exception('无效的核销码或该订单非平台自营');

        } catch (\Exception $e) {
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }

    public function verify()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // [并发防重] 同一核销码串行化:两个核销员同一瞬间提交同一码时只放行一个,
        // 避免都过状态校验后重复核销、重复触发 EVENT_ORDER_CONFIRMED(重复结算/发积分)。
        $lockKey = 'clerk_verify_' . Yii::$app->mall->id . '_' . md5((string)$this->code);
        $lockToken = LockHelper::lock($lockKey, 10);
        if (!$lockToken) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '该凭证正在核销中，请勿重复提交');
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $this->checkClerkAuth();

            $mchId = 0;
            $clerkId = Yii::$app->user->id;
            
            $isVerified = false;
            $verifyResult = null;

            if ($this->verifyNormalOrder($mchId, $clerkId)) {
                $isVerified = true;
                $verifyResult = $this->apiResponse(ApiCode::CODE_SUCCESS, '核销成功');
            }

            $snap = [];
            if (!$isVerified) {
                // 【台账快照】走到这里说明不是核心自营单(核心单自带快照),且插件尚未核销、状态未变 ——
                // 这是唯一能取到单号+商品的时机。插件老钩子 verifyCode 的返回值普遍只有
                // code/msg/verify_type_name(仅 brand 主动塞了单号商品),不预取的话台账这两列永远是空,
                // 后台核销记录按订单号根本搜不到插件核销。与商家核销台 UnifiedVerifyForm 的做法一致。
                // 放在这里而不是方法开头:核心单命中时不必空跑一遍插件链(otv 那种要调第三方 API 的尤其)。
                $snap = $this->capturePluginSnapshot();

                // 统一走 VerifyGateway 责任链(反射 3/4 参兼容 + 坏插件故障隔离)
                $ctx = new VerifyContext(Yii::$app->mall->id, $mchId, $clerkId, '', (int)$this->is_manual_force, 'platform');
                $res = (new VerifyGateway())->verifyPlugins($this->code, $ctx);
                if ($res !== null) {
                    $isVerified = true;
                    $verifyResult = $res;
                }
            }

            if (!$isVerified) {
                throw new \Exception('无效的核销码或该订单非平台自营');
            }

            // 核销记录不再单独写 MchVerifyLog —— 直接读 Order 表即可：
            // Order.clerk_id / clerk_name / finish_at / status=40 / deliver_type='pickup'
            // 后台 LogListForm 从 Order 表查询核销记录

            $transaction->commit();

            // 统一核销台账留痕(guarded,不影响主流程);单号/商品从插件结果或核心单取,顺带激活死字段 $remark
            $r = is_array($verifyResult) ? $verifyResult : [];
            $no = $this->verifiedNormalOrder; // 核心自提单命中时非 null
            ClerkLog::record(
                new VerifyContext(Yii::$app->mall->id, 0, (int)$clerkId, Yii::$app->user->identity->nickname ?? '', (int)$this->is_manual_force, 'platform'),
                [
                    // 三级兜底:插件自报(brand 那种主动塞全字段的最准) → 核销前预取的快照 → 核心自营单。
                    // 用 ?: 而非 ??:字段存在但为空串/空数组时也要继续往下兜,否则等于没兜。
                    'biz_type' => ($r['biz_type'] ?? '') ?: ($snap['biz_type'] ?? '') ?: ($no ? 'normal' : ''),
                    'biz_type_name' => ($r['verify_type_name'] ?? '') ?: ($snap['biz_type_name'] ?? '') ?: '平台自营自提',
                    'code' => (string)$this->code,
                    'order_no' => ($r['order_no'] ?? '') ?: ($snap['order_no'] ?? '') ?: ($no->order_no ?? ''),
                    'products' => ($r['products'] ?? []) ?: ($snap['products'] ?? []) ?: ($no ? $this->normalOrderProducts($no) : []),
                    'remark' => (string)$this->remark,
                    'result' => 1,
                ]
            );

            return is_array($verifyResult) ? $verifyResult : $this->apiResponse(ApiCode::CODE_SUCCESS, '核销成功');

        } catch (\Throwable $e) {
            // 必须接 \Throwable 而不是 \Exception:核销要跨进各个插件的实现,
            // 那里最容易抛出的恰恰是 \Error 而非 \Exception —— 典型如
            // OrderService::verifyPickup(Order $order, User|Admin $clerk, ...) 是强类型,
            // 插件里 User::findOne($clerkId) 查不到人就传了 null,直接 TypeError。
            // 用 \Exception 接不住的后果是双重的:接口直接 500(用户只看到"系统错误"),
            // 而且**这个事务不会回滚** —— 核销可能已写了一半。
            $transaction->rollBack();
            Yii::error('[平台核销] 执行异常 code=' . $this->code . ' : ' . $e->getMessage()
                . ' @ ' . $e->getFile() . ':' . $e->getLine(), 'clerk');
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage() ?: '核销失败,请重试');
        } finally {
            LockHelper::unlock($lockKey, $lockToken);
        }
    }

    private function checkNormalOrder($mchId)
    {
        $order = Order::find()->with(['orderDetails.goods', 'user'])
            ->where([
                'mall_id' => Yii::$app->mall->id, // 数据隔离:只查本商城,防跨商城核销
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

        $hasActiveRefund = in_array($order->refund_status, [Order::REFUND_STATUS_APPLIED, Order::REFUND_STATUS_PROCESSING]);
        $isAllow = ($order->status == Order::STATUS_TO_BE_SHIPPED || $order->status == Order::STATUS_SHIPPED)
            && ($order->deliver_type == Order::DELIVER_TYPE_PICKUP)
            && !$hasActiveRefund;

        $msg = $hasActiveRefund ? '该订单有售后处理中，不可核销' : ($isAllow ? '待核销' : '订单状态不可核销');

        return [
            'type' => 'normal',
            'is_allow' => $isAllow,
            'msg' => $msg,
            'data' => [
                'name' => '平台自营自提订单',
                'order_no' => $order->order_no,
                'user_name' => $order->name ?: '未知用户',
                'info' => '实付: ¥' . $order->pay_price,
                'products' => $products,
            ]
        ];
    }

    private function verifyNormalOrder($mchId, $clerkId)
    {
        $order = Order::find()->where([
            'mall_id' => Yii::$app->mall->id, // 数据隔离:只查本商城,防跨商城核销
            'pickup_code' => $this->code,
            'mch_id' => $mchId,
            'is_delete' => 0,
        ])
        ->andWhere(['or', ['plugin' => ''], ['plugin' => null]])
        ->one();

        if (!$order) {
            return false;
        }
        // 锁行后按最新状态判断(Order::lockForUpdate):核销码锁只挡得住两个核销员同时核同一张码,
        // 挡不住同一刻的取消 / 退款 —— 不锁的话会把刚关掉、钱已退的单写成已完成待结算,货也交出去了
        if (!$order->lockForUpdate()) {
            return false;
        }

        // 退款拦截:有进行中的售后(申请中/处理中)禁止核销,防「既退款又提货」资损
        if (in_array($order->refund_status, [Order::REFUND_STATUS_APPLIED, Order::REFUND_STATUS_PROCESSING])) {
            throw new \Exception('该订单有售后申请处理中，请先处理售后再核销');
        }

        $allowedStatus = [Order::STATUS_TO_BE_SHIPPED, Order::STATUS_SHIPPED];
        if (!in_array($order->status, $allowedStatus) || $order->deliver_type != Order::DELIVER_TYPE_PICKUP) {
            throw new \Exception('订单状态异常(非待核销状态)，无法核销');
        }

        $order->status = Order::STATUS_COMPLETED;
        $order->receipt_status = Order::RECEIPT_STATUS_RECEIVED;
        $order->confirm_at = time();
        $order->finish_at = time();
        $order->clerk_id = $clerkId;
        $order->clerk_name = Yii::$app->user->identity->nickname ?? '平台账号';

        $order->settlement_status = Order::SETTLEMENT_STATUS_PENDING;

        if (!$order->save()) {
            throw new \Exception($this->getErrorMsg($order));
        }

        // 暂存已核销的核心单,供 verify() 写 ClerkLog 台账(单号+商品快照),避免读侧改读 ClerkLog 后核心单丢数据
        $this->verifiedNormalOrder = $order;

        try {
            Yii::$app->trigger(Order::EVENT_ORDER_CONFIRMED, new \app\events\OrderEvent(['order' => $order]));
        } catch (\Exception $e) {
            Yii::error("核销自提事件触发异常: " . $e->getMessage());
        }

        return true;
    }

    /** @var Order|null 本次已核销的核心自提单(verifyNormalOrder 命中时暂存,供 ClerkLog 台账取单号/商品) */
    private $verifiedNormalOrder = null;

    /**
     * 核销前预取插件侧的单号+商品快照,供 ClerkLog 台账。
     *
     * 必须在核销执行前调用:核销后订单已置完成态,部分插件的 getVerifyCodeDetail 会因状态校验
     * 返回 false 或抛异常,那时再取就取不到了。
     *
     * guarded:预览失败一律降级为空快照,绝不阻断核销主流程 —— 预览说"不可核销"不代表核销会失败
     * (强制核销就是这种场景),更不该因为台账取不到字段就让核销员核不了单。
     *
     * @return array{biz_type:string,biz_type_name:string,order_no:string,products:array}|array{}
     */
    private function capturePluginSnapshot(): array
    {
        try {
            $ctx = new VerifyContext(
                Yii::$app->mall->id, 0, (int)Yii::$app->user->id,
                Yii::$app->user->identity->nickname ?? '', (int)$this->is_manual_force, 'platform'
            );
            $res = (new VerifyGateway())->searchPlugins((string)$this->code, $ctx);
            if (!is_array($res)) {
                return [];
            }
            // 老钩子返回裸数组(名字在 data.name),新契约 DTO 多一个平铺的 type_name,两者都兼容
            return [
                'biz_type' => (string)($res['type'] ?? ''),
                'biz_type_name' => (string)($res['type_name'] ?? ($res['data']['name'] ?? '')),
                'order_no' => (string)($res['data']['order_no'] ?? ''),
                'products' => is_array($res['data']['products'] ?? null) ? $res['data']['products'] : [],
            ];
        } catch (\Throwable $e) {
            Yii::warning('[平台核销] 台账快照预取失败(不影响核销) code=' . $this->code . ' : ' . $e->getMessage(), 'clerk');
            return [];
        }
    }

    /** 构造核心单商品快照 [{name,image,spec,num}] 供 ClerkLog */
    private function normalOrderProducts(Order $order): array
    {
        $products = [];
        foreach ($order->orderDetails as $detail) {
            $products[] = [
                'name' => $detail->snapshotName() ?: '商品已失效',   // 下单时的商品(快照),老数据回退商品表
                'image' => $detail->snapshotPic(),
                'spec' => '',
                'num' => $detail->num,
            ];
        }
        return $products;
    }
}
