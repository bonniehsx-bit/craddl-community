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

namespace app\forms\mall\payment;

use app\core\ApiCode;
use app\helpers\OptionHelper;
use app\helpers\ResponseHelper;
use app\payments\WxWorkPayment; // 引入我们之前写的支付网关类

class WxWorkPaymentForm extends PaymentModel
{
    public $setting;

    public function rules()
    {
        return [
            [['setting'], 'safe'],
        ];
    }

    public function save()
    {
        // 获取当前商户已有的支付配置
        $payment = $this->payment;

        // 简单校验：如果开启状态，必填项不能为空
        if (isset($this->setting['status']) && $this->setting['status'] == 1) {
            if (empty($this->setting['corpId']) || empty($this->setting['corpSecret'])) {
                return ResponseHelper::send(ApiCode::CODE_ERROR, '开启时必须填写企业ID(corpId)和应用Secret(corpSecret)');
            }
        }

        // 绑定支付类名
        $this->setting['payment_type'] = WxWorkPayment::class;

        // 存入配置数组
        $payment[WxWorkPayment::class] = $this->setting;

        // 保存到数据库 options 表
        OptionHelper::set('payments', $payment, \Yii::$app->mall->id);

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '保存成功');
    }

    public function search()
    {
        // 获取当前支付方式的配置
        $this->setting = $this->getPayment(WxWorkPayment::class);

        // 如果没有配置过，给一些默认值方便前端显示
        if (empty($this->setting)) {
            $this->setting = [
                'status' => '0', // 默认关闭
                'name' => '企业微信支付',
                'desc' => '联系客服获取支付链接',
                'logo' => '',
                'corpId' => '',
                'corpSecret' => '',
                'kfId' => '', // 客服账号ID (选填)
                'token' => '',
                'encodingAesKey' => '',
                // 实际收款网关:企微本身不收钱,只把用户从 App 领进微信,
                // 真正的 JSAPI 支付由这里指定的网关完成。空=沿用历史默认(见中转页)。
                'real_payment' => '',
            ];
        }

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', [
            'setting'          => $this->setting,
            'payment_options'  => $this->realPaymentOptions(),
        ]);
    }

    /**
     * 「实际收款网关」的候选列表。
     *
     * 规则而不是写死类名:本商城**已开启** ∩ platforms() 含 WECHAT ∩ 不是企微自己。
     *   · 含 WECHAT —— 中转页是在微信浏览器里跑 JSAPI,不支持这个平台的网关选了也拉不起
     *   · 排除自己 —— 否则中转页会再跳一次企微,自己套自己
     * 新增微信侧网关时会自动出现在这里,不用回来改代码。
     */
    private function realPaymentOptions(): array
    {
        $mallId = (int)\Yii::$app->mall->id;
        $payments = OptionHelper::get('payments', $mallId) ?: [];
        $out = [];

        foreach ($payments as $class => $conf) {
            if ($class === WxWorkPayment::class || empty($conf['status'])) {
                continue;
            }
            if (!class_exists($class)) {
                continue;   // 配置里可能残留已删除的网关类
            }
            // 静态判定排在前面,不合格的连 new 都不用 —— 网关构造函数依赖配置,
            // 能少实例化一个就少一次潜在异常,配置页也快一点。
            if (!in_array(\app\helpers\ConstantHelper::PLATFORM_WECHAT, (array)$class::supportedPlatforms(), true)) {
                continue;
            }
            // 即时扣款型(余额/积分这类)绝不能进候选:中转页只是替用户「取一份支付参数」,
            // 接上它就变成用户点一下链接钱当场就没了,连确认环节都没有。
            if ($class::isInstantDebit()) {
                continue;
            }

            // 名称优先用商城自己配的;没配才 new 出来取网关默认名。
            $label = $conf['name'] ?? '';
            if ($label === '') {
                try {
                    $label = \Yii::createObject($class, [$mallId])->getDisplay()['name'] ?? $class;
                } catch (\Throwable $e) {
                    // 单个网关实例化失败不能让整个配置页打不开,退化成用类名兜底
                    \Yii::warning("企微支付候选网关 {$class} 取名失败: " . $e->getMessage(), 'payment');
                    $label = $class;
                }
            }

            // appid 不一致的网关照常可选(那是它自己配置页该修的事),
            // 但要在这里就标出来 —— 运营正是在这个下拉里决定「谁来收钱」。
            $warn = \app\helpers\WechatPayAppIdHelper::mismatchWarning($class, $mallId);

            $out[] = ['value' => $class, 'label' => $label, 'warning' => $warn];
        }
        return $out;
    }
}