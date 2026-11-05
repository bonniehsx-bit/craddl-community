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

namespace app\forms\api\pay;

use app\core\ApiCode;
use app\helpers\ConstantHelper;
use app\helpers\LockHelper;
use app\helpers\OptionHelper;
use app\helpers\ResponseHelper;
use app\helpers\UserHelper;
use app\helpers\WechatHelper;
use app\models\BaseModel;
use app\models\CommonPayOrder;
use app\models\Order;
use app\models\UserBankCard;
use app\payments\BalancePayment;
use app\payments\BasePayment;
use app\payments\contracts\QuickPaymentInterface;
use app\services\order\OrderTypeRegistry;

class PayForm extends BaseModel
{
    public $payment_type;
    public $order_no;
    public $pay_order_id;
    public $openid;
    public $code; // 接收前端传来的 wxCode

    // 余额支付 —— 商城开启"余额支付验证密码"后必须带支付密码
    public $pay_password;

    // 银行卡快捷支付 —— 用户选择的已绑卡记录 id (user_bank_card.id)
    public $bind_card_id;

    public function rules()
    {
        return [
            [['pay_order_id', 'bind_card_id'], 'integer'],
            [['payment_type', 'order_no', 'openid', 'code', 'pay_password'], 'string'],
            [['pay_password'], 'trim'],
        ];
    }

