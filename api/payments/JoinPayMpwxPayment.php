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

use app\core\ApiCode;
use app\helpers\ConstantHelper;
use app\helpers\OptionHelper;
use app\helpers\ResponseHelper;
use app\helpers\ServerHelper;
use app\models\CommonPayOrder;
use EasyWeChat\Kernel\Exceptions\InvalidArgumentException;
use joinpay\entity\OrderQuery;
use joinpay\entity\Refund;
use joinpay\entity\UniPay;
use joinpay\JoinPayClient;
use Yii;
use yii\helpers\Json;

class JoinPayMpwxPayment extends BasePayment
{

    public function __construct($mall_id)
    {
        // ⛔ 本类依赖的 SDK(composer 包 jxmall/joinpay,命名空间 joinpay\…)已不在仓库里:
        //    composer.lock 零引用、git 历史里只在 eb8565f5f6 那次 vendor 入库出现过、线上 vendor 也没有。
        //    仓库现有的 api/common/joinpay/ 是另一套新版 SDK(app\common\joinpay\…),没有 JoinPayClient/UniPay。
        //    而后台「汇聚通道」页仍能把它打开 —— 打开后收银台会列出它,用户一点就在 placeOrder 里
        //    撞 `Class "joinpay\JoinPayClient" not found`(\Error,PayForm 的 catch \Exception 接不住)→ 500。
        //    这里在构造期就明确失败:PayForm 列支付方式时 catch 到就跳过,收银台不再展示;
        //    别的入口硬调也拿到一句能看懂的话,而不是 500。SDK 装回来后本守卫自动失效,不用改代码。
        if (!class_exists('joinpay\JoinPayClient') || !class_exists('joinpay\entity\UniPay')) {
            Yii::error("[JoinPayMpwxPayment] 汇聚支付 SDK(joinpay\\JoinPayClient)未安装,通道不可用 mall_id={$mall_id}", 'payment');
            throw new \InvalidArgumentException('汇聚支付 SDK 未安装,通道不可用');
        }
        //初始化支付参数
        $payment = OptionHelper::get('payments', $mall_id);
        if (!$payment) {
            throw new InvalidArgumentException('微信支付未开启');
        }
        $payment = $payment[self::class];
        if (!$payment['status']) {
            throw new InvalidArgumentException('微信支付未开启');
        }
        $this->logo = $payment['logo'];
        $this->name = $payment['name'];
        $this->desc = $payment['desc'];
        $this->payment_type = self::class;
        if (!$payment['status']) {
            throw new \InvalidArgumentException('汇聚支付未开启');
        }

        $this->config = [
            // 商户ID
            'app_id' => $payment['app_id'], // 商户加密密钥
            'app_secret' => $payment['join_app_secret'], // 报备商户号
            'private_key' => $payment['join_private_key'], // 商户私钥
            'trade_merchantNo' => $payment['join_trade_merchant_no'], // 报备商户号
            'merchant_no' => $payment['join_merchant_no'],//
        ];
    }

    public function placeOrder($params)
    {
        $notify_url = '/notify/pay.php';
        $notify_url = ServerHelper::getHost() . $notify_url;
        $frpCode = 'WEIXIN_XCX';
        $joinPayClient = JoinPayClient::getInstance($this->config);
        /**
         * @var UniPay $joinPayClient ;
         */
        $joinPayClient = $joinPayClient->driver('UniPay');// 使用驱动方式重新构造订单查询
        $res = $joinPayClient
            ->setVerison('1.0')// 版本号
            ->setOrderNo($params['order_no'])//
            ->setAmount($params['price'])
            ->setFrpCode($frpCode)
            ->setProductName($params['goods_name'])
            ->setProductDesc($params['desc'])
            ->setNotifyUrl($notify_url)
            ->setOpenId($params['open_id'])
            ->setMp(Json::encode(['class' => self::class, 'mall_id' => $this->mall_id]))
            ->send();
        if ($res && $res['ra_Code'] == 100) {
            $res = Json::decode($res['rc_Result']);
            if (isset($res['timeStamp'])) {
                $res['timestamp'] = $res['timeStamp'];
                unset($res['timeStamp']);
            }
            return $res;
        }
        throw new \yii\base\Exception($res['rb_CodeMsg']);
    }

