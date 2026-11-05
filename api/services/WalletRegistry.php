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

namespace app\services;

use app\helpers\NounHelper;
use app\helpers\OptionHelper;
use app\models\MallPlugin;
use app\models\Plugin as PluginModel;
use Yii;

/**
 * 钱包注册表(核心)—— 汇总各插件 Plugin::getWallets() 声明的钱包。
 *
 *  - allRaw(): 全部已安装插件声明的钱包(mall 无关),供路由/取名/校验(核心 CommissionService、提现 AssetService 等)
 *  - all($mallId): 本商城【已授权且已启用】的钱包,供后台资产管理 / C 端展示
 *  - withdrawableAll($mallId): 上者中【可提现】的,供提现来源
 *  - exists() 宽松(mall 无关,冲账用) / existsIn() 严格(带 mall,入账用)——
 *    这对非对称是有意的,见各自方法注释
 *
 * 钱包项结构:['key'=>'xwy.channel','name'=>'渠道专户','noun'=>'xwy.channel','plugin'=>'xwy','plugin_label'=>'..','withdrawable'=>true]
 * key 约定带插件前缀(如 xwy.channel),防跨插件冲突;noun 对齐词库「插件.字段」约定(默认=key)。
 */
class WalletRegistry
{
    private static $rawCache = null;

    /** 全部已安装插件声明的钱包(mall 无关,key => 钱包项) */
    public static function allRaw(): array
    {
        if (self::$rawCache !== null) {
            return self::$rawCache;
        }
        $list = [];
        foreach (PluginModel::find()->where(['is_delete' => 0])->all() as $item) {
            $class = '\\app\\plugins\\' . $item->name . '\\Plugin';
            if (!class_exists($class)) {
                continue;
            }
            try {
                $plugin = new $class();
                $pluginLabel = '';
                try {
                    $pluginLabel = (string)$plugin->getLabel(); // 插件中文名,后台展示用前缀防重名
                } catch (\Throwable $e) {
                }
                foreach ($plugin->getWallets() as $w) {
                    if (empty($w['key'])) {
                        continue;
                    }
                    $list[(string)$w['key']] = [
                        'key'          => (string)$w['key'],
                        'name'         => (string)($w['name'] ?? $w['key']),
                        // 名词 key:对齐「插件.字段」约定,可与钱包 key 不同(如钱包 key=mch.funds、名词 key=mch.mch.funds);省略默认=钱包 key
                        'noun'         => (string)($w['noun'] ?? $w['key']),
                        'plugin'       => $item->name,
                        'plugin_label' => $pluginLabel !== '' ? $pluginLabel : $item->name,
                        'withdrawable' => !isset($w['withdrawable']) || !empty($w['withdrawable']),
                    ];
                }
            } catch (\Throwable $e) {
                Yii::warning('[WalletRegistry] 插件 ' . $item->name . ' getWallets 失败: ' . $e->getMessage());
            }
        }
        return self::$rawCache = $list;
    }

    /** option 名:逐商城的钱包开关。结构 ['xwy.channel' => ['enabled'=>1,'withdrawable'=>1], ...] */
    const SETTING_NAME = 'wallet_setting';

    /** @var array<int,array> 逐商城的开关配置缓存(键必须带 mallId,否则多商城请求串场) */
    private static $settingCache = [];

    /**
     * 取本商城的钱包开关配置。**未配置的钱包一律按「启用」处理** ——
     * 这个默认值是有意的:开关是后加的,不能让历史上已在用的钱包因为没人去点一下而突然消失。
     */
    public static function settings(int $mallId): array
    {
        if (isset(self::$settingCache[$mallId])) {
            return self::$settingCache[$mallId];
        }
        $conf = OptionHelper::get(self::SETTING_NAME, $mallId, []);
        return self::$settingCache[$mallId] = is_array($conf) ? $conf : [];
    }

    /** 保存开关后失效进程内缓存,否则同一次请求里后续读到的还是旧值 */
    public static function flushSettingCache(?int $mallId = null): void
    {
        if ($mallId === null) {
            self::$settingCache = [];
            return;
        }
        unset(self::$settingCache[$mallId]);
    }

    /** 本商城是否启用了该钱包(未配置默认启用) */
    public static function isEnabled(string $key, int $mallId): bool
    {
        $conf = self::settings($mallId)[$key] ?? null;
        if (!is_array($conf) || !array_key_exists('enabled', $conf)) {
            return true;
        }
        return (int)$conf['enabled'] === 1;
    }

    /** 本商城该钱包是否可提现:后台开关优先,没配过则用插件 getWallets 里的声明 */
    public static function isWithdrawable(string $key, int $mallId): bool
    {
        $conf = self::settings($mallId)[$key] ?? null;
        if (is_array($conf) && array_key_exists('withdrawable', $conf)) {
            return (int)$conf['withdrawable'] === 1;
        }
        return !empty(self::allRaw()[$key]['withdrawable']);
    }

