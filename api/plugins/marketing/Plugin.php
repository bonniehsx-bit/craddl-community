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

namespace app\plugins\marketing;

use app\helpers\FileHelper;
use app\plugins\marketing\listeners\MarketingIntegralTypeListener;
use app\plugins\marketing\listeners\MarketingOrderListener;
use app\plugins\marketing\listeners\MarketingLevelListener;
use app\plugins\marketing\listeners\MarketingUserListener;

/**
 * 营销活动插件
 *
 * 定位:营销发放中台。券本身复用平台 coupon/user_coupon,本插件只做
 * "给谁、何时、发哪张、发多少、效果如何"的编排 + 自动发放 + 数据回收,
 * 发券统一走核心 CouponService,零侵入核心券表结构(开发规范 §8)。
 */
class Plugin extends \app\plugins\Plugin
{
    public function getLabel()
    {
        return '营销活动';
    }

    public function getName()
    {
        return 'marketing';
    }

    public function getIcon(): string
    {
        return 'el-icon-present';
    }

    public function getLogo()
    {
        $plugin = \app\models\Plugin::findOne(['name' => $this->getName(), 'is_delete' => 0]);
        return \app\helpers\PluginHelper::resolveLogo($this->getName(), $plugin->logo ?? '');
    }

    public function getType()
    {
        return self::TYPE_MARKET;
    }

    public function getVersion()
    {
        return '1.1.0';
    }

    public function getHistoryVersion()
    {
        return [
            '1.0.0' => '营销优惠券初始版本:活动/触发发券/人群定向/预算自停/定时群发/效果看板',
            '1.1.0' => '更名营销活动;奖励支持积分/余额;新增累计下单阶梯奖(按单数/金额,活动期/月/周/终身);每满N单循环奖/指定商品累计/新客复购/邀请首单有礼;订单售后自动回收奖励;C端奖励进度',
        ];
    }

    public function getDsc()
    {
        return '营销活动:围绕下单、注册、邀请等用户行为做营销编排,奖励可发优惠券/积分/余额。支持注册礼包/首单/满额/累计下单阶梯/会员升级/生日/沉睡召回等触发发放,按会员等级/消费/首单等人群定向,定时群发,预算封顶自停,订单售后自动回收,并提供发放→核销→带动GMV 的效果看板。';
    }

    /**
     * C 端链接:营销领券中心 + 我的优惠券,商户可在后台挂到个人中心宫格。
     */
    public function getLinks()
    {
        return [
            ['name' => '领券中心', 'url' => '/plugins2/marketing/coupon-center'],
            ['name' => '我的优惠券', 'url' => '/plugins2/marketing/my-coupons'],
            ['name' => '奖励进度', 'url' => '/plugins2/marketing/reward-progress'],
        ];
    }

    public function getListeners()
    {
        return [
            MarketingUserListener::class,         // 用户注册 → 新人礼包(即时到账)
            MarketingOrderListener::class,        // 订单 支付奖 / 结算累计奖 / 售后回收
            MarketingLevelListener::class,        // 会员升级 → 升级奖励
            MarketingIntegralTypeListener::class, // 登记积分类型「营销奖励」
        ];
    }

    public function getCrons(): array
    {
        return [
            \app\plugins\marketing\cron\MarketingNewUserTask::class,       // 新注册用户 → 新人礼包
            \app\plugins\marketing\cron\MarketingBirthdayTask::class,      // 生日券
            \app\plugins\marketing\cron\MarketingRecallTask::class,        // 沉睡召回券
            \app\plugins\marketing\cron\MarketingBatchDispatchTask::class, // 定时群发到点分批发放
        ];
    }

    /**
     * 活动/触发规则属配置,不可清空;发放流水与群发任务属流水/记录,测试可清空。
     */
    public function getProtectedTables(): array
    {
        return [
            'plugin_marketing_campaign',
            'plugin_marketing_campaign_coupon',
            'plugin_marketing_reward',
            'plugin_marketing_trigger',
        ];
    }
}