    protected function doRefund($order)
    {
        try {
            $joinPayClient = JoinPayClient::getInstance($this->config);
            /**
             * @var OrderQuery $joinPayClient ;
             */
            $client = $joinPayClient->driver('Refund');// 使用驱动方式重新构造订单查询
            /**
             * @var Refund $client ;
             */
            $res = $client
                ->setVerison('2.1')
                ->setOrderNo($order['order_no'])// 原支付订单号
                ->setRefundOrderNo($order['refund_order_no'])// 退款订单号
                ->setRefundAmount($order['refund_price'])// 退款金额
                ->setRefundReason('订单退款')// 退款原因
                ->setNotifyUrl(ServerHelper::getHost() . '/notify/refund.php')// 服务器异步通知地址
                ->send();
            if ($res && is_array($res)) {
                if ($res['ra_Status'] == 100) {
                    return ResponseHelper::send(ApiCode::CODE_SUCCESS, $res['rc_CodeMsg']);
                }
            }
            throw new \Exception($res['rc_CodeMsg'] ?? '退款请求失败');
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function handleCallback($callbackData)
    {
        try {
            // 签名验证：防止伪造回调
            if (!$this->verifyCallbackSign($callbackData)) {
                Yii::error('[JoinPay] 回调签名验证失败, data=' . Json::encode($callbackData), __METHOD__);
                return ['code' => 'FAIL', 'msg' => '签名验证失败'];
            }

            $order_no = $callbackData['r2_OrderNo'] ?? '';
            if (empty($order_no)) {
                return ['code' => 'FAIL', 'msg' => '缺少订单号'];
            }

            $order = CommonPayOrder::findOne(['order_no' => $order_no]);
            if ($order && !$order->is_pay) {
                $transactionId = $callbackData['r9_BankTrxNo'] ?? '';
                $order->paySuccess($transactionId, static::class);
            }
            return 'success';
        } catch (\Exception $e) {
            Yii::error('[JoinPay] 回调处理异常: ' . $e->getMessage(), __METHOD__);
            return ['code' => 'FAIL', 'msg' => $e->getMessage()];
        }
    }

    /**
     * 汇聚回调签名验证
     * 按照汇聚文档：将所有 r/p 开头参数按 key 排序拼接 + HMAC-MD5
     */
    private function verifyCallbackSign(array $data): bool
    {
        $sign = $data['hmac'] ?? '';
        if (empty($sign)) return false;

        unset($data['hmac']);
        ksort($data);
        $signStr = '';
        foreach ($data as $k => $v) {
            if ($v !== '' && $v !== null) {
                $signStr .= $v;
            }
        }
        $expectSign = md5($signStr . $this->config['app_secret']);
        return hash_equals($expectSign, $sign);
    }


    public function getDisplay()
    {
        return [
            'logo' => $this->logo,
            'name' => $this->name,
            'desc' => $this->desc,
            'payment_type' => self::class
        ];
    }

    public function queryOrder($queryData)
    {
        $orderNo = is_array($queryData) ? ($queryData['out_trade_no'] ?? $queryData['order_no'] ?? '') : $queryData;
        $joinPayClient = JoinPayClient::getInstance($this->config);
        $joinPayClient = $joinPayClient->driver('OrderQuery');
        return $joinPayClient->setOrderNo($orderNo)->send();
    }

    public static function supportedPlatforms(): array
    {
        // TODO: Implement platforms() method.
        return [
            ConstantHelper::PLATFORM_MPWX
        ];
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function applySettleOrder($settleOrderData, $extra = null)
    {
        // TODO: Implement applySettleOrder() method.
    }
}