    /**
     * 支付核心数据获取
     */
    /**
     * 发起支付 —— 并发闸门。真正的逻辑在 doPayData()。
     *
     * 为什么必须串行:本方法每次调用都会重算 out_trade_no 并覆盖主表那一列(见下方)。
     * 用户连点、或网络慢时手动重试,会并发进来生成**多个都能付款的预支付单**。
     * 后果有二:
     *   · 旧号的回调匹配不到订单 → 掉单(这条已由 CommonPayOrder::findByTradeNo 兜住)
     *   · 用户把两个都付了 → 重复支付,只有一笔能核销,另一笔得人工退款(兜不住)
     * 所以入口这里按支付单 ID 上锁,把重复发起挡在生成新单号之前。
     *
     * 锁 10 秒:够覆盖一次网关下单往返,又不会让用户换支付方式时等太久。
     */
    public function payData()
    {
        $order = $this->findPayOrder();

        if (!$order) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '订单不存在或已支付');
        }

        $lockKey = 'PAY_DATA_' . $order->id;
        $lockToken = LockHelper::lock($lockKey, 10);
        if (!$lockToken) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '支付处理中，请勿重复提交');
        }
        try {
            return $this->doPayData($order);
        } finally {
            LockHelper::unlock($lockKey, $lockToken);
        }
    }

    private function doPayData(CommonPayOrder $order)
    {
        if ($order->is_cancel) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '订单已关闭');
        }
        if ($order->is_pay) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '支付成功', ['is_pay' => 1]);
        }
        if ($order->user_id && $order->user_id != UserHelper::getMyId()) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '无权操作此订单');
        }

        $mallOrder = Order::findOne($order->order_id);
        if ($mallOrder && $mallOrder->status != Order::STATUS_UNPAID) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '订单状态不正确!');
        }

        // --- 0元订单(如纯积分换购)走统一 paySuccess 流程 ---
        if ($order->pay_price <= 0) {
            if (!$order->paySuccess('ZERO_' . date('YmdHis') . '_' . $order->id, 'zero_payment')) {
                return $this->apiResponse(ApiCode::CODE_FAIL, '0元单处理失败');
            }
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '支付成功', ['is_pay' => 1]);
        }

        // ⛔ 网关限制的第二道(也是真正管用的那道)。
        //    getPayment 只是不把网关列出来,而 payment_type 是前端传上来的 ——
        //    改一个参数就能拿被禁的网关付款,所以这里必须再判一次。
        //    金额为 0 的单在上面已经直接结单返回了,走不到这里,不受影响。
        if ($this->payment_type) {
            $payments = OptionHelper::get('payments', $this->mall_id);
            $cfg = (is_array($payments) && isset($payments[$this->payment_type]) && is_array($payments[$this->payment_type]))
                ? $payments[$this->payment_type] : [];
            $scope = $mallOrder ? [(string) $mallOrder->plugin, (string) $mallOrder->type] : null;
            if (!self::gatewayAllowed($cfg, $scope)) {
                return ResponseHelper::send(ApiCode::CODE_FAIL, '该支付方式不支持此类订单,请重新选择');
            }
        }

        // --- 核心逻辑：如果有 code，则去微信换取 openid ---
        if ($this->code && empty($this->openid)) {
            try {
                WechatHelper::init(\Yii::$app->mall->id);
                $app = \Yii::$app->wechat->miniProgram;
                if ($app) {
                    $session = $app->getUtils()->codeToSession($this->code);
                    if (isset($session['openid'])) {
                        $this->openid = $session['openid'];
                    } else {
                        \Yii::warning("小程序Code换取OpenID失败: " . json_encode($session));
                    }
                }
            } catch (\Exception $e) {
                \Yii::warning("小程序支付获取OpenID异常: " . $e->getMessage());
                return ResponseHelper::send(ApiCode::CODE_FAIL, '微信授权失败: ' . $e->getMessage());
            }
        }
        // --------------------------------------------------------

        if (!$this->openid) {
            $this->openid = UserHelper::getOpenId();
        }

        $transaction = \Yii::$app->db->beginTransaction();
        try {
            // 优化：防止 $mallOrder 为 null 时报错，退化使用原始 order_no 的前缀
            $baseOrderNo = $mallOrder ? $mallOrder->order_no : explode('_', $order->order_no)[0];
            // $order->order_no = $baseOrderNo . '_' . time(); // [已废弃] 不再修改原单号

            // 生成唯一的商户支付单号 (透传给微信/支付宝)。
            // 走 nextTradeNo 而不是直接拼 time():time() 只到秒,同一秒内点两次支付
            // 会生成完全相同的号,登记表里那行就仍记着上一次的支付类,
            // 回调按单号反查支付类时会分发到错的网关(实测线上已有一单撞上:57345)。
            $order->out_trade_no = $order->nextTradeNo($baseOrderNo);
            $order->user_id = UserHelper::getMyId();
            $order->platform = \Yii::$app->platform;
            $order->payment = $this->payment_type;
            $order->pay_openid = $this->openid;
            if (!$order->save()) {
                throw new \Exception('支付单保存失败: ' . $this->getErrorMsg($order));
            }

            /** @var BasePayment $payment */
            $payment = \Yii::createObject($order->payment, [$this->mall_id]);

            if ($mallOrder) {
                Order::updateAll(['payment_class' => $order->payment, 'payment_name' => $payment->name], ['id' => $order->order_id]);
            }

            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            return ResponseHelper::send(ApiCode::CODE_FAIL, $e->getMessage());
        }

        // 按下单平台生成微信/支付宝账单里显示的订单描述
        $bodyMap = [
            ConstantHelper::PLATFORM_WECHAT => '微信公众号下单',
            ConstantHelper::PLATFORM_MPWX => '微信小程序下单',
            ConstantHelper::PLATFORM_APP => 'APP下单',
        ];
        $body = $bodyMap[$order->platform] ?? '订单支付';

        // 统一构造(见 CommonPayOrder::buildGatewayOrderData 注释):
        // 同时给 order_no 与 out_trade_no —— 各网关读的键不一样,少给哪个都会静默变成 null。
        // out_trade_no 已在上面的事务里生成好,这里不再重复生成。
        $orderData = $order->buildGatewayOrderData((string) $order->pay_openid, $body, false);

        // 余额支付验密分支: 仅当商城开启 is_balance_pay_password 时生效
        // 用 BalancePayment 类判定, 将来其它基于钱包的支付类(如积分支付)需要单独加自己的开关
        if ($payment instanceof BalancePayment) {
            $mallSetting = OptionHelper::get('mall_setting', $this->mall_id, []);
            if (($mallSetting['is_balance_pay_password'] ?? '0') == '1') {
                if (!UserHelper::hasPayPwd()) {
                    return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', [
                        'is_pay'                => 0,
                        'need_set_pay_password' => 1,
                        'msg'                   => '您尚未设置支付密码,请先前往设置',
                    ]);
                }
                if (empty($this->pay_password)) {
                    return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', [
                        'is_pay' => 0,
                        'msg'    => '请输入支付密码',
                    ]);
                }
                if (!UserHelper::authPayPwd($this->pay_password)) {
                    return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', [
                        'is_pay' => 0,
                        'msg'    => '支付密码错误',
                    ]);
                }
            }
        }

        // 银行卡快捷支付分支: 必须带用户绑卡记录, 透传 bindCardId 给支付类并单独处理响应
        // 原因: 快捷支付 placeOrder 同步返回三种状态 (SUCCESS/DOING/FAIL), 前端需要知道 "待短信" 状态
        // 走 QuickPaymentInterface 判定, 未来新增汇付/杉德等快捷支付网关自动走本分支
        if ($payment instanceof QuickPaymentInterface) {
            if (empty($this->bind_card_id)) {
                return ResponseHelper::send(ApiCode::CODE_FAIL, '请选择支付银行卡');
            }
            $userBankCard = UserBankCard::findOne([
                'id'          => $this->bind_card_id,
                'user_id'     => $order->user_id,
                'gateway'     => $order->payment,
                'bind_status' => UserBankCard::STATUS_ACTIVE,
                'is_delete'   => 0,
            ]);
            if (!$userBankCard) {
                return ResponseHelper::send(ApiCode::CODE_FAIL, '所选银行卡不存在或未生效');
            }
            $orderData['bind_card_id']      = $userBankCard->bind_card_id;
            $orderData['user_bank_card_id'] = $userBankCard->id;

            try {
                $quickRes = $payment->placeOrder($orderData);
            } catch (\Exception $e) {
                // 下单异常: 走 code=0 + is_pay=0 + msg 结构, 前端统一展示
                return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', [
                    'is_pay' => 0,
                    'msg'    => $e->getMessage(),
                ]);
            }

            $quickStatus = $quickRes['status'] ?? '';
            if ($quickStatus === 'SUCCESS') {
                // 同步成功: 立刻落库 (异步 handleCallback 仍会做兜底, 双写安全)
                //
                // ⚠ 这里原来是「先 $fresh->is_pay = 1 并保存,再 $fresh->pay()」——
                // 那是空转:pay() 转调 paySuccess(),而它第一句就是
                // `if ($this->is_pay == 1) return true;`,刚存成 1 就立刻命中幂等返回,
                // **业务回调(发货/分润/事件)一次都不执行**,还顺带绕过了并发锁与
                // is_cancel 检查。直接交给 paySuccess 即可,它自己就是幂等的。
                $fresh = CommonPayOrder::findOne($order->id);
                if ($fresh) {
                    $txn = $quickRes['atuOrderNo'] ?? ($quickRes['channelFlowNo'] ?? '');
                    if (!$fresh->paySuccess((string) $txn, $fresh->payment ?: null)) {
                        \Yii::warning('[PayForm] 快捷支付同步成功但置已支付失败'
                            . "(可能已取消/并发锁),pay_order_id={$fresh->id}");
                    }
                }
                return ResponseHelper::send(ApiCode::CODE_SUCCESS, '支付成功', ['is_pay' => 1]);
            }
            if ($quickStatus === 'DOING' || $quickStatus === 'PROCESS') {
                $mobile = (string)$userBankCard->mobile;
                return ResponseHelper::send(ApiCode::CODE_SUCCESS, '短信验证码已下发', [
                    'is_pay'      => 0,
                    'need_sms'    => 1,
                    'order_no'    => $order->order_no,
                    'mobile_mask' => strlen($mobile) === 11
                        ? substr($mobile, 0, 3) . '****' . substr($mobile, -4)
                        : $mobile,
                ]);
            }
            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', [
                'is_pay' => 0,
                'msg'    => $quickRes['desc'] ?? '快捷支付下单失败',
            ]);
        }

        try {
            $res = $payment->placeOrder($orderData);
            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', $res);
        } catch (\Exception $exception) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $exception->getMessage());
        }
    }

    /**
     * 获取订单详情
     */
    public function getOrder()
    {
        $order = $this->findPayOrder();

        if (!$order) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '订单不存在或已支付');
        }
        // 🔒 归属校验。findPayOrder 是按 pay_order_id / order_no 全局查的,既不带 mall_id
        // 也不带 user_id;而本方法把整个模型塞进响应,少了这两道就是「知道一个自增 id
        // 就能读走别人的支付单」。隔壁 doPayData 有 user_id 判断,这里一直没有。
        // 报错文案与「订单不存在」保持一致,不泄露「这个 id 存在但不属于你」。
        //
        // 注意没有沿用 doPayData 里 `if ($order->user_id && ...)` 那种写法 —— 那个 && 意味着
        // user_id 为空就整条跳过。实库 57370 单里 0 单为空,不该把这个口子复制到新代码。
        if ((int) $order->mall_id !== (int) $this->mall_id
            || (int) $order->user_id !== (int) UserHelper::getMyId()) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '订单不存在或已支付');
        }
        if ($order->is_cancel) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '订单已关闭');
        }
        if ($order->is_pay) {
            // 带上摘要:支付弹窗按「已付成功」处理时要用 order_id 跳订单详情(0 元单下单即自动支付走的就是这里)
            return $this->apiResponse(ApiCode::CODE_FAIL, '支付成功', ['is_pay' => 1, 'summary' => $this->paySummary($order)]);
        }

        $order_no_parts = explode('_', $order->order_no);
        $order->order_no = $order_no_parts[0];

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', [
            'order'   => $order,
            'summary' => $this->paySummary($order),
        ]);
    }

    /**
     * 支付弹窗(jx-pay-sheet)要显示的摘要:买的是什么、还剩多久超时、付完跳哪张订单。
     * 只读、不影响 order 字段,老收银台页面不用这一节也照常工作。
     *
     * - order_id:主商城订单 id(付款成功后调用页跳订单详情用)。支付单一律由 PostCreationStage 写 order_id;
     *   老数据没有的给 0,前端退回订单列表
     * - expire_at / now:支付单过期时间(建单时按「未支付超时时间」算好的)和服务器当前时间,
     *   前端用两者的差倒计时,不依赖手机本地时钟
     */
    private function paySummary(CommonPayOrder $payOrder): array
    {
        $orderId = (int) $payOrder->order_id;
        $goodsName = '';
        $goodsCount = 0;
        if ($orderId > 0) {
            $snap = \app\models\OrderDetail::hasGoodsSnapshot();
            $details = \app\models\OrderDetail::find()
                ->select($snap ? ['goods_id', 'num', 'goods_name'] : ['goods_id', 'num'])
                ->where(['order_id' => $orderId, 'mall_id' => (int) $payOrder->mall_id, 'is_delete' => 0])
                ->orderBy(['id' => SORT_ASC])
                ->asArray()->all();
            foreach ($details as $d) {
                $goodsCount += (int) $d['num'];
            }
            if ($details) {
                // 下单那一刻的商品名(快照),老数据回退商品表
                $goodsName = (string) ($details[0]['goods_name'] ?? '');
                if ($goodsName === '') {
                    $goodsName = (string) \app\models\Goods::find()
                        ->select('name')->where(['id' => (int) $details[0]['goods_id']])->scalar();
                }
            }
        }

        return [
            'order_id'    => $orderId,
            'goods_name'  => $goodsName,
            'goods_count' => $goodsCount,
            'expire_at'   => (int) $payOrder->expire_at,
            'now'         => time(),
        ];
    }

    /**
     * 获取支持的支付方式列表
     */
    public function getPayment()
    {
        $payments = OptionHelper::get('payments', $this->mall_id);
        $newArr = [];

        // 增加数据健壮性判断
        if (empty($payments) || !is_array($payments)) {
            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', $newArr);
        }

        // 订单上下文(前端带了 pay_order_id / order_no 才有)。取不到就是 null,
        // 下面按「不限」处理 —— 宁可多列一个也不能让收银台空着,真正的把关在 payData。
        $orderScope = $this->resolveOrderScope();

        foreach ($payments as $key => $payment) {
            if (isset($payment['status']) && $payment['status'] == 1) {
                if (!self::gatewayAllowed($payment, $orderScope)) {
                    continue;   // 该网关不接这类订单
                }
                try {
                    /** @var BasePayment $paymentObj */
                    $paymentObj = \Yii::createObject($key, [$this->mall_id]);
                    if (in_array(\Yii::$app->platform, $paymentObj->platforms())) {
                        $display = $paymentObj->getDisplay();
                        // 前端据此判断是否需要在下单前弹出"选择银行卡"环节
                        // 未来任何 implements QuickPaymentInterface 的支付类都会自动带上该标记
                        $display['quick_pay'] = $paymentObj instanceof QuickPaymentInterface ? 1 : 0;
                        $newArr[] = $display;
                    }
                } catch (\Exception $e) {
                    // 支付方式初始化失败会导致该渠道对前端不可见，必须升级到 error 级别以便运维发现
                    \Yii::error("支付类[{$key}]初始化异常: " . $e->getMessage(), 'payment');
                }
            }
        }
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', $newArr);
    }

    /**
     * 取当前这笔支付对应的订单归属 [plugin, type]。
     *
     * 取不到返回 null —— 前端拉支付方式列表时可能还没有订单上下文。
     * 调用方按「不限」处理。
     */
    private function resolveOrderScope(): ?array
    {
        try {
            $payOrder = $this->findPayOrder();
            if (!$payOrder || !$payOrder->order_id) {
                return null;
            }
            $order = Order::findOne($payOrder->order_id);
            if (!$order) {
                return null;
            }
            return [(string) $order->plugin, (string) $order->type];
        } catch (\Throwable $e) {
            \Yii::warning('[pay] 解析订单归属失败: ' . $e->getMessage(), 'payment');
            return null;
        }
    }

    /**
     * 这个网关接不接这类订单。
     *
     * 规则存在 option `payments` 的每个网关配置下:
     *   'order_types' => ['mall', 'group', ...]   // 空 / 缺省 = 不限
     *
     * ⛔ 默认放行。默认拒绝的话,升级上去所有商城的支付方式会立刻全空 ——
     *    没人来得及先把每个网关配一遍,那是直接停止收款。
     *
     * ⛔ 这个方法必须被**列表和下单两处**调用。只在列表里藏起来是假限制:
     *    payment_type 是前端传上来的,改个参数就能用被禁的网关付款。
     *
     * @param array      $cfg   option payments 里该网关的配置
     * @param array|null $scope [plugin, type];null = 拿不到订单上下文,放行
     */
    private static function gatewayAllowed(array $cfg, ?array $scope): bool
    {
        $keys = $cfg['order_types'] ?? [];
        if (!is_array($keys) || empty($keys)) {
            return true;    // 没配 = 不限
        }
        if ($scope === null) {
            return true;    // 没有订单上下文,交给 payData 那道把关
        }
        return OrderTypeRegistry::matches($keys, $scope[0], $scope[1]);
    }

    /**
     * 提取公共查询方法，减少代码冗余
     * @return CommonPayOrder|null
     */
    private function findPayOrder()
    {
        if ($this->pay_order_id) {
            return CommonPayOrder::findOne($this->pay_order_id);
        }
        if ($this->order_no) {
            return CommonPayOrder::findOne(['order_no' => $this->order_no]);
        }
        return null;
    }
}
