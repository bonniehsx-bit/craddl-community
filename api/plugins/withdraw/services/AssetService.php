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

use app\helpers\BalanceLogHelper;
use app\helpers\PriceLogHelper;
use app\models\User;
use app\plugins\withdraw\models\Channel;
use app\services\WalletRegistry;
use app\services\WalletService;

/**
 * 资产服务 —— asset_type ↔ 用户钱包 的桥接。
 *
 * 核心 3 个资产(直挂 User 列 + 各自 LogHelper,原子扣减金融防线):
 *   - 'balance'(余额)   → User.money    → BalanceLogHelper
 *   - 'price'(奖励/佣金) → User.price    → PriceLogHelper
 *   - 'integral'(积分)  → User.integral → 核心网关 IntegralService(§22G,支持幂等键)
 * 其余 = 插件 getWallets() 声明的通用钱包(含已迁移的「货款」mch.funds),
 *   余额存 user_wallet,经核心 app\services\WalletService 原子存取。
 */
class AssetService
{
    /** 资产类型展示名 */
    public static function label(string $assetType): string
    {
        $core = [
            Channel::ASSET_BALANCE  => '余额',
            Channel::ASSET_PRICE    => '奖励',
            Channel::ASSET_INTEGRAL => '积分',
        ];
        if (isset($core[$assetType])) {
            return $core[$assetType];
        }
        // 通用钱包:取注册表展示名(走品牌词库)
        return WalletRegistry::exists($assetType) ? WalletRegistry::label($assetType) : $assetType;
    }

    /**
     * 本商城「可选资产」选项 = 核心 3 + 授权插件的可提现钱包(货款由 mch 声明,只在授权 mch 的商城出现)。
     * 传 $userId(>0) 时,扩展钱包按「逐用户可见性」过滤(如 xwy.channel 走后台逐用户开关);核心 3 恒可见。
     * @return array [['key'=>'balance','label'=>'余额','plugin_label'=>''], ...]
     */
    public static function assetOptions(int $mallId, int $userId = 0): array
    {
        $opts = [
            ['key' => Channel::ASSET_BALANCE,  'label' => '余额', 'plugin_label' => ''],
            ['key' => Channel::ASSET_PRICE,    'label' => '奖励', 'plugin_label' => ''],
            ['key' => Channel::ASSET_INTEGRAL, 'label' => '积分', 'plugin_label' => ''],
        ];
        // 用 withdrawableAll 而不是 all:all() 现在只按「已启用」过滤(后台资产管理也要用它),
        // 能不能提现是另一回事,由后台钱包设置里的 withdrawable 开关决定。
        foreach (WalletRegistry::withdrawableAll($mallId) as $w) {
            // 逐用户可见性:传了 userId 且该钱包对此用户不可见 → 不列出(总览/提现源统一遵守)
            if ($userId > 0 && !WalletRegistry::isVisible($w['key'], $userId)) {
                continue;
            }
            $opts[] = ['key' => $w['key'], 'label' => $w['name'], 'plugin_label' => $w['plugin_label'] ?? ''];
        }
        return $opts;
    }

    /** assetOptions 的 key 列表(校验/兜底用);传 userId 则按逐用户可见性过滤扩展钱包 */
    public static function allAssetTypes(int $mallId, int $userId = 0): array
    {
        return array_column(self::assetOptions($mallId, $userId), 'key');
    }

    /** 读取用户某资产的当前可用余额（字符串，bc 友好） */
    public static function balance(User $user, string $assetType): string
    {
        switch ($assetType) {
            case Channel::ASSET_BALANCE:  return (string)$user->money;
            case Channel::ASSET_PRICE:    return (string)$user->price;
            case Channel::ASSET_INTEGRAL: return (string)$user->integral;
            default:
                // 通用钱包(含 mch.funds)
                return WalletRegistry::exists($assetType)
                    ? WalletService::balance((int)$user->id, $assetType)
                    : '0';
        }
    }

