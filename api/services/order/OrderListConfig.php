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
 * C 端订单列表「可配置」配置读写(每商城一份,存 jxmall_option)。
 *
 * 结构:
 *   status_tabs:    [['key','name','status'(0-5),'enabled'], ...]  状态 tab 显隐 + 改名 + 排序
 *   included_types: ['mall','group',...]  本列表只展示这些订单类型 key(空 = 全部,向后兼容)
 *
 * 空配置(商城没存过)= 老版写死行为:6 个状态 tab、不限类型。
 */
class OrderListConfig
{
    const OPTION_KEY = 'order_list_config';

    /**
     * 默认状态 tab(= 老版 order.vue 写死的 6 个)。
     * status 值对应 OrderListForm::getList() 的状态筛选 switch(0=全部)。
     * @return array
     */
    public static function defaultStatusTabs(): array
    {
        return [
            ['key' => 'all',        'name' => '全部',        'status' => 0, 'enabled' => true],
            ['key' => 'unpaid',     'name' => '待付款',      'status' => 1, 'enabled' => true],
            ['key' => 'to_ship',    'name' => '待发货/使用', 'status' => 2, 'enabled' => true],
            ['key' => 'to_receive', 'name' => '待收货',      'status' => 3, 'enabled' => true],
            ['key' => 'to_comment', 'name' => '待评价',      'status' => 4, 'enabled' => true],
            ['key' => 'closed',     'name' => '关闭',        'status' => 5, 'enabled' => true],
        ];
    }

    /**
     * 取某商城的配置(已填默认值;商城没配过 → 返回老行为)。
     * @param int $mallId
     * @return array ['status_tabs'=>[...], 'included_types'=>[...]]
     */
    public static function get(int $mallId): array
    {
        $cfg = OptionHelper::get(self::OPTION_KEY, $mallId, []);
        if (!is_array($cfg)) {
            $cfg = [];
        }
        $tabs = (!empty($cfg['status_tabs']) && is_array($cfg['status_tabs']))
            ? array_values($cfg['status_tabs'])
            : self::defaultStatusTabs();
        $included = (isset($cfg['included_types']) && is_array($cfg['included_types']))
            ? array_values($cfg['included_types'])
            : [];

        return ['status_tabs' => $tabs, 'included_types' => $included];
    }
}
