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

namespace app\plugins\withdraw\forms\admin\setting;

use app\core\ApiCode;
use app\helpers\OptionHelper;
use app\models\BaseModel;
use Yii;

/**
 * 提现插件 - 全局设置
 *
 * 存 OptionHelper 'withdraw_setting'：
 *  - enabled  全局提现开关（关闭后 C 端 prepare 返回停用，所有渠道不可用）
 *  - notice   提现须知文案（展示在 C 端提现页底部）
 *  - entries  各资金来源提现入口开关 {balance,price,integral}，控制中心页「提现」按钮显隐，
 *             与老 cash 的 is_cash 解耦，独立控制。
 */
class SettingForm extends BaseModel
{
    const KEY = 'withdraw_setting';
    const ASSETS = ['balance', 'price', 'integral']; // 核心 3;货款 mch.funds 改由 mch 插件声明,经 assetKeys() 从注册表带入

    // 老 cash 提现页的 type → 本插件 asset 的映射（接管跳转用）

    public $enabled;
    public $notice;
    public $entries;   // array：{balance:1, price:1, integral:1}
    public $day_limit_count; // 全局每日提现次数上限(跨所有渠道统一计数;0=不限),与渠道级 Channel.day_limit_count 并存
    public $day_limit_tip;   // 达到每日次数上限时的拦截文案(可配,{count} 占位符=上限次数)
    public $finance;   // array：转赠/转换/显示等财务开关(收编自旧 cash_setting.other),见 FINANCE_DEFAULTS

    public function rules()
    {
        return [
            [['enabled', 'day_limit_count'], 'integer'],
            [['notice', 'day_limit_tip'], 'string'],
            [['entries', 'finance'], 'safe'],
            [['enabled'], 'default', 'value' => 1],
            [['day_limit_count'], 'default', 'value' => 0],
            [['notice', 'day_limit_tip'], 'default', 'value' => ''],
        ];
    }

    /**
     * 财务开关默认值(收编自旧 cash_setting.other,除已被渠道取代的银行卡/微信打款限额)。
     * 首次(withdraw_setting.finance 未落库)以旧 cash_setting.other 逐键兜底,老数据零丢失;
     * 后台保存新页后 withdraw_setting.finance 即为唯一权威源。
     */
    const FINANCE_DEFAULTS = [
        'balance_transfer_enabled'      => 0,   // 余额转赠开关
        'integral_transfer_enabled'     => 0,   // 积分转赠开关
        'integral_transfer_fee_rate'    => '0', // 积分转赠手续费率(%)
        'balance_to_integral_enabled'   => 0,   // 余额转积分开关
        'one_rmb_to_integral'           => 1,   // 1 元兑换积分数
        'hide_balance_detail'           => 0,   // 隐藏余额明细
    ];

    /** finance 键 → 旧 cash_setting.other 键的映射(用于首次兜底迁移) */
    const FINANCE_LEGACY_MAP = [
        'balance_transfer_enabled'      => 'is_balance_transfer',
        'integral_transfer_enabled'     => 'is_integral_transfer',
        'integral_transfer_fee_rate'    => 'integral_transfer_service_price',
        'balance_to_integral_enabled'   => 'is_balance_to_integral',
        'one_rmb_to_integral'           => 'one_rmb_to_integral',
        'hide_balance_detail'           => 'is_hide_balance_detail',
    ];

    /** 每日次数上限拦截文案默认值({count}=上限次数) */
    const DEFAULT_DAY_LIMIT_TIP = '亲,一天只能提现一次,请第二天再来提现哦';

    /** 读取（后台回显） */
    public function getData()
    {
        $mallId = (int)Yii::$app->mall->id;
        $s = self::get($mallId);
        // 资产选项(核心4 + 授权插件可提现钱包)供后台动态渲染各来源开关
        $s['asset_options'] = \app\plugins\withdraw\services\AssetService::assetOptions($mallId);
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', $s);
    }

