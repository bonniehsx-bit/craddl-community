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

use app\helpers\CacheHelper;
use app\helpers\ConstantHelper;
use app\helpers\OptionHelper;
use app\models\CommonPayOrder;
use Yii;
use yii\helpers\Json;
use app\payments\contracts\MerchantApplicationPaymentInterface;

/**
 * 通用企业微信客服引导支付网关
 *
 * @desc 该网关不直接处理资金交易。
 *       它的作用是生成一个携带订单号(scene)的企业微信客服跳转链接。
 *       用户点击链接 -> 跳转企业微信 -> 触发enter_session事件 -> 服务端自动回复H5支付卡片。
 */
class WxWorkPayment extends BasePayment implements MerchantApplicationPaymentInterface
{
    // 企业微信 API 基础地址
    private const API_BASE_URL = 'https://qyapi.weixin.qq.com/cgi-bin/';

    // 缓存键前缀
    private const CACHE_ACCESS_TOKEN = 'wxwork:generic:access_token:';
    private const CACHE_KF_LIST = 'wxwork:generic:kf_list:';

    public function __construct($mall_id)
    {
        $this->mall_id = $mall_id;
        // 获取系统配置中的支付配置
        $payment = OptionHelper::get('payments', $mall_id);

        // 检查配置是否存在
        if (!$payment || !isset($payment[self::class])) {
            throw new \InvalidArgumentException('企业微信客服支付[全局配置]未找到');
        }
        $this->config = $payment[self::class];

        // 检查是否开启
        if (empty($this->config['status'])) {
            throw new \InvalidArgumentException('企业微信客服支付[全局配置]未开启');
        }

        // 初始化基础信息
        $this->logo = $this->config['logo'] ?? '';
        $this->name = $this->config['name'] ?? '企业微信支付'; // 前端显示的支付方式名称
        $this->desc = $this->config['desc'] ?? '联系客服进行支付';
        $this->payment_type = self::class;
    }

    /**
     * 把收款主体切换为商户的进件应用（MerchantApplicationPaymentInterface）。
     * 派生出的配置直接作为 placeOrder() 的第二形参 $mchConfig 传入。
     */
    public function withMerchantApplication(array $config, string $applicationId): array
    {
        if (trim($applicationId) === '') {
            throw new \InvalidArgumentException('该商户尚未完成分账进件，无法作为收款主体');
        }
        $config['applicationId'] = $applicationId;
        return $config;
    }

    /**
     * 统一下单 (核心逻辑)
     *
     * @param array $orderData 订单数据，包含 order_no, pay_price 等
     * @param array|null $mchConfig 商户独立配置 (可选)
     * @return mixed
     * @throws \Exception
     */
    public function placeOrder($orderData, $mchConfig = null)
    {
        // 1. 确定配置 (优先使用传入的商户配置，否则使用全局配置)
        $activeConfig = $mchConfig ?? $this->config;
        $this->_validateConfig($activeConfig);

        // 2. 更新数据库订单记录 (标记该订单使用了“企业微信客服”这种支付方式)
        // 这一步很重要，因为回调时需要知道当初是用什么配置发起的
        CommonPayOrder::updateAll(
            [
                'payment' => self::class,
                'payment_config' => Json::encode($activeConfig)
            ],
            ['id' => $orderData['pay_order_id']]
        );

        // 3. 获取企业微信 AccessToken
        $accessToken = $this->_getAccessToken($activeConfig);

        // 4. 获取可用的客服账号ID (OpenKfId)
        $openKfId = $this->_getOpenKfId($activeConfig, $accessToken);

        // 5. 调用企业微信接口：获取客服帐号链接 (add_contact_way)
        $url = self::API_BASE_URL . 'kf/add_contact_way?access_token=' . $accessToken;

        // 构造请求参数
        $bizContent = [
            'open_kfid' => $openKfId,
            // 【关键】scene 存 order_no,不能存 out_trade_no。
            //
            // 两个理由:
            //  ① 对不上 —— out_trade_no 是「order_no 前缀 + 时间戳」(如 C260821xxx_1787295080),
            //     而回来时 WxWorkHelper::getPayOrderByOrderNo 是按 order_no 查的,
            //     两者永远不相等,查不到订单就不会发支付链接,链路断在这里。
            //     后面菜单点击那条路用的 menuId 也是 order_no,本来就自洽,是这里跑偏了。
            //  ② 就算把查询改成按 out_trade_no 也不对 —— add_contact_way 生成的客服链接
            //     是长期有效的,而 out_trade_no 每次重新发起支付都会换。用户存了旧链接
            //     再点进来,scene 是旧号、库里已经换了新号,照样查不到。
            //     scene 的用途是「回来时认出是哪张单」,该用恒定标识。
            'scene' => (string) ($orderData['order_no'] ?? ''),
        ];

        Yii::warning("企业微信生成客服链接请求: " . Json::encode($bizContent), 'payment');

        $result = $this->_postRequest($url, $bizContent);

        // 6. 处理接口返回
        if (isset($result['errcode']) && $result['errcode'] !== 0) {
            throw new \Exception('生成跳转链接失败：' . ($result['errmsg'] ?? '企业微信接口错误'));
        }

        // 7. 返回结果给前端
        // 前端拿到这个 url 后，直接跳转即可唤起企业微信
        return [
            'type' => 'url', // 告诉前端这是 URL 跳转类型
            'url' => $result['url'],
            'desc' => '即将跳转至企业微信客服...'
        ];
    }

