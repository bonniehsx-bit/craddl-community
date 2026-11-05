<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
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

namespace app\helpers;


use app\core\ApiCode;
use Overtrue\EasySms\EasySms;
use Overtrue\EasySms\Exceptions\NoGatewayAvailableException;

class SmsHelper
{


    /**
     * 验证码短信统一入口。
     *
     * 原先这套渠道分发在 SmsCodeForm 里被完整复制了一遍(自己读 mall_setting、
     * 自己 if aliyun / if tx / if dxb、自己构造 EasySms config),和 sendNotify
     * 读的是同一批配置字段。复制的代价不只是多写代码 —— dxb 分支漏掉了
     * applyDxbSign(),导致同一个商城「业务通知带签名、验证码不带签名」。
     * 收口到这里之后,签名、渠道、异常处理全都只有一份。
     *
     * 三种渠道的模板都取自 mall_setting,调用方只管把码交进来:
     *   aliyun → sms_tpl_code(模板ID) + sms_tpl(变量名)
     *   tx     → tx_template_id(模板ID),变量按位置传
     *   dxb    → dxb_content(含 {code} 占位),签名由 sendNotify 自动前置
     *
     * @return array ['code'=>int, 'msg'=>string, 'provider'=>string] 同 sendNotify
     */
    public static function sendVerifyCode(int $mallId, string $phone, string $code): array
    {
        $setting = OptionHelper::get('mall_setting', $mallId);
        if (!$setting) {
            \Yii::warning("[SmsHelper] mall_setting 未配置 mall_id={$mallId}");
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '未配置短信服务', 'provider' => '-'];
        }

        // dxb 走原文,把模板里的 {code} 占位换成真码。
        // 没配 dxb_content 时给一句兜底文案 —— sendNotify 对空 content 是直接拒发的,
        // 那样管理员会看到「发送失败」却查不出原因。
        $content = str_replace('{code}', $code, (string)($setting['dxb_content'] ?? ''));
        if (trim($content) === '') {
            $content = '您的验证码为: ' . $code;
        }

