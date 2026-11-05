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

namespace app\plugins\withdraw\models;

use app\models\BaseActiveRecord;

/**
 * 提现渠道（配置）
 *
 * 一条渠道 = 方式 + 网关(1:1) + 资料 + 费规 + 适用对象。
 * "谁能用" 由 user_types 决定；"提哪个钱包" 由 asset_types 决定；
 * "打款给谁执行" 由 fund_class 反射决定；"要填什么资料" 由 onboarding_override（叠加网关默认）决定。
 *
 * @property int    $id
 * @property int    $mall_id
 * @property string $name
 * @property string $fund_class       网关完整类名(反射)
 * @property string $user_types       json: ["user","merchant"]
 * @property string $asset_types      json: ["balance","price","integral"]
 * @property string $onboarding_override json
 * @property string $fund_config      json
 * @property int    $status
 */
class Channel extends BaseActiveRecord
{
    // 用户类型
    const USER_TYPE_USER     = 'user';      // 普通用户
    const USER_TYPE_MERCHANT = 'merchant';  // 商户

    // 资金来源
    const ASSET_BALANCE   = 'balance';    // 余额
    const ASSET_PRICE     = 'price';      // 奖励/佣金
    const ASSET_INTEGRAL  = 'integral';   // 积分
    // 货款(mch.funds)已迁为「通用钱包」,由 mch 插件 getWallets 声明,不再是核心资产常量

    const STATUS_OFF = 0;
    const STATUS_ON  = 1;

    public static function tableName()
    {
        return '{{%plugin_withdraw_channel}}';
    }

    /** 适用用户类型数组 */
    public function getUserTypeList(): array
    {
        return $this->decodeJsonField($this->user_types);
    }

    /** 适用资金来源数组 */
    public function getAssetTypeList(): array
    {
        return $this->decodeJsonField($this->asset_types);
    }

    /** 网关参数覆盖 */
    public function getFundConfig(): array
    {
        return $this->decodeJsonField($this->fund_config);
    }

    /** 资料 schema 覆盖（空数组=用网关默认 getOnboardingFields） */
    public function getOnboardingOverride(): array
    {
        return $this->decodeJsonField($this->onboarding_override);
    }

    /** 该渠道是否对某用户类型开放 */
    public function allowsUserType(string $userType): bool
    {
        $list = $this->getUserTypeList();
        return empty($list) ? false : in_array($userType, $list, true);
    }

    /** 该渠道是否支持某资金来源 */
    public function allowsAssetType(string $assetType): bool
    {
        $list = $this->getAssetTypeList();
        return empty($list) ? false : in_array($assetType, $list, true);
    }

    private function decodeJsonField($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
