<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-29
 * Time: 11:30
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

namespace app\plugins\mch;


use app\helpers\FileHelper;

use app\plugins\mch\listeners\FirstOrderDiscountListener;
use app\plugins\mch\listeners\OrderListener;
use app\plugins\mch\listeners\MchListener;

class Plugin extends \app\plugins\Plugin
{

    public function getLabel()
    {
        // TODO: Implement getLabel() method.
        return '多商户';
    }

    public function getName()
    {
        // TODO: Implement getName() method.
        return "mch";
    }

    /**
     * 应用中心图标（element-ui 图标类名）。矢量图标不依赖文件/host，免设计、免托管。
     */
    public function getIcon(): string
    {
        return 'el-icon-s-shop';
    }

    public function getLogo()
    {
        $plugin = \app\models\Plugin::findOne(['name' => $this->getName(), 'is_delete' => 0]);
        return \app\helpers\PluginHelper::resolveLogo($this->getName(), $plugin->logo ?? '');
    }

    /**
     * 获取移动端链接
     * @return array
     */
    public function getLinks()
    {
        // 已根据提供的 mch 路径配置进行替换
        return [
            ['name' => '商户入驻', 'url' => '/plugins1/mch/apply'],
            ['name' => '商家中心', 'url' => '/plugins1/mch/admin/center'],
            ['name' => '附近商家', 'url' => '/plugins1/mch/nearby-shops'],
            ['name' => '商家列表', 'url' => '/plugins1/mch/shop-list'],

            ['name' => '商家订单', 'url' => '/plugins1/mch/orders'],
            ['name' => '订单详情', 'url' => '/plugins1/mch/order-detail'],
            ['name' => '售后订单', 'url' => '/plugins1/mch/refund-orders'],

            ['name' => '会员管理', 'url' => '/plugins1/mch/member-list'],
            ['name' => '优惠券管理', 'url' => '/plugins1/mch/coupon-list'],
            ['name' => '发放优惠券', 'url' => '/plugins1/mch/coupon-grant'],

            ['name' => '扫码核销', 'url' => '/plugins1/mch/admin/verify'],
            ['name' => '我的核销', 'url' => '/plugins1/mch/my-verify'],
            ['name' => '核销记录', 'url' => '/plugins1/mch/verify-records'],

            ['name' => '店员管理', 'url' => '/plugins1/mch/clerk-list'],
            ['name' => '添加店员', 'url' => '/plugins1/mch/clerk-add'],

        ];
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-09-10
     * @Time: 16:59
     * @Note:获取事件监听器
     */
    public function getListeners()
    {
        // TODO: Implement getListeners() method.
        return [
            MchListener::class,
            FirstOrderDiscountListener::class,
            OrderListener::class,
            \app\plugins\mch\listeners\GoodsListener::class,
            \app\plugins\mch\listeners\CommissionTypeListener::class,
            \app\plugins\mch\listeners\RecruitListener::class,
            \app\plugins\mch\listeners\DiyComponentListener::class,
            // 分润统一回滚:结算后发生取消/退款时冲回商家货款+运费,否则商家白拿(资损)
            \app\plugins\mch\listeners\CommissionRevertListener::class,
        ];
    }

    public function getCrons(): array
    {
        return [
            \app\plugins\mch\cron\MchSettlementTask::class,
        ];
    }


    public function getType()
    {
        // TODO: Implement getType() method.
        return self::TYPE_TOOL;
    }

    public function getVersion()
    {
        // TODO: Implement getVersion() method.
        return file_get_contents(__DIR__ . '/version');
    }

    public function getDsc()
    {
        // TODO: Implement getDsc() method.
        return "多商家";
    }

    public function getHistoryVersion()
    {
        // TODO: Implement getHistoryVersion() method.

        return [
            '1.0.0'
        ];
    }

    public static function syncGoods($id)
    {
        // TODO: Implement syncGoods() method.
    }

    /**
     * 声明本插件不可清空的表（配置/字典/模板/等级类）
     */
    public function getProtectedTables(): array
    {
        return [
            'plugin_mch_cat',
        ];
    }


    /**
     * 订单列表「按类型可配置展示」声明,详见基类 Plugin::getOrderTypes()。
     */
    public function getOrderTypes(): array
    {
        // key=mch → pluginsForKeys 映射为 order.plugin IN ('mch'),筛出的是全部商家订单。
        // 历史上错标成「收银台订单」(收银台已拆为独立 cashier 插件,自带 key=cashier),此处正名。
        return [['key' => 'mch', 'name' => '商家订单', 'plugins' => ['mch'], 'icon' => '']];
    }

    /**
     * 货款账户(mch.funds)—— 已从核心 User 列迁入通用钱包(user_wallet),由本插件声明为可提现钱包。
     * 入账仍由核心 CommissionService 结算(PAYOUT_TO_MCH_FUNDS),此处只声明让它接入提现链路。
     */
    public function getWallets(): array
    {
        // 钱包 key 仍是 mch.funds(asset_type/结算/迁移沿用);名词 key 用「插件.字段」格式对齐词库约定
        return [
            ['key' => 'mch.funds', 'name' => '货款', 'noun' => 'mch.funds', 'withdrawable' => true],
        ];
    }

    /**
     * 「货款」钱包的逐用户可见性(范本:xwy\Plugin::isWalletVisible 的渠道专户)。
     *
     * 基类默认恒 true,会让**普通买家**在提现页/资产总览里看到一个余额为 0 的「货款」项。
     * 这里收紧为:是商家(有 Mch 记录) 或 该钱包仍有余额(退驻商家仍需提完历史货款) 才可见。
     *
     * 消费方:WalletRegistry::isVisible → withdraw 的 AssetService::assetOptions / ApplyForm /
     *        PrepareForm / WalletForm 四处统一过滤。
     */
    public function isWalletVisible(string $walletKey, int $userId): bool
    {
        if ($walletKey !== 'mch.funds') {
            return true;
        }
        $mallId = (int)(\Yii::$app->mall->id ?? 0);
        $isMerchant = \app\plugins\mch\models\Mch::find()
            ->where(['user_id' => $userId, 'mall_id' => $mallId, 'is_delete' => 0])
            ->exists();
        if ($isMerchant) {
            return true;
        }
        return (float)\app\services\WalletService::balance($userId, 'mch.funds') > 0;
    }

    /** 货款名词(对齐「插件.字段」约定;后台可改叫法,前端 $noun('mch.funds')) */
    public function getNouns(): array
    {
        return [
            'mch.funds' => '货款',
        ];
    }

    /** 词条元数据：货款=资产名词 */
    public function getNounMeta(): array
    {
        return [
            'mch.funds' => ['type' => 'noun'],
        ];
    }
}
