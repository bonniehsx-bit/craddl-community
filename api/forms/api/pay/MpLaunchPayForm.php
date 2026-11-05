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
use app\helpers\CacheHelper;
use app\helpers\WechatHelper;
use app\models\BaseModel;
use app\models\Order;
use app\models\User;
use Exception;
use Yii;

/**
 * [最终重构版] - 严格参考 payData 逻辑
 */
class MpLaunchPayForm extends BaseModel
{
    public $key;
    public $code;

    public function rules()
    {
        return [
            [['key', 'code'], 'required'],
            [['key', 'code'], 'string'],
        ];
    }

    public function launch()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }


        try {

            $cacheData = CacheHelper::get($this->key);
            if (empty($cacheData)) {
                throw new Exception('支付链接已失效，请返回APP重试');
            }
            // 【关键安全步骤】获取后立即删除，确保key只能使用一次

            CacheHelper::remove($this->key);

            $order_no = $cacheData['order_no'];
            $payment_gateway = $cacheData['payment_gateway'];

            // 步骤 2: 查询订单，并使用 CommonPayOrder 以获取通用支付信息
            /** @var \app\models\CommonPayOrder $order */
            $order = \app\models\CommonPayOrder::findOne(['order_no' => $order_no]);
            if (!$order) {
                throw new Exception('订单不存在');
            }
            if ($order->is_pay) {
                throw new Exception('订单已支付');
            }

            // 步骤 3: 使用 code 换取 openid
            WechatHelper::init($order->mall_id);
            $app = Yii::$app->wechat->miniProgram;
            if (!$app) {
                throw new Exception('小程序配置异常');
            }
            $session = $app->getUtils()->codeToSession($this->code);
            if (isset($session['errcode'])) {
                throw new Exception('微信登录失败: ' . $session['errmsg']);
            }
            $openid = $session['openid'];

            // 步骤 4: 身份校验（⚠ 绝不改写 App 登录态!）
            // 原实现在此为小程序 mint 新 access_token 并改写 user.access_token/auth_key/openid。
            // 但 API 鉴权按 user.access_token 列单一匹配 → 一改写,App 端手里的旧 token 立即失效,
            // 用户从小程序付完款「返回APP」后下一个请求就 401 被登出。
            // 本中转页付完款只显示结果+返回,不需要登录态;openid 直接透传给 placeOrder 即可,无需落库。
            if (empty($order->user_id)) {
                throw new Exception('订单未关联用户，无法支付');
            }
            $user = User::findOne($order->user_id);
            if (!$user) {
                throw new Exception('订单关联的用户不存在');
            }

            // 步骤 5: 动态实例化支付网关（参考 payData 逻辑）
            if (!class_exists($payment_gateway)) {
                throw new Exception('支付网关不存在');
            }
            /** @var \app\payments\BasePayment $payment */
            $payment = new $payment_gateway($order->mall_id);

            // 【必须在登记商户单号之前设好 payment】
            // 下面 buildGatewayOrderData 内部会调 issueTradeNo 把这个号登记进
            // common_pay_order_trade_no,而它记的「这个号用哪个支付类」读的正是 $order->payment。
            // placeOrder 内部虽然也会 updateAll 写这个字段,但那是在登记**之后**发生的 ——
            // 不先设,登记表就会记成【上一次】的支付类。
            // 回调按单号反查支付类时(CommonPayOrder::resolvePaymentByTradeNo)会因此分发到
            // 错误的网关,重演 57311 那类掉单。
            $order->payment = $payment_gateway;
            $order->save(false, ['payment']);

            // 步骤 6: 准备传递给支付网关的数据（参考 payData 逻辑）
            $body = '商城下单订单支付'; // 优先使用订单自带的body

            // 统一构造(见 CommonPayOrder::buildGatewayOrderData 注释):
            // 原来这里只给 order_no,而多数网关读的是 out_trade_no —— 走本入口时那个键恒为
            // undefined,商户单号会被网关内部的 array_filter 静默滤掉。
            // renew=true:本入口是一次新的支付发起(App 跳小程序),必须用新的商户单号,
            // 复用上次 PayForm 生成的那个会被网关判为单号重复而拒单。
            $orderData = $order->buildGatewayOrderData((string) $openid, $body, true);

            // 注意：更新订单支付方式的职责，已在之前的优化中移交给了 placeOrder 方法内部
            $payData = $payment->placeOrder($orderData);

            // 步骤 7: 只返回支付参数（不再下发登录 token,避免破坏 App 登录态）
            $responseData = [
                'pay_data' => $payData,
            ];

            return $this->apiResponse(ApiCode::CODE_SUCCESS, '支付参数获取成功', $responseData);
        } catch (Exception $e) {
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }
}