    /** 保存 */
    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $entries = [];
        $in = is_array($this->entries) ? $this->entries : [];
        foreach (self::assetKeys((int)Yii::$app->mall->id) as $a) {
            $entries[$a] = isset($in[$a]) ? (int)(!empty($in[$a])) : 1;
        }
        OptionHelper::set(self::KEY, [
            'enabled'  => (int)$this->enabled,
            'notice'   => (string)$this->notice,
            'entries'  => $entries,
            'day_limit_count' => max(0, (int)$this->day_limit_count),
            'day_limit_tip' => trim((string)$this->day_limit_tip),
            'finance'  => self::normalizeFinance($this->finance),
        ], (int)Yii::$app->mall->id);
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '保存成功');
    }

    /** 归一化 finance 提交值：只认白名单键，开关转 0/1，费率/兑换比留字符串数值 */
    private static function normalizeFinance($in): array
    {
        $in = is_array($in) ? $in : [];
        $out = [];
        foreach (self::FINANCE_DEFAULTS as $k => $default) {
            if (in_array($k, ['integral_transfer_fee_rate', 'one_rmb_to_integral'], true)) {
                // 数值型：费率(%)、兑换比。缺省回落默认，负数归零
                $v = isset($in[$k]) ? (float)$in[$k] : (float)$default;
                $out[$k] = (string)max(0, $v);
            } else {
                $out[$k] = isset($in[$k]) ? (int)(!empty($in[$k])) : (int)$default;
            }
        }
        return $out;
    }

    /** 静态读取，供 C 端 prepare / 中心页复用 */
    public static function get(int $mallId): array
    {
        $s = OptionHelper::get(self::KEY, $mallId, []);
        $entriesRaw = is_array($s['entries'] ?? null) ? $s['entries'] : [];
        $entries = [];
        foreach (self::assetKeys($mallId) as $a) {
            $entries[$a] = isset($entriesRaw[$a]) ? (int)$entriesRaw[$a] : 1; // 默认开启
        }
        return [
            'enabled'  => isset($s['enabled']) ? (int)$s['enabled'] : 1, // 默认开启
            'notice'   => $s['notice'] ?? '',
            'entries'  => $entries,
            'day_limit_count' => isset($s['day_limit_count']) ? (int)$s['day_limit_count'] : 0, // 全局每日提现次数上限,0=不限
            'day_limit_tip' => (isset($s['day_limit_tip']) && trim((string)$s['day_limit_tip']) !== '') ? (string)$s['day_limit_tip'] : self::DEFAULT_DAY_LIMIT_TIP,
            'finance'  => self::financeConfig($mallId),
        ];
    }

    /**
     * 财务开关唯一读取入口(后台回显 / C 端 / 转赠 Form 都走这里)。
     * withdraw_setting.finance 已落库 → 以它为准;某键缺失 → 逐键回落旧 cash_setting.other,再回落 FINANCE_DEFAULTS。
     */
    public static function financeConfig(int $mallId): array
    {
        $s = OptionHelper::get(self::KEY, $mallId, []);
        $finance = is_array($s['finance'] ?? null) ? $s['finance'] : [];
        $legacyOther = OptionHelper::get('cash_setting', $mallId, [])['other'] ?? [];
        $legacyOther = is_array($legacyOther) ? $legacyOther : [];

        $out = [];
        foreach (self::FINANCE_DEFAULTS as $k => $default) {
            $isNumeric = in_array($k, ['integral_transfer_fee_rate', 'one_rmb_to_integral'], true);
            if (array_key_exists($k, $finance)) {
                $out[$k] = $isNumeric ? (string)max(0, (float)$finance[$k]) : (int)(!empty($finance[$k]));
                continue;
            }
            // 回落旧 cash_setting.other
            $legacyKey = self::FINANCE_LEGACY_MAP[$k] ?? null;
            if ($legacyKey !== null && array_key_exists($legacyKey, $legacyOther)) {
                $out[$k] = $isNumeric ? (string)max(0, (float)$legacyOther[$legacyKey]) : (int)(!empty($legacyOther[$legacyKey]));
                continue;
            }
            $out[$k] = $isNumeric ? (string)$default : (int)$default;
        }
        return $out;
    }


    /** 本商城所有「提现入口资产」key = 核心 4 + 授权插件的可提现钱包(供 entries 枚举) */
    public static function assetKeys(int $mallId): array
    {
        $keys = self::ASSETS;
        foreach (\app\services\WalletRegistry::all($mallId) as $w) {
            $keys[] = $w['key'];
        }
        return array_values(array_unique($keys));
    }

    /** 某资金来源的提现入口是否开启 = 全局开 且 该资产入口开 */
    public static function entryEnabled(int $mallId, string $asset): bool
    {
        $s = self::get($mallId);
        return !empty($s['enabled']) && !empty($s['entries'][$asset]);
    }
}