    /**
     * 本商城【已授权且已启用】的钱包列表。
     *
     * ⚠ 这里**不再**按 withdrawable 过滤。原实现拿 withdrawable 当通用可见性用,
     * 而后台用户资产(UserWalletForm)正是消费本方法 —— 于是声明 withdrawable=false 的钱包
     * 在后台会凭空消失、连余额都管理不了。两个语义必须分开:
     *   enabled      → 是否挂载(本方法过滤)
     *   withdrawable → 能不能提现(只由提现侧自己筛,见 withdrawableAll)
     */
    public static function all(int $mallId): array
    {
        $authorized = Yii::$app->plugin->resolveAuthorizedPluginNames();
        $res = [];
        foreach (self::allRaw() as $w) {
            if ($authorized !== null && !in_array($w['plugin'], $authorized, true)) {
                continue;
            }
            if (!self::isEnabled($w['key'], $mallId)) {
                continue;
            }
            // 钱包名走品牌词库(可后台改叫法),与 余额/佣金/积分 一致;未配置/未声明则用 getWallets 默认名
            $w['name'] = self::resolveName($w['noun'], $w['name'], $mallId);
            $w['withdrawable'] = self::isWithdrawable($w['key'], $mallId);
            $res[] = $w;
        }
        return $res;
    }

    /** 本商城可作为「提现来源」的钱包 = 已启用 且 可提现 */
    public static function withdrawableAll(int $mallId): array
    {
        return array_values(array_filter(self::all($mallId), function ($w) {
            return !empty($w['withdrawable']);
        }));
    }

    /**
     * 是否一个已注册钱包 key(**mall 无关**,供路由/校验)。
     *
     * ⚠ 冲账/退款路径必须继续用这个宽松版本:钱已经打进去了,哪怕之后插件取消授权、
     * 钱包被关掉,也必须还能把它扣回来。收紧成 existsIn 会让那些单子冲账失败、钱扣不回来。
     */
    public static function exists(string $key): bool
    {
        return isset(self::allRaw()[$key]);
    }

    /**
     * 该钱包在本商城是否可用(已注册 + 已授权 + 已启用)。**入账路径用这个**:
     * 没授权/没启用的钱包不该被打进钱 —— 否则钱进得去、出不来
     * (提现侧按 all() 过滤,那笔钱在提现列表里根本看不到)。
     */
    public static function existsIn(string $key, int $mallId): bool
    {
        $entry = self::allRaw()[$key] ?? null;
        if (!$entry || $mallId <= 0) {
            return false;
        }
        if (!self::isEnabled($key, $mallId)) {
            return false;
        }
        // ⚠ 授权判断必须按**传入的 mallId** 直接查,不能借道 all() ——
        // all() 里的 resolveAuthorizedPluginNames() 读的是 Yii::$app->mall 这个全局上下文,
        // 而本方法的主要调用方是分润结算(cron/job),那里的全局上下文可能是另一个商城,
        // 借道就会拿 A 商城的授权去判断 B 商城的单子。
        return MallPlugin::find()
            ->where(['mall_id' => $mallId, 'plugin' => $entry['plugin'], 'is_delete' => 0])
            ->exists();
    }

    /**
     * 钱包对某用户是否可见(逐用户可见性,由声明钱包的插件 isWalletVisible 决定)。
     * 用于总览/提现源等通用面统一按用户过滤;未知钱包不可见,出错默认可见(不误藏)。
     */
    public static function isVisible(string $key, int $userId): bool
    {
        $entry = self::allRaw()[$key] ?? null;
        if (!$entry) {
            return false;
        }
        $class = '\\app\\plugins\\' . $entry['plugin'] . '\\Plugin';
        if (!class_exists($class)) {
            return true;
        }
        try {
            return (bool)(new $class())->isWalletVisible($key, $userId);
        } catch (\Throwable $e) {
            Yii::warning('[WalletRegistry] isWalletVisible 失败 key=' . $key . ': ' . $e->getMessage());
            return true;
        }
    }

    /** 钱包展示名(走品牌词库) */
    public static function label(string $key): string
    {
        $entry = self::allRaw()[$key] ?? null;
        $default = $entry['name'] ?? $key;
        $noun = $entry['noun'] ?? $key;
        return self::resolveName($noun, $default, null);
    }

    /**
     * 钱包展示名:优先品牌词库(NounHelper::parse,可后台配置,= 前端 $noun),
     * 未配置/未声明名词则回退 getWallets 声明的默认名(绝不露出英文 key)。
     * @param string $nounKey 品牌词库 key(钱包的 noun 字段,默认=钱包 key)
     */
    private static function resolveName(string $nounKey, string $default, ?int $mallId): string
    {
        $word = NounHelper::parse($nounKey, $mallId);
        return ($word === '' || $word === $nounKey) ? $default : $word;
    }
}
