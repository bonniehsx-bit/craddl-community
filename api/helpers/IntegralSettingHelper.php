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

/**
 * 积分设置访问器(统一读 OptionHelper('integral_setting'),带默认值兜底)
 *
 * 放核心:计提引擎(integral 插件)与有效期(核心)都要读它;它只是配置读取,无业务逻辑。
 * 所有新增能力**默认关**,未配置的商城维持现状(多租户零影响)。
 * 结构见 docs/方案-积分统一网关与有效期.md §11。
 */
class IntegralSettingHelper
{
    const KEY = 'integral_setting';

    /**
     * 默认配置(新行为一律默认关)
     */
    public static function defaults(): array
    {
        return [
            'accrual' => [
                'enabled' => 0,            // 计提总开关
                'base'    => 'pay_price',  // 计提基数:pay_price(实付) | goods_total_price(商品原额)
                'round'   => 'floor',      // 取整:round | floor | ceil
                // 部分售后(只退其中一个商品)是否按比例回收积分。
                // 1=按该明细占计提基数的比例回收,累计不超过原计提总额(默认:退掉的那件不该带来积分,用户 9/28 定)
                // 0=不回收(原来的默认,平台让利,只有整单回滚才追回;单独存过设置的商城不受默认值影响)
                // 结算前就退掉的那件,计提时直接不算进基数,与这个开关无关(见 IntegralAccrualService::accrueForOrder)
                'partial_revert' => 1,
            ],
            'multiplier' => [
                'enabled'    => 0,         // 多倍总开关
                'birthday'   => ['enabled' => 0, 'times' => 1],             // 会员生日当月
                'member_day' => ['enabled' => 0, 'days' => [], 'times' => 1], // 会员日(每月哪几天,如 [8,18,28])
                'apply_to'   => [],        // 限定订单类型 key(到场类:cashier/passive/photo/public_verify);空=全部计提订单
            ],
            'consume' => [
                // 停车抵扣:多少积分抵 1 元、单次最多抵多少元(0=不限)
                'parking' => ['enabled' => 0, 'rate' => 100, 'max_amount' => 0],
            ],
            'expire' => [
                'enabled'      => 0,             // 有效期总开关(改核心 helper 语义,默认关=行为零变更)
                'mode'         => 'manual',      // manual=手动回收 / rolling=自动滚动过期
                'rolling_month'=> 5,             // rolling:每年几月
                'rolling_day'  => 31,            // rolling:几号到期(回收前一年及更早的未用积分)
            ],
        ];
    }

    /** 短 TTL(秒):既避免一次请求/一批操作内重复读 Option,又把常驻 worker 里的配置传播延迟收敛到秒级 */
    const MEMO_TTL = 5;

    /** @var array memo:[mallId => ['v'=>设置数组, 'exp'=>过期时间戳]] */
    private static $memo = [];

    /**
     * 取某商城的完整积分设置(默认值 + 已保存值,深合并)。
     * 短 TTL 缓存 + 读失败兜底:配置读异常不影响原本能成功的积分加减(仅回落默认=各开关关)。
     * 保存配置后应调 flush() 立即失效(见 IntegralSettingForm);常驻进程未 flush 也最多滞后 MEMO_TTL 秒。
     */
    public static function get($mallId): array
    {
        $mid = (int) $mallId;
        $now = time();
        if (isset(self::$memo[$mid]) && self::$memo[$mid]['exp'] > $now) {
            return self::$memo[$mid]['v'];
        }
        try {
            $saved = OptionHelper::get(self::KEY, $mid);
        } catch (\Throwable $e) {
            \Yii::error('[IntegralSetting] 读配置失败,回落默认: ' . $e->getMessage(), 'integral');
            $saved = [];
        }
        if (!is_array($saved)) {
            $saved = [];
        }
        $v = array_replace_recursive(self::defaults(), $saved);
        self::$memo[$mid] = ['v' => $v, 'exp' => $now + self::MEMO_TTL];
        return $v;
    }

    /**
     * 清请求级缓存(保存配置后/测试改配置后调用,使下次 get 重读)。
     */
    public static function flush($mallId = null): void
    {
        if ($mallId === null) {
            self::$memo = [];
        } else {
            unset(self::$memo[(int) $mallId]);
        }
    }

    /**
     * 计提总开关是否开启
     */
    public static function accrualEnabled($mallId): bool
    {
        return !empty(self::get($mallId)['accrual']['enabled']);
    }
}