    /**
     * 验证配置参数有效性
     */
    private function _validateConfig(array $config)
    {
        // 必须配置企业ID和应用Secret
        $requiredKeys = ['corpId', 'corpSecret'];
        foreach ($requiredKeys as $key) {
            if (empty($config[$key])) {
                throw new \InvalidArgumentException("支付配置缺少关键参数：{$key}");
            }
        }
    }

    /**
     * 获取 AccessToken (带缓存机制)
     */
    private function _getAccessToken($config)
    {
        // 缓存 Key 区分不同的企业ID
        $cacheKey = self::CACHE_ACCESS_TOKEN . $config['corpId'];
        $token = CacheHelper::get($cacheKey);

        if ($token) {
            return $token;
        }

        // 调用接口获取 Token
        $url = self::API_BASE_URL . "gettoken?corpid={$config['corpId']}&corpsecret={$config['corpSecret']}";
        $res = $this->_getRequest($url);

        if (isset($res['errcode']) && $res['errcode'] === 0) {
            $token = $res['access_token'];
            // 缓存 7000 秒 (官方有效期 7200 秒)
            CacheHelper::set($cacheKey, $token, 7000);
            return $token;
        }

        throw new \Exception("获取AccessToken失败: " . ($res['errmsg'] ?? '未知错误'));
    }

    /**
     * 获取客服帐号ID (OpenKfId)
     */
    private function _getOpenKfId($config, $accessToken)
    {
        // 1. 如果配置中指定了具体的客服ID，直接使用 (推荐，速度最快)
        if (!empty($config['kfId'])) {
            return $config['kfId'];
        }

        // 2. 尝试从缓存获取 (避免每次都拉取列表)
        $cacheKey = self::CACHE_KF_LIST . $config['corpId'];
        $kfId = CacheHelper::get($cacheKey);
        if ($kfId) {
            return $kfId;
        }

        // 3. 调用接口拉取客服列表
        $url = self::API_BASE_URL . "kf/account/list?access_token=" . $accessToken;
        $res = $this->_getRequest($url);

        if (isset($res['errcode']) && $res['errcode'] === 0 && !empty($res['account_list'])) {
            // 遍历列表，找到第一个有“管理权限”的客服
            foreach ($res['account_list'] as $account) {
                // manage_privilege: 当前调用接口的应用是否具有管理该账号的权限
                if (isset($account['manage_privilege']) && $account['manage_privilege'] == true) {
                    $kfId = $account['open_kfid'];
                    // 缓存 1 天
                    CacheHelper::set($cacheKey, $kfId, 86400);
                    return $kfId;
                }
            }
        }

        throw new \Exception("未找到可用的企业微信客服账号，请在后台配置 'kfId' 或检查企微后台设置");
    }

    /**
     * Curl POST 辅助方法
     */
    private function _postRequest($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, Json::encode($data, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // 生产环境建议开启证书校验
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Yii::error("Curl Post Error: " . $error, 'payment');
            throw new \Exception('网络请求失败');
        }
        return Json::decode($response);
    }

    /**
     * Curl GET 辅助方法
     */
    private function _getRequest($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Yii::error("Curl Get Error: " . $error, 'payment');
            throw new \Exception('网络请求失败');
        }
        return Json::decode($response);
    }

    // ======================= 接口必须实现的方法 (占位) =======================

    /**
     * 资金流异步回调 (不需要处理)
     * 因为实际支付是在H5网关完成的，资金回调会去那个网关，而不是这里。
     */
    public function handleCallback($callbackData)
    {
        return ['code' => 'SUCCESS', 'msg' => 'Ignore'];
    }

    /**
     * 退款 (不支持)
     * 企业微信只是个聊天窗口，无法退款。退款需走原H5支付渠道。
     */
    protected function doRefund($order)
    {
        throw new \Exception('请通过原始支付渠道(如H5支付配置)进行退款，企业微信客服通道不支持直接退款。');
    }

    /**
     * 查单 (不支持)
     */
    public function queryOrder($queryData)
    {
        return null;
    }

    /**
     * 获取前端显示配置
     */
    public function getDisplay()
    {
        return [
            'logo' => $this->logo,
            'name' => $this->name,
            'desc' => $this->desc,
            'payment_type' => self::class
        ];
    }

    /**
     * 适用的平台
     */
    public static function supportedPlatforms(): array
    {
        return [
            ConstantHelper::PLATFORM_APP
        ];
    }

    /**
     * 获取配置
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * 分账/放款(不支持)
     *
     * 与 doRefund / handleCallback / queryOrder 同一个姿态:企微只是把用户从 App
     * 领进微信会话的桥,真正收款的是中转页里选的网关(见 WxWorkPayController)。
     * 订单一旦进入支付,common_pay_order.payment 就已经被改写成那个真实网关,
     * 分账自然该由它来做。
     *
     * 所以走到这里只有一种可能:上游没有按 payment 字段分发,而是错拿了企微这个桥。
     * 与其静默返回 null 让调用方当成「分账成功」继续往下走,不如当场喊出来。
     *
     * @throws \Exception 总是抛出
     */
    public function applySettleOrder($settleOrderData, $applicationId = null)
    {
        throw new \Exception(
            '企业微信客服通道不参与实际收款,不能对它发起分账/放款;'
            . '请按支付单的 payment 字段找到真实收款网关再调用。'
        );
    }
}