    /**
     * 扣减资产（提现申请时冻结）。失败抛异常（余额不足 / 用户不存在）。
     *
     * @param string $idemKey 幂等键(建议传提现单号)。**目前仅积分路径生效** —— 积分走核心网关
     *   IntegralService::consume,天然幂等;余额/奖励/通用钱包的 LogHelper 尚无幂等形参,
     *   它们靠外层「条件 UPDATE 抢占状态 + 单一事务」防重,这里传了也只是备用。
     * @throws \Throwable
     */
    public static function deduct(int $userId, string $assetType, $amount, string $remark, string $title, string $idemKey = ''): void
    {
        switch ($assetType) {
            case Channel::ASSET_BALANCE:
                BalanceLogHelper::sub($userId, $amount, $remark, 0, 0, $title);
                break;
            case Channel::ASSET_PRICE:
                PriceLogHelper::sub($userId, $amount, $remark, 0, $title);
                break;
            case Channel::ASSET_INTEGRAL:
                // 走核心积分网关:业务类型 withdraw(资产搬运,非消费,报表须单列)
                (new \app\services\IntegralService())->consume($userId, (float)$amount, 'withdraw', [
                    'title'          => $title,
                    'content'        => $remark,
                    'idempotencyKey' => $idemKey ?: null,
                ]);
                break;
            default:
                // 通用钱包(含 mch.funds):原子扣减,余额不足抛异常
                if (WalletRegistry::exists($assetType)) {
                    WalletService::sub($userId, $assetType, $amount, $remark, $title, '');
                    break;
                }
                throw new \Exception('未知的资产类型：' . $assetType);
        }
    }

    /**
     * 退回资产（审核驳回 / 打款失败时原路退回）。
     *
     * @param string $idemKey 幂等键(建议传提现单号)。同 deduct：目前仅积分路径生效。
     * @throws \Throwable
     */
    public static function refund(int $userId, string $assetType, $amount, string $remark, string $title, string $idemKey = ''): void
    {
        self::credit($userId, $assetType, $amount, $remark, $title, $idemKey, 'withdraw_refund');
    }

    /**
     * 入账资产（通用）—— 退回、充值到账都走这里，只是积分侧的业务类型不同。
     *
     * 拆出这个方法是因为积分必须带**业务类型**：全走 withdraw_refund 的话，
     * 后台积分明细里充值到账会显示成"提现退回"，运营对不上账；
     * 而类型是要在 IntegralTypeEvent 里登记的，不能随手编一个。
     *
     * @param string $integralType 积分侧业务类型（须已在 IntegralTypeEvent 登记）
     * @throws \Throwable
     */
    public static function credit(
        int $userId,
        string $assetType,
        $amount,
        string $remark,
        string $title,
        string $idemKey = '',
        string $integralType = 'withdraw_refund'
    ): void {
        switch ($assetType) {
            case Channel::ASSET_BALANCE:
                BalanceLogHelper::add($userId, $amount, $remark, 0, 0, $title);
                break;
            case Channel::ASSET_PRICE:
                PriceLogHelper::add($userId, $amount, $remark, 0, $title);
                break;
            case Channel::ASSET_INTEGRAL:
                // 走核心积分网关(§22G):业务类型由调用方指定
                (new \app\services\IntegralService())->grant($userId, (float)$amount, $integralType, [
                    'title'          => $title,
                    'content'        => $remark,
                    'idempotencyKey' => $idemKey ?: null,
                ]);
                break;
            default:
                // 通用钱包(含 mch.funds):原路退回=入账
                if (WalletRegistry::exists($assetType)) {
                    WalletService::add($userId, $assetType, $amount, $remark, $title, '');
                    break;
                }
                throw new \Exception('未知的资产类型：' . $assetType);
        }
    }

    /** 校验资产类型合法 */
    public static function isValid(string $assetType): bool
    {
        if (in_array($assetType, [
            Channel::ASSET_BALANCE,
            Channel::ASSET_PRICE,
            Channel::ASSET_INTEGRAL,
        ], true)) {
            return true;
        }
        // 通用钱包(含 mch.funds)
        return WalletRegistry::exists($assetType);
    }
}