        return self::sendNotify($mallId, $phone, $content, [
            'aliyun_tpl'  => $setting['sms_tpl_code'] ?? '',
            // 变量名是后台配的(阿里模板里写成什么就填什么),没配则退回最常见的 code。
            // 先 ?? 再 ?::单用 ?: 的话,键不存在会抛 "Undefined array key" warning 污染日志。
            'aliyun_vars' => [(($setting['sms_tpl'] ?? '') ?: 'code') => $code],
            'tx_tpl'      => $setting['tx_template_id'] ?? '',
            'tx_vars'     => [$code],
        ]);
    }

    /**
     * 业务短信统一入口 (催收 / 账单下发 / 合同临期 等)
     *
     * 对接三种服务商: aliyun / tx / dxb ,自动读取 mall_setting.sms_type 分发
     *   - dxb: 直接发 $content 原文
     *   - aliyun / tx: 需要 $tplOpts 里提供模板 ID + 变量映射 (否则降级忽略)
     *
     * @param int    $mallId   商城 ID
     * @param string $phone    手机号
     * @param string $content  dxb 原文 / 日志内容
     * @param array  $tplOpts  ['aliyun_tpl'=>'SMS_xxx', 'aliyun_vars'=>['code'=>'1234'],
     *                          'tx_tpl'=>12345,        'tx_vars'=>['1234']]
     *
     * @return array ['code'=>int, 'msg'=>string, 'provider'=>string]
     */
    public static function sendNotify(int $mallId, string $phone, string $content, array $tplOpts = []): array
    {
        if (!$phone || !$content) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => 'phone 或 content 为空', 'provider' => '-'];
        }

        $setting = OptionHelper::get('mall_setting', $mallId);
        if (!$setting) {
            \Yii::warning("[SmsHelper] mall_setting 未配置 mall_id={$mallId}");
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '未配置短信服务', 'provider' => '-'];
        }

        $provider = $setting['sms_type'] ?? '';
        try {
            if ($provider === 'dxb') {
                // 统一注入短信签名:正文未自带【签名】时,前置全局配置的 dxb_sign(留空则不加,适配网关自带签名的账号)
                $content = self::applyDxbSign($content, (string)($setting['dxb_sign'] ?? ''));
                return array_merge(
                    self::sendDxbRaw($setting['dxb_user'] ?? '', $setting['dxb_pass'] ?? '', $phone, $content),
                    ['provider' => 'dxb']
                );
            }

            if ($provider === 'aliyun') {
                $tpl  = $tplOpts['aliyun_tpl']  ?? '';
                $vars = $tplOpts['aliyun_vars'] ?? [];
                if (!$tpl) {
                    \Yii::warning("[SmsHelper] aliyun 模板缺失 phone={$phone}");
                    return ['code' => ApiCode::CODE_FAIL, 'msg' => 'aliyun 模板未配置', 'provider' => 'aliyun'];
                }
                return array_merge(self::sendAliyunTpl($setting, $phone, $tpl, $vars), ['provider' => 'aliyun']);
            }

            if ($provider === 'tx') {
                $tpl  = $tplOpts['tx_tpl']  ?? '';
                $vars = $tplOpts['tx_vars'] ?? [];
                if (!$tpl) {
                    \Yii::warning("[SmsHelper] tx 模板缺失 phone={$phone}");
                    return ['code' => ApiCode::CODE_FAIL, 'msg' => 'tx 模板未配置', 'provider' => 'tx'];
                }
                return array_merge(self::sendTxTpl($setting, $phone, $tpl, $vars), ['provider' => 'tx']);
            }

            return ['code' => ApiCode::CODE_FAIL, 'msg' => '未识别的 sms_type: ' . $provider, 'provider' => $provider];
        } catch (\Throwable $e) {
            \Yii::warning('[SmsHelper] sendNotify 失败: ' . $e->getMessage() . ' phone=' . $phone);
            return ['code' => ApiCode::CODE_FAIL, 'msg' => $e->getMessage(), 'provider' => $provider];
        }
    }

    /**
     * @return array
     * 短信宝发放 (保留兼容旧 SmsCodeForm)
     */
    public static function sendDxb($user, $pass, $phone, $content)
    {
        $res = self::sendDxbRaw($user, $pass, $phone, $content);
        if ($res['code'] === ApiCode::CODE_SUCCESS) {
            return ResponseHelper::send(ApiCode::CODE_SUCCESS, $res['msg']);
        }
        return ResponseHelper::send(ApiCode::CODE_FAIL, $res['msg']);
    }

    /**
     * dxb(短信宝)正文统一加签名。
     *   - $sign 为空 → 不加(适配网关自动追加签名的账号,避免双签名)
     *   - $content 已以「【」开头 → 视为调用方自带签名,跳过(兼容 login/mch/massage 等自填含签名的正文)
     *   - 否则前置签名;$sign 自带【】原样用,否则补【】
     * 签名内容由 mall_setting.dxb_sign 统一配置(后台「短信设置」)。
     */
    private static function applyDxbSign(string $content, string $sign): string
    {
        $sign = trim($sign);
        if ($sign === '' || $content === '') {
            return $content;
        }
        if (mb_substr($content, 0, 1) === '【') {
            return $content;
        }
        $prefix = (mb_substr($sign, 0, 1) === '【') ? $sign : ('【' . $sign . '】');
        return $prefix . $content;
    }

    /**
     * 真正的 dxb 请求,返回不经 ResponseHelper 包装的结果,供 sendNotify 内部复用
     */
    private static function sendDxbRaw(string $user, string $pass, string $phone, string $content): array
    {
        if (!$user || !$pass) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => 'dxb 账号/密码未配置'];
        }

        $statusStr = [
            "0"  => "短信发送成功",
            "-1" => "参数不全",
            "-2" => "服务器不支持 curl/fsocket",
            "30" => "密码错误",
            "40" => "账号不存在",
            "41" => "余额不足",
            "42" => "帐户已过期",
            "43" => "IP 地址限制",
            "50" => "内容含有敏感词",
        ];

        $smsapi = "http://api.smsbao.com/";
        $pass = md5($pass);
        $url = $smsapi . "sms?u={$user}&p={$pass}&m={$phone}&c=" . urlencode($content);

        $result = @file_get_contents($url);
        if ($result === false) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => 'dxb 网络请求失败'];
        }

        $msg = $statusStr[trim($result)] ?? ('未知响应: ' . $result);
        if (trim($result) === '0') {
            return ['code' => ApiCode::CODE_SUCCESS, 'msg' => $msg];
        }
        return ['code' => ApiCode::CODE_FAIL, 'msg' => $msg];
    }

    private static function sendAliyunTpl(array $setting, string $phone, string $template, array $data): array
    {
        $config = [
            'timeout' => 5.0,
            'default' => [
                'strategy' => \Overtrue\EasySms\Strategies\OrderStrategy::class,
                'gateways' => ['aliyun'],
            ],
            'gateways' => [
                'aliyun' => [
                    'access_key_id'     => $setting['access_key_id']     ?? '',
                    'access_key_secret' => $setting['access_key_secret'] ?? '',
                    'sign_name'         => $setting['sign_name']         ?? '',
                ],
            ],
        ];
        $easySms = new EasySms($config);
        try {
            $result = $easySms->send($phone, [
                'template' => $template,
                'data'     => $data,
            ]);
            $res = $result['aliyun'] ?? [];
            if (($res['status'] ?? '') === 'success') {
                return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '阿里云短信发送成功'];
            }
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '阿里云短信失败: ' . json_encode($res, JSON_UNESCAPED_UNICODE)];
        } catch (NoGatewayAvailableException $e) {
            $inner = $e->getExceptions()['aliyun'] ?? null;
            return ['code' => ApiCode::CODE_FAIL, 'msg' => $inner ? $inner->getMessage() : $e->getMessage()];
        }
    }

    private static function sendTxTpl(array $setting, string $phone, $template, array $data): array
    {
        $config = [
            'timeout' => 5.0,
            'default' => [
                'strategy' => \Overtrue\EasySms\Strategies\OrderStrategy::class,
                'gateways' => ['qcloud'],
            ],
            'gateways' => [
                'qcloud' => [
                    'sdk_app_id' => $setting['tx_sdk_app_id'] ?? '',
                    'secret_id'  => $setting['tx_secret_id']  ?? '',
                    'secret_key' => $setting['tx_secret_key'] ?? '',
                    'sign_name'  => $setting['tx_sign_name']  ?? '',
                ],
            ],
        ];
        $easySms = new EasySms($config);
        try {
            $result = $easySms->send($phone, [
                'template' => $template,
                'data'     => $data,
            ]);
            $res = $result['qcloud'] ?? [];
            if (($res['status'] ?? '') === 'success') {
                return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '腾讯云短信发送成功'];
            }
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '腾讯云短信失败: ' . json_encode($res, JSON_UNESCAPED_UNICODE)];
        } catch (NoGatewayAvailableException $e) {
            $inner = $e->getExceptions()['qcloud'] ?? null;
            return ['code' => ApiCode::CODE_FAIL, 'msg' => $inner ? $inner->getMessage() : $e->getMessage()];
        }
    }
}
