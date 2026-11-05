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

namespace app\plugins\withdraw\services;

use app\funds\BaseFund;
use Yii;

/**
 * 网关注册表 —— 扫描 api/funds/ 下所有可用打款网关，供后台「提现渠道」配置时下拉选择。
 *
 * 不依赖老的 fund_channel/OptionHelper 映射（那套是 Cash 链路的），
 * 直接反射目录里的 BaseFund 子类，渠道存的就是完整类名（遵守"禁 code 映射"铁律）。
 */
class FundRegistry
{
    /** 抽象基类/中间类 + 已知有问题的网关，不作为可选网关 */
    private static $excludes = [
        'BaseFund',
        'BaseFlexibleLaborFund',
        // 微信零钱:openid 取用列全错位,配了必然打款失败或打错人。user 表有三个 openid 列,
        // 各自归属(已核对写入方与 dev 数据)——
        //   openid        ← LoginForm / MobileSignInForm,公众号 openid
        //   mpwx_openid   ← LoginByMpwxPhoneForm / BindMpwxForm,小程序 openid
        //   wechat_openid ← 只有 hardware 插件 BindByFollowForm(关注绑定)写,登录链路零写入
        // 而 WechatFund::getUserAccountInfo 的三个分支:
        //   小程序端取 wechat_openid(登录链路不写,恒空)→ payout 抛「缺少用户OpenID」;
        //   公众号端取 mpwx_openid(那是小程序的)→ 拿到值但 openid 与 appid 不匹配,等于打错身份;
        //   兜底分支从没读过真正的公众号列 openid。
        // checkUserEligibility 同样漏判 openid 列,只用公众号登录的用户会被误拒"请先绑定微信"。
        // 修好三个分支 + 资格判断 + 过一次真实打款验证之前,禁止后台配置。
        'WechatFund',
        // 注:灵工代发(FastPayFlexibleLaborFund)曾因「回调链路整条缺失」不可用 ——
        // notifyUrl 报的 /lg/fastpay.php 文件不存在(404)、actionLg 是只打日志的空壳、
        // 无验签,而它又有 orderQuery 会进对账轮询,等于复刻代付那次「失败却显示已到账」。
        // 现已补齐(入口文件 + 事件 + 监听器 + 验签,见 FastPayAgentNotifyListener),故不再排除。
        // ⚠ 启用前需在后台「资金渠道 → 快付灵活用工」配好 platformPublicKey 等参数
        //   (存于独立配置键 flexible_labor_funds,与其它网关的 funds 不是同一个 key)。
    ];

    /**
     * 列出全部可用网关
     *
     * @param int $mallId
     * @return array [ ['class'=>'app\\funds\\BankFund', 'name'=>'银行卡转账', 'channels'=>['bank']], ... ]
     */
    public static function list(int $mallId): array
    {
        $dir = Yii::getAlias('@app') . '/funds';
        if (!is_dir($dir)) {
            return [];
        }

        $result = [];
        foreach (glob($dir . '/*.php') as $file) {
            $short = basename($file, '.php');
            if (in_array($short, self::$excludes, true)) {
                continue;
            }

            $class = 'app\\funds\\' . $short;
            if (!class_exists($class)) {
                continue;
            }

            $ref = new \ReflectionClass($class);
            if ($ref->isAbstract() || !$ref->isSubclassOf(BaseFund::class)) {
                continue;
            }

            // 实例化失败的网关跳过（不阻断整张列表）
            try {
                /** @var BaseFund $fund */
                $fund = Yii::createObject($class, [$mallId]);
                $display = $fund->getDisplay();
                $result[] = [
                    'class'    => $class,
                    'name'     => is_array($display) ? ($display['name'] ?? $short) : $short,
                    'channels' => $fund->getSupportedChannels(),
                ];
            } catch (\Throwable $e) {
                Yii::warning("[withdraw\\FundRegistry] 网关 {$class} 实例化失败：" . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * 取某网关的进件资料字段（用于后台「资料配置」时预填默认 schema）
     *
     * @return array getOnboardingFields() 的结果，失败返回空数组
     */
    public static function onboardingFields(int $mallId, string $fundClass): array
    {
        if (!class_exists($fundClass) || !is_subclass_of($fundClass, BaseFund::class)) {
            return [];
        }
        try {
            /** @var BaseFund $fund */
            $fund = Yii::createObject($fundClass, [$mallId]);
            return $fund->getOnboardingFields();
        } catch (\Throwable $e) {
            Yii::warning("[withdraw\\FundRegistry] 取 {$fundClass} 进件字段失败：" . $e->getMessage());
            return [];
        }
    }
}
