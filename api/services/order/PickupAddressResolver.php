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

namespace app\services\order;

use app\helpers\OptionHelper;

/**
 * 平台自营自提订单的取货地址来源。
 *
 * 由 mall_setting.pickup_address_type 决定:
 *   - 'platform'(默认) : 后台配置的平台自提点(pickup_* 留空则回退商城注册信息)
 *   - 'user_address'    : 用户自己选一个收货地址,流程与快递配送完全一致,必须选、不做兜底
 *
 * 预览(FormatPreviewResponseStage)与下单落库(ProcessDeliveryStage)都读本类,
 * 保证「用户看到的取货方式」与「订单里存的地址」同源。
 * 商家自提(mch_id > 0)走商户门店,不经过这里。
 */
class PickupAddressResolver
{
    const TYPE_PLATFORM     = 'platform';
    const TYPE_USER_ADDRESS = 'user_address';

    /** 当前商城的自提地址类型;非法值一律按 platform */
    public static function type(int $mallId): string
    {
        $s = OptionHelper::get('mall_setting', $mallId) ?: [];
        $t = (string)($s['pickup_address_type'] ?? self::TYPE_PLATFORM);
        return $t === self::TYPE_USER_ADDRESS ? self::TYPE_USER_ADDRESS : self::TYPE_PLATFORM;
    }

    /** 后台配置的平台自提点;各字段留空则回退商城注册信息 */
    public static function platformPoint(int $mallId): array
    {
        $s = OptionHelper::get('mall_setting', $mallId) ?: [];
        return [
            'name'      => ($s['pickup_name'] ?? '') ?: ($s['name'] ?? ''),
            'address'   => ($s['pickup_address'] ?? '') ?: ($s['address'] ?? ''),
            'latitude'  => ($s['pickup_lat'] ?? '') ?: ($s['lat'] ?? ''),
            'longitude' => ($s['pickup_lon'] ?? '') ?: ($s['lon'] ?? ''),
        ];
    }
}
