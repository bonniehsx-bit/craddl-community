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

namespace app\helpers;

use app\payments\FastPayWechatJsPayment;
use app\payments\WechatPayment;

/**
 * 微信 JSAPI 支付的 appid 一致性检查。
 *
 * ── 为什么需要 ──
 * 微信的硬规则:JSAPI 下单时传的 openid,必须由下单 appid 对应的那个公众号颁发。
 * 而本系统里这两个值来自**两份互不相干的配置**:
 *
 *   openid  ← WechatHelper::init() 读 option『platform_wechat』的 appid 做 OAuth
 *   下单    ← 各支付网关读自己在 option『payments』里的 appid / app_id
 *
 * 填成两个不同的公众号时,前面每一步都正常 —— 收银台列得出来、能跳转、能授权 ——
 * 直到用户真的点了支付才报「openid 与 appid 不匹配」。这是资金链路上最隐蔽的一类
 * 配置错误:表现是「偶发支付失败」,排查方向很容易跑偏到网络或证书上。
 *
 * ── 只警告,不拦截 ──
 * 配置存在合理的中间态(先配公众号还是先配支付、换主体时先改一头),硬拦会让运营
 * 改不动。而且 platform_wechat 未配置时也不该阻断支付配置的保存。
 *
 * ── 哪些网关适用 ──
 * 只有「openid 来自公众号 OAuth」的才适用。小程序那几个(MpwxPayment 等)的 openid
 * 来自小程序 login、appid 是小程序的,拿来和公众号比是错的;App / 余额 / 系统单更无关。
 */
class WechatPayAppIdHelper
{
    /** 网关类 => 它在自己配置里存 appid 的键名(两个网关键名不一样) */
    const GATEWAY_APPID_KEY = [
        FastPayWechatJsPayment::class => 'appid',
        WechatPayment::class          => 'app_id',
    ];

    /**
     * 检查某个网关的 appid 与公众号是否一致。
     *
     * @return string 不一致时返回给运营看的提示;一致、或信息不全无法判断时返回 ''
     */
    public static function mismatchWarning(string $gatewayClass, int $mallId): string
    {
        $key = self::GATEWAY_APPID_KEY[$gatewayClass] ?? null;
        if ($key === null) {
            return '';   // 不适用的网关不参与判断
        }

        $payments = OptionHelper::get('payments', $mallId) ?: [];
        $gatewayAppId = trim((string)($payments[$gatewayClass][$key] ?? ''));

        $wechat = OptionHelper::get('platform_wechat', $mallId) ?: [];
        $mpAppId = trim((string)($wechat['appid'] ?? ''));

        // 任一为空都判断不了 —— 不能因为「还没填」就报不一致,那是噪音
        if ($gatewayAppId === '' || $mpAppId === '') {
            return '';
        }
        if ($gatewayAppId === $mpAppId) {
            return '';
        }

        return "⚠ 支付 appid（{$gatewayAppId}）与公众号 appid（{$mpAppId}）不一致。"
            . '微信要求 JSAPI 下单的 appid 必须与颁发 openid 的公众号一致，'
            . '否则用户点击支付时会报「openid 与 appid 不匹配」——'
            . '前面每一步都正常，只有最后付款那下失败。请确认两处填的是同一个公众号。';
    }

    /** 保存成功的提示语:一致就是原样,不一致则把警告追加上去 */
    public static function appendWarning(string $baseMsg, string $gatewayClass, int $mallId): string
    {
        $w = self::mismatchWarning($gatewayClass, $mallId);
        return $w === '' ? $baseMsg : ($baseMsg . '，但 ' . $w);
    }
}
