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

namespace app\controllers\wechat;

use app\controllers\BaseController;
use app\core\ApiCode;
use app\helpers\ConstantHelper;
use app\helpers\OptionHelper;
use app\helpers\ServerHelper;
use app\helpers\WechatHelper;
use app\helpers\UserHelper;
use app\models\CommonPayOrder;
use app\models\User;
use app\payments\FastPayWechatJsPayment;
use app\payments\WxWorkPayment;
use Yii;
use yii\helpers\Json;

/**
 * 企业微信客服支付专用中转控制器
 * 路径: /wechat/wx-work-pay/index
 */
class WxWorkPayController extends BaseController
{
    public $layout = false; // 不使用布局文件
    public $enableCsrfValidation = false; // 关闭CSRF验证方便回调

    /**
     * 1. 支付中转页入口
     * URL: /wechat/wx-work-pay/index?mall_id=x&order_no=xxx
     */
    public function actionIndex()
    {
        $mallId = $this->request->get('mall_id');
        $pay_order_id = $this->request->get('pay_order_id');

        if (!$mallId || !$pay_order_id) {
            return "参数错误";
        }

        // 入口就校验订单归属,理由同 actionGetPayParams:mall_id 来自 URL 参数,
        // 不校验的话「别家订单 + 自己商城」这个组合能一路走到取支付参数那一步。
        // 放在授权之前:一是不让用户白跑一趟微信静默授权才发现单子不对,
        // 二是不给探测者留一个「授权后才报错」的时间差去枚举订单号。
        if (!CommonPayOrder::find()->where(['id' => $pay_order_id, 'mall_id' => (int)$mallId])->exists()) {
            return "订单不存在";
        }

        // 尝试从 Session 获取 OpenID (避免重复授权)
        $sessionKey = "wx_pay_openid_{$mallId}";
        $openId = Yii::$app->session->get($sessionKey);

        // 如果没有 OpenID，去微信静默授权
        if (empty($openId)) {
            // 初始化微信配置
            WechatHelper::init($mallId);
            $app = Yii::$app->wechat->app;

            // 设置回调地址到 actionCallback
            $callbackUrl = ServerHelper::getHost() . "/index.php?r=/wechat/wx-work-pay/callback&mall_id={$mallId}&pay_order_id={$pay_order_id}";
            $redirectUrl = $app->getOAuth()->scopes(['snsapi_base'])
                ->redirect($callbackUrl);

            return Yii::$app->response->redirect($redirectUrl)->send();
        }

        // 如果有 OpenID，渲染支付视图
        return $this->render('index', [
            'mall_id' => $mallId,
            'pay_order_id' => $pay_order_id,
            'openid' => $openId
        ]);
    }

    /**
     * 2. 授权回调
     */
    public function actionCallback()
    {
        $mallId = $this->request->get('mall_id');
        $pay_order_id = $this->request->get('pay_order_id');
        $code = $this->request->get('code');

        if (empty($code)) {
            return "微信授权失败，请重试";
        }

        try {
            WechatHelper::init($mallId);
            $app = Yii::$app->wechat->app;

            // 用 code 换取用户信息 (v6 适配)
            $user = $app->getOAuth()->userFromCode($code);
            try {
                $openId = $user->getId();
            } catch (\Exception $e) {
                // 兼容可能出现的异常返回
                $raw = $user->getRaw();
                $openId = $raw['openid'] ?? '';
            }

            // 存入 Session
            $sessionKey = "wx_pay_openid_{$mallId}";
            Yii::$app->session->set($sessionKey, $openId);

            // 跳回入口页进行支付
            return $this->redirect([
                'index',
                'mall_id' => $mallId,
                'pay_order_id' => $pay_order_id
            ]);
        } catch (\Exception $e) {
            return "获取用户信息失败: " . $e->getMessage();
        }
    }

    /**
     * 3. 获取 JSAPI 支付参数 (AJAX 调用)
     * 这个接口逻辑模仿了 PayForm::payData，但它是后端自动决策支付方式
     */
    public function actionGetPayParams()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $pay_order_id = $this->request->post('pay_order_id');
        $openId = $this->request->post('openid');
        $mallId = $this->request->post('mall_id');

