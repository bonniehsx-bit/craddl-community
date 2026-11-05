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

namespace app\payments;

use app\models\CommonPayOrder;

abstract class BasePayment
{
    public $mall_id;
    public $logo;
    public $name;
    public $desc;
    public $payment_type;
    public $config;

// 抽象下单方法，子类必须实现
    abstract public function placeOrder($orderData);

    abstract public function applySettleOrder($settleOrderData, $applicationId = null);

    // 抽象退款方法，子类必须实现
    abstract protected function doRefund(array $refundData);

    /**
     * 退款入口（带金额校验）
     */
    public function refundOrder(array $refundData)
    {
        $refundPrice = (float)($refundData['refund_price'] ?? 0);
        if ($refundPrice <= 0) {
            throw new \Exception('退款金额必须大于0');
        }
        // 校验退款金额不超过支付金额
        if (isset($refundData['order_no'])) {
            $payOrder = CommonPayOrder::findOne(['order_no' => $refundData['order_no']]);
            if ($payOrder && $refundPrice > (float)$payOrder->pay_price) {
                throw new \Exception("退款金额({$refundPrice})超过支付金额({$payOrder->pay_price})");
            }
        }
        return $this->doRefund($refundData);
    }

    // 抽象回调方法，子类必须实现
    abstract public function handleCallback($callbackData);

    abstract public function getConfig();

    // 抽象查询方法，子类必须实现

    /**
     * @param $queryData
     * @return mixed
     * throw new \Exception('未查询到订单');
     */
    abstract public function queryOrder($queryData);

    /**
     * 退款查询 (如果未实现，默认返回未退款，以便业务走兜底重新退款)
     * @param array $queryData 包含 out_refund_no 等信息
     * @return array|null 返回 ['code' => API::SUCCESS, 'msg' => '已退款'] 等，否则抛出异常或返回空
     */
    public function queryRefund($queryData)
    {
        // 默认实现：如果不原生支持退款查询，抛出异常或返回未查到，使业务当做未退款处理
        throw new \Exception('该支付方式暂不支持退款独立查询或查询不到退款记录');
    }

    abstract public function getDisplay();

    /**
     * 适用平台。保留为实例方法只是为了不动既有调用方,实现一律落在
     * 静态的 supportedPlatforms() 上 —— 见下面那个方法的说明。
     */
    public function platforms()
    {
        return static::supportedPlatforms();
    }

    /**
     * 适用平台(静态)。
     *
     * 为什么要有静态版:平台是网关的固有属性,跟实例状态无关,但原来只有实例方法,
     * 想判断「这个网关支不支持某平台」就必须先 new 出来。而 new 是会失败的 ——
     * 网关构造函数普遍依赖后台配置(实测快付微信JS 缺全局配置会抛
     * InvalidArgumentException)。结果就是:一个「不支持该平台 + 配置又不全」的网关,
     * 报出来的是「配置不完整」,把运营指向了错误的方向。
     *
     * 有了静态版就能在实例化之前先筛平台,错误提示才指得准。
     * 子类只需覆盖这一个方法,platforms() 自动跟着走。
     *
     * @return string[] ConstantHelper::PLATFORM_* 常量数组
     */
    public static function supportedPlatforms(): array
    {
        return [];
    }

    /**
     * 是否「即时扣款型」网关 —— placeOrder 当场完成扣款,没有第三方收银台让用户确认。
     *
     * 默认 false(第三方网关都要用户在收银台点确认)。余额/积分这类走内部账户的要覆盖成 true。
     *
     * 为什么需要这个声明:有些场景是「先拿支付参数、再由用户决定付不付」——
     * 比如企微中转页,它只是替用户取一份 JSAPI 参数。这种地方接上即时扣款型网关,
     * 用户点一下链接钱就没了,连确认都没有。靠 platforms() 判断不出来
     * (余额支付确实支持微信平台),得单独声明。
     *
     * 与 supportedPlatforms() 同为静态:同样要能在实例化之前就判断。
     */
    public static function isInstantDebit(): bool
    {
        return false;
    }
}