        if (!$pay_order_id || !$openId) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '参数缺失'];
        }

        // 1. 查找订单
        //
        // ⚠ 必须带 mall_id:这条链路上的 mall_id 全程来自 URL / POST 参数,
        //   而 pay_order_id 是自增主键、明文暴露在中转页链接里。只按 id 查的话,
        //   构造「别家商城的 pay_order_id + 自己商城的 mall_id」就能拿到
        //   本商城网关的支付参数去付别家的单 —— 钱进 A 商城的商户号、订单却是 B 商城的,
        //   跨租户资金串场。订单表本来就有 mall_id,加上即可。
        $order = CommonPayOrder::findOne(['id' => $pay_order_id, 'mall_id' => (int)$mallId]);
        if (!$order) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '订单不存在'];
        }
        if ($order->is_cancel) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '订单已关闭'];
        }
        if ($order->is_pay) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '订单已支付', 'data' => ['is_pay' => 1]];
        }

        // 2. 自动选择支付方式 (这是核心逻辑)
        // 我们不依赖前端传 payment_type，而是去检查后台配置了什么
        try {
            $paymentInstance = $this->getAvailablePaymentInstance($mallId);
        } catch (\Exception $e) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => $e->getMessage()];
        }

        // 3. 更新订单信息 (为了记录当前是用什么支付的)
        // 这里我们把 user_id 设置为 0 或者尝试通过 openid 查找用户，
        // 但在这个纯支付场景下，保持订单原有 user_id 即可，主要更新 payment_type
        $order->payment = get_class($paymentInstance);
        $order->pay_openid = $openId;
        $order->platform = ConstantHelper::PLATFORM_WECHAT; // 强制标记为微信环境
        $order->save();

        // 4. 构造下单数据 —— 必须走 CommonPayOrder::buildGatewayOrderData。
        //
        // 原来这里是手拼的,少了两个键,而且绕过了商户单号登记,三处都会让链路断掉:
        //   · out_trade_no 缺失 → 快付的 merOrderNo 取到 null,被 array_filter 滤掉,
        //     下单请求里根本没有商户订单号,网关必然报错
        //   · pay_order_id 缺失 → 网关内部那句
        //     CommonPayOrder::updateAll(..., ['id' => null]) 变成 WHERE id IS NULL,
        //     更新 0 行,payment_config 快照写不进去(回调要靠它还原当初用的配置)
        //   · 绕过 issueTradeNo → 发出去的商户单号没登记,回调匹配不到订单 = 掉单
        //
        // CommonPayOrder:174 本来就写着「所有发起支付的入口都必须走这里」,
        // PayForm / MpLaunchPayForm 都在走,这里是漏网的。
        //
        // 第三个参数 true(每次重新生成商户单号):中转页会被反复打开——用户点多次
        // 客服里的支付链接、页面刷新都会重来一次,微信也规定失败重试必须换单号。
        // 旧单号由 issueTradeNo 登记过,回调照样匹配得上,不会掉单。
        $orderData = $order->buildGatewayOrderData(
            $openId,
            '订单支付-' . $order->order_no,
            true,
            // 网关已可配,有的网关按 trade_type 区分下单场景,原样保留
            ['trade_type' => 'JSAPI']
        );

        try {
            // 调用具体支付类的下单方法
            $res = $paymentInstance->placeOrder($orderData);

            // 兼容性处理：有些支付类返回结构不同，这里统一处理成 JSAPI 所需格式
            // 假设 placeOrder 返回的就是标准数组或者包含相关字段
            return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '获取成功', 'data' => $res];
        } catch (\Exception $e) {
            Yii::error("中转页下单失败: " . $e->getMessage());
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '下单失败: ' . $e->getMessage()];
        }
    }

    /**
     * 支付工厂：获取优先级最高的可用支付实例
     */
    private function getAvailablePaymentInstance($mallId)
    {
        $payments = OptionHelper::get('payments', $mallId);
        if (empty($payments)) {
            throw new \Exception('未配置支付方式');
        }

        // 实际收款网关改为后台可配(企微支付配置页 → 实际收款网关)。
        // 原来这里写死 FastPayWechatJsPayment:商城若用的是别的微信侧网关,
        // C 端在第一步能看到并选中「企业微信支付」,要一路走到中转页才失败。
        $configured = $payments[WxWorkPayment::class]['real_payment'] ?? '';

        // 没配过就沿用历史默认,保证存量商城升级后行为不变
        $candidates = $configured !== '' ? [$configured] : [FastPayWechatJsPayment::class];

        foreach ($candidates as $class) {
            // 配置里可能残留已删除的网关类。这种情况要单独报,别跟「没开启」混成一句 ——
            // 两者的处理动作完全不同:一个去支付设置开启,一个得回来重选。
            if (!class_exists($class)) {
                throw new \Exception('企业微信配置的收款网关已不存在,请到企业微信支付配置页重新选择');
            }
            if (empty($payments[$class]['status'])) {
                continue;   // 配了但没开启,等同于不可用
            }

            // ⚠ 顺序要紧:平台与即时扣款这两道判定必须排在实例化**之前**。
            // 网关构造函数普遍依赖后台配置(实测快付微信JS 缺全局配置就抛
            // InvalidArgumentException),先 new 的话「不支持微信平台」会被
            // 「配置不完整」盖掉,把运营指向错误的方向。
            // 这两个属性都是网关固有的,已抽成静态方法,不用 new 就能问。

            // 中转页在微信浏览器里走 JSAPI,不支持该平台的网关拿不到可用支付参数
            if (!in_array(ConstantHelper::PLATFORM_WECHAT, (array)$class::supportedPlatforms(), true)) {
                throw new \Exception('所选收款网关不支持微信平台,请到企业微信支付配置页重新选择');
            }

            // 双保险:UI 已经把即时扣款型排除在候选之外,这里再挡一次 ——
            // 配置存在 option 里,绕过 UI 直接改库就能塞进来,
            // 而这条路一旦走通就是「用户点个链接余额被扣光」,不能只靠前端拦。
            if ($class::isInstantDebit()) {
                throw new \Exception('余额/积分这类即时扣款方式不能作为企业微信的收款网关,请重新选择');
            }

            // 前面两道都过了才实例化。这里仍要接住异常:构造函数依赖配置,
            // 缺参数时会抛底层错误,不接住会原样甩到用户脸上。
            try {
                return \Yii::createObject($class, [$mallId]);
            } catch (\Throwable $e) {
                \Yii::error("企微中转页实例化收款网关 {$class} 失败: " . $e->getMessage(), 'payment');
                throw new \Exception('所选收款网关配置不完整,请到支付设置里补全它的参数');
            }
        }

        throw new \Exception($configured !== ''
            ? '企业微信配置的收款网关未开启,请到支付设置里启用它'
            : '未找到可用的微信JSAPI支付通道');
    }